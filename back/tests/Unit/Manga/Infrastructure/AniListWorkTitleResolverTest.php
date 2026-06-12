<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Infrastructure\ExternalApi\AniListWorkTitleResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AniListWorkTitleResolverTest extends TestCase
{
    private const string BASE_URL = 'https://graphql.anilist.co';

    private function makeResolver(MockHttpClient $httpClient): AniListWorkTitleResolver
    {
        return new AniListWorkTitleResolver($httpClient, self::BASE_URL, new NullLogger());
    }

    private function responseJson(): string
    {
        return (string) json_encode([
            'data' => [
                'Media' => [
                    'title' => [
                        'romaji'  => 'Shingeki no Kyojin',
                        'english' => 'Attack on Titan',
                        'native'  => '進撃の巨人',
                    ],
                    'synonyms' => ["L'Attaque des Titans"],
                ],
            ],
        ]);
    }

    public function testResolvesNativeTitleForJapanese(): void
    {
        $httpClient = new MockHttpClient([new MockResponse($this->responseJson(), ['http_code' => 200])]);

        $title = $this->makeResolver($httpClient)->resolve("L'Attaque des titans", 'ja');

        $this->assertSame('進撃の巨人', $title);
    }

    public function testResolvesEnglishTitleForOtherForeignMarkets(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse(
                (string) json_encode([
                    'data' => ['Media' => ['title' => [
                        'romaji'  => 'Shingeki no Kyojin',
                        'english' => 'Attack on Titan',
                        'native'  => '進撃の巨人',
                    ]]],
                ]),
                ['http_code' => 200],
            ),
        );

        $resolver = $this->makeResolver($httpClient);

        $this->assertSame('Attack on Titan', $resolver->resolve("L'Attaque des titans", 'en'));
        $this->assertSame('Attack on Titan', $resolver->resolve("L'Attaque des titans", 'de'));
        $this->assertSame('Attack on Titan', $resolver->resolve("L'Attaque des titans", 'it'));
    }

    public function testReturnsNullForFrenchWithoutRequest(): void
    {
        $requestCount = 0;
        $httpClient   = new MockHttpClient(function () use (&$requestCount): MockResponse {
            $requestCount++;

            return new MockResponse('{}', ['http_code' => 200]);
        });

        $title = $this->makeResolver($httpClient)->resolve("L'Attaque des titans", 'fr');

        $this->assertNull($title);
        $this->assertSame(0, $requestCount);
    }

    public function testReturnsNullForNullLanguage(): void
    {
        $resolver = $this->makeResolver(new MockHttpClient([]));

        $this->assertNull($resolver->resolve('Berserk', null));
    }

    public function testFallsBackToRomajiWhenEnglishMissing(): void
    {
        $json = (string) json_encode([
            'data' => ['Media' => ['title' => [
                'romaji'  => 'Vinland Saga',
                'english' => null,
                'native'  => 'ヴィンランド・サガ',
            ]]],
        ]);
        $httpClient = new MockHttpClient([new MockResponse($json, ['http_code' => 200])]);

        $this->assertSame('Vinland Saga', $this->makeResolver($httpClient)->resolve('Vinland Saga', 'de'));
    }

    public function testReturnsNullWhenNoMediaFound(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['data' => ['Media' => null]]), ['http_code' => 200]),
        ]);

        $this->assertNull($this->makeResolver($httpClient)->resolve('Unknown work', 'ja'));
    }

    public function testReturnsNullOnNonOkResponse(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 500])]);

        $this->assertNull($this->makeResolver($httpClient)->resolve('Berserk', 'ja'));
    }
}
