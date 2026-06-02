<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;

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
}
