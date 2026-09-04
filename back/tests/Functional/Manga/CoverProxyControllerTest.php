<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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
        yield 'plain http google'       => ['http://books.google.com/cover.jpg'];
        yield 'cloud metadata'          => ['http://169.254.169.254/latest/meta-data/'];
        yield 'loopback'                => ['http://127.0.0.1:8000/api/stats'];
        yield 'internal service'        => ['http://back:80/api/me'];
        yield 'file scheme'             => ['file:///etc/passwd'];
        yield 'no scheme'               => ['books.google.com/cover.jpg'];
    }
}
