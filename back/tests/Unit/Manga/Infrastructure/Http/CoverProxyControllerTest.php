<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Http;

use App\Manga\Infrastructure\Http\CoverProxyController;
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

    private function handle(MockHttpClient $client, string $url): Response
    {
        $controller = new CoverProxyController($client, new NullLogger());

        return $controller(Request::create('/proxy/cover', 'GET', ['url' => $url]));
    }
}
