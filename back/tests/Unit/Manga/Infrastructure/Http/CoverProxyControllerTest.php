<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Http;

use App\Manga\Infrastructure\Http\CoverProxyController;
use App\Shared\Domain\Exception\RateLimitExceededException;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use App\Tests\Doubles\Shared\InMemoryRateLimitCounterStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The rejection paths are covered end-to-end in the functional test; this
 * covers the fetch itself — above all that a redirect cannot walk the request
 * off the allowlist, which is the SSRF control and is invisible from outside.
 */
final class CoverProxyControllerTest extends TestCase
{
    private const string ALLOWED = 'https://books.google.com/books/content?id=1';
    private const string MANGADEX = 'https://uploads.mangadex.org/covers/a/b.jpg';
    private const string BNF = 'https://catalogue.bnf.fr/couverture?appName=NE&idArk=ark:/12148/cb37148339x&couverture=1';

    public function testReturnsUpstreamImage(): void
    {
        $client   = new MockHttpClient(new MockResponse('IMAGE-BYTES', [
            'response_headers' => ['content-type' => 'image/jpeg'],
        ]));
        $response = $this->handle($client, self::ALLOWED);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('IMAGE-BYTES', $response->getContent());
        self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        // Asserted per directive: ResponseHeaderBag re-serialises Cache-Control
        // in alphabetical order, so the literal string is not ours to predict.
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('604800', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testFollowsARedirectThatStaysOnTheAllowlist(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', [
                'http_code'        => 302,
                'response_headers' => ['location' => 'https://books.googleusercontent.com/x.jpg'],
            ]),
            new MockResponse('REDIRECTED', ['response_headers' => ['content-type' => 'image/jpeg']]),
        ]);

        $response = $this->handle($client, self::ALLOWED);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('REDIRECTED', $response->getContent());
        self::assertSame(2, $client->getRequestsCount());
    }

    public function testResolvesARelativeRedirectAgainstTheCurrentHost(): void
    {
        $requestedUrls = [];
        $responses     = [
            new MockResponse('', [
                'http_code'        => 302,
                'response_headers' => ['location' => '/covers/moved.jpg'],
            ]),
            new MockResponse('MOVED', ['response_headers' => ['content-type' => 'image/jpeg']]),
        ];
        $client = new MockHttpClient(
            function (string $method, string $url) use (&$requestedUrls, &$responses): MockResponse {
                $requestedUrls[] = $url;

                return array_shift($responses);
            },
        );

        $response = $this->handle($client, self::MANGADEX);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            [self::MANGADEX, 'https://uploads.mangadex.org/covers/moved.jpg'],
            $requestedUrls,
        );
    }

    /** The SSRF control: an allowlisted host must not be able to hand us another. */
    public function testRefusesARedirectThatLeavesTheAllowlist(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', [
                'http_code'        => 302,
                'response_headers' => ['location' => 'http://169.254.169.254/latest/meta-data/'],
            ]),
        ]);

        $response = $this->handle($client, self::ALLOWED);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        // The second hop was never dispatched.
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testGivesUpAfterTooManyRedirects(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('', [
            'http_code'        => 302,
            'response_headers' => ['location' => 'https://books.google.com/next'],
        ]));

        $response = $this->handle($client, self::ALLOWED);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame(4, $client->getRequestsCount());
    }

    public function testTreatsARedirectWithoutLocationAsNotFound(): void
    {
        $client   = new MockHttpClient(new MockResponse('', ['http_code' => 302]));
        $response = $this->handle($client, self::ALLOWED);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRejectsANonImageContentType(): void
    {
        $client = new MockHttpClient(new MockResponse('<html>', [
            'response_headers' => ['content-type' => 'text/html'],
        ]));

        self::assertSame(Response::HTTP_NOT_FOUND, $this->handle($client, self::ALLOWED)->getStatusCode());
    }

    public function testRejectsANonOkStatus(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'http_code'        => 500,
            'response_headers' => ['content-type' => 'image/jpeg'],
        ]));

        self::assertSame(Response::HTTP_NOT_FOUND, $this->handle($client, self::ALLOWED)->getStatusCode());
    }

    public function testServedImagesCannotRunAsADocument(): void
    {
        $client   = new MockHttpClient(new MockResponse('IMAGE-BYTES', [
            'response_headers' => ['content-type' => 'image/png; charset=binary'],
        ]));
        $response = $this->handle($client, self::ALLOWED);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertSame("default-src 'none'; sandbox", $response->headers->get('Content-Security-Policy'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /** An SVG served from our origin could run script next to the SPA. */
    public function testRefusesAnSvgImage(): void
    {
        $client = new MockHttpClient(new MockResponse('<svg onload="alert(1)"/>', [
            'response_headers' => ['content-type' => 'image/svg+xml'],
        ]));

        self::assertSame(Response::HTTP_NOT_FOUND, $this->handle($client, self::ALLOWED)->getStatusCode());
    }

    public function testAMissingCoverIsCachedByTheBrowser(): void
    {
        $client   = new MockHttpClient(new MockResponse('', ['http_code' => 404]));
        $response = $this->handle($client, self::ALLOWED);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('86400', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testServesABnfCoverWithTheBnfReferer(): void
    {
        $sentHeaders = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentHeaders): MockResponse {
            $sentHeaders = $options['headers'];

            return new MockResponse(str_repeat('j', 4000), ['response_headers' => ['content-type' => 'image/jpeg']]);
        });

        $response = $this->handle($client, self::BNF);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertContains('Referer: https://catalogue.bnf.fr/', $sentHeaders);
    }

    /** The BnF answers "no cover" with a tiny placeholder: it must read as missing. */
    public function testReportsTheBnfPlaceholderAsMissing(): void
    {
        $client = new MockHttpClient(new MockResponse(str_repeat('p', 800), [
            'response_headers' => ['content-type' => 'image/gif'],
        ]));

        self::assertSame(Response::HTTP_NOT_FOUND, $this->handle($client, self::BNF)->getStatusCode());
    }

    public function testReportsAnEmptyImageAsMissing(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['response_headers' => ['content-type' => 'image/jpeg']]));

        self::assertSame(Response::HTTP_NOT_FOUND, $this->handle($client, self::ALLOWED)->getStatusCode());
    }

    /** An oversized body must not be buffered into the response. */
    public function testRejectsABodyOverTheSizeCap(): void
    {
        $oneMebibyte = str_repeat('a', 1024 * 1024);
        $client      = new MockHttpClient(new MockResponse(
            array_fill(0, 9, $oneMebibyte),
            ['response_headers' => ['content-type' => 'image/jpeg']],
        ));

        self::assertSame(Response::HTTP_NOT_FOUND, $this->handle($client, self::ALLOWED)->getStatusCode());
    }

    public function testRefusesAClientIpPastItsQuotaBeforeAnyFetch(): void
    {
        $client     = new MockHttpClient();
        $controller = new CoverProxyController(
            $client,
            new NullLogger(),
            new CacheRateLimiter(new InMemoryRateLimitCounterStore()),
        );

        for ($call = 0; $call < CoverProxyController::REQUESTS_PER_IP; $call++) {
            $controller($this->request('https://evil.example/x.jpg', '203.0.113.7'));
        }

        try {
            $controller($this->request(self::ALLOWED, '203.0.113.7'));
            self::fail('The call past the quota should be refused.');
        } catch (RateLimitExceededException) {
            self::assertSame(0, $client->getRequestsCount());
        }
    }

    public function testCountsEachClientIpApart(): void
    {
        $controller = new CoverProxyController(
            new MockHttpClient(),
            new NullLogger(),
            new CacheRateLimiter(new InMemoryRateLimitCounterStore()),
        );

        for ($call = 0; $call < CoverProxyController::REQUESTS_PER_IP; $call++) {
            $controller($this->request('https://evil.example/x.jpg', '203.0.113.8'));
        }

        $response = $controller($this->request('https://evil.example/x.jpg', '203.0.113.9'));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function handle(MockHttpClient $client, string $url): Response
    {
        $controller = new CoverProxyController(
            $client,
            new NullLogger(),
            new CacheRateLimiter(new InMemoryRateLimitCounterStore()),
        );

        return $controller($this->request($url, '198.51.100.1'));
    }

    private function request(string $url, string $clientIp): Request
    {
        return Request::create('/proxy/cover', 'GET', ['url' => $url], server: ['REMOTE_ADDR' => $clientIp]);
    }
}
