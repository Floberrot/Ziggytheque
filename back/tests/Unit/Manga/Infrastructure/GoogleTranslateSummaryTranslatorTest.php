<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Infrastructure\Translation\GoogleTranslateSummaryTranslator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleTranslateSummaryTranslatorTest extends TestCase
{
    private function makeTranslator(MockHttpClient $httpClient): GoogleTranslateSummaryTranslator
    {
        return new GoogleTranslateSummaryTranslator($httpClient, new NullLogger(), 'https://translate.googleapis.com');
    }

    public function testTranslateSendsLanguagesAndTextAndJoinsSegments(): void
    {
        $capturedUrl = null;
        $httpClient  = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            // Google's nested shape: [[[segmentFr, segmentEn, ...], ...], ...]
            return new MockResponse((string) json_encode([
                [
                    ['Bonjour le monde. ', 'Hello world. '],
                    ['Une histoire de pirates.', 'A pirate story.'],
                ],
                null,
                'en',
            ]));
        });

        $result = $this->makeTranslator($httpClient)->translate('Hello world. A pirate story.', 'en', 'fr');

        $this->assertSame('Bonjour le monde. Une histoire de pirates.', $result);
        $this->assertNotNull($capturedUrl);
        $this->assertStringContainsString('/translate_a/single', $capturedUrl);
        $this->assertStringContainsString('client=gtx', $capturedUrl);
        $this->assertStringContainsString('sl=en', $capturedUrl);
        $this->assertStringContainsString('tl=fr', $capturedUrl);
    }

    public function testTranslateReturnsBlankTextUnchangedWithoutCallingApi(): void
    {
        $called     = false;
        $httpClient = new MockHttpClient(function () use (&$called): MockResponse {
            $called = true;

            return new MockResponse('[]');
        });

        $result = $this->makeTranslator($httpClient)->translate('   ', 'en', 'fr');

        $this->assertSame('   ', $result);
        $this->assertFalse($called);
    }

    public function testTranslateFallsBackToOriginalWhenResponseHasNoTranslation(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode([null, null, 'en'])),
        ]);

        $result = $this->makeTranslator($httpClient)->translate('Untranslatable', 'en', 'fr');

        $this->assertSame('Untranslatable', $result);
    }
}
