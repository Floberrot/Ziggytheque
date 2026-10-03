<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Manga\Infrastructure\Http\CoverProxyController;
use App\Tests\Doubles\Manga\FakeCoverImageResponseFactory;
use App\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class CoverProxyControllerTest extends AbstractApiTestCase
{
    public function testRejectsEmptyUrl(): void
    {
        $response = $this->jsonRequest('GET', '/proxy/cover', auth: false);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testRejectsHostOutsideAllowlist(): void
    {
        $response = $this->jsonRequest('GET', '/proxy/cover?url=' . urlencode('https://evil.example/cover.jpg'), auth: false);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testRejectsNonHttpsMangadex(): void
    {
        $response = $this->jsonRequest('GET', '/proxy/cover?url=' . urlencode('http://uploads.mangadex.org/covers/a/b.jpg'), auth: false);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testRejectsLookalikeMangadexHost(): void
    {
        $response = $this->jsonRequest('GET', '/proxy/cover?url=' . urlencode('https://uploads.mangadex.org.evil.example/x.jpg'), auth: false);
        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * The allowlist is matched on the parsed host, so none of these reach the
     * HTTP client. Matching a prefix of the URL string instead would let
     * `books.google.com.evil.example` and friends through.
     */
    #[DataProvider('rejectedUrls')]
    public function testRejectsUrlOutsideTheHostAllowlist(string $url): void
    {
        $response = $this->jsonRequest('GET', '/proxy/cover?url=' . urlencode($url), auth: false);
        $this->assertSame(400, $response->getStatusCode());
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedUrls(): iterable
    {
        yield 'google suffix lookalike' => ['https://books.google.com.evil.example/cover.jpg'];
        yield 'google prefix lookalike' => ['https://books.googleevil.example/cover.jpg'];
        yield 'google userinfo'         => ['https://books.google.com@evil.example/cover.jpg'];
        yield 'mangadex userinfo'       => ['https://uploads.mangadex.org@evil.example/x.jpg'];
        yield 'bnf suffix lookalike'    => ['https://catalogue.bnf.fr.evil.example/couverture'];
        yield 'plain http bnf'          => ['http://catalogue.bnf.fr/couverture?idArk=ark:/12148/cb1'];
        yield 'plain http google'       => ['http://books.google.com/cover.jpg'];
        yield 'cloud metadata'          => ['http://169.254.169.254/latest/meta-data/'];
        yield 'loopback'                => ['http://127.0.0.1:8000/api/stats'];
        yield 'internal service'        => ['http://back:80/api/me'];
        yield 'file scheme'             => ['file:///etc/passwd'];
        yield 'no scheme'               => ['books.google.com/cover.jpg'];
    }

    /** A collection grid loads a hundred covers or more at once: all of them are served. */
    public function testServesAWholeCollectionGridOfCovers(): void
    {
        for ($cover = 1; $cover <= 150; $cover++) {
            $response = $this->requestCover('https://books.google.com/books/content?id=' . $cover, '198.51.100.10');

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
            $this->assertSame(FakeCoverImageResponseFactory::IMAGE_BYTES, strlen((string) $response->getContent()));
        }
    }

    public function testRefusesAClientIpPastItsQuotaButNotTheOthers(): void
    {
        // A rejected URL is answered without any fetch, and still counts.
        for ($call = 0; $call < CoverProxyController::REQUESTS_PER_IP; $call++) {
            $this->assertSame(400, $this->requestCover('https://evil.example/x.jpg', '198.51.100.20')->getStatusCode());
        }

        $refused = $this->requestCover('https://uploads.mangadex.org/covers/a/b.jpg', '198.51.100.20');
        $this->assertJsonStatus(429, $refused);

        $otherClient = $this->requestCover('https://uploads.mangadex.org/covers/a/b.jpg', '198.51.100.21');
        $this->assertSame(200, $otherClient->getStatusCode());
    }

    private function requestCover(string $url, string $clientIp): Response
    {
        // Hundreds of requests: no need to boot a fresh kernel for each of them.
        $this->client->disableReboot();
        $this->client->request('GET', '/proxy/cover?url=' . urlencode($url), server: ['REMOTE_ADDR' => $clientIp]);

        return $this->client->getResponse();
    }
}
