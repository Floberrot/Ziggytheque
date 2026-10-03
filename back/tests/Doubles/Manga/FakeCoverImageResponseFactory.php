<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Manga;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Upstream of the cover proxy in the test env: every URL answers with the same small
 * JPEG, so proxied covers are served without reaching the internet.
 */
final class FakeCoverImageResponseFactory
{
    /** Above the BnF placeholder threshold (2000 bytes): a real-looking cover. */
    public const int IMAGE_BYTES = 4096;

    /** @param array<string, mixed> $options */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        return new MockResponse(
            "\xFF\xD8\xFF\xE0" . str_repeat('c', self::IMAGE_BYTES - 4),
            ['response_headers' => ['content-type' => 'image/jpeg']],
        );
    }
}
