<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\HandTypedSeriesTrait;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cover lookups, volume search, summary translation and price checks all call outside
 * services: they share one per-account quota (30 a minute), the cover batch its own.
 */
final class ExternalLookupQuotaTest extends AbstractApiTestCase
{
    use HandTypedSeriesTrait;

    private const int EXTERNAL_LOOKUP_LIMIT = 30;
    private const int COVER_BATCH_LIMIT     = 5;

    private string $mangaId;
    private string $volumeId;

    protected function setUp(): void
    {
        parent::setUp();

        ['mangaId' => $this->mangaId, 'volumeIds' => [$this->volumeId]] = $this->collectHandTypedSeries('Quota Series');
    }

    /** @return iterable<string, array{string, string, array<string, mixed>}> */
    public static function externalLookups(): iterable
    {
        yield 'cover by ISBN' => ['GET', '/api/manga/cover-by-isbn?isbn=9782723492539', []];
        yield 'volume search' => ['GET', '/api/manga/volume-search?q=Berserk', []];
        yield 'summary translation' => ['POST', '/api/manga/translate-summary', ['text' => 'A pirate story.']];
        yield 'volume prices' => ['GET', '/api/manga/{manga}/volumes/{volume}/prices', []];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('externalLookups')]
    public function testEachLookupStopsOnceTheSharedQuotaIsSpent(string $method, string $url, array $body): void
    {
        $this->spendTheExternalLookupQuota();

        $this->assertJsonStatus(429, $this->jsonRequest($method, $this->resolve($url), $body));
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('externalLookups')]
    public function testEachLookupAnswersWithinTheQuota(string $method, string $url, array $body): void
    {
        $this->assertSame(200, $this->jsonRequest($method, $this->resolve($url), $body)->getStatusCode());
    }

    public function testTheQuotaIsPerAccount(): void
    {
        $this->spendTheExternalLookupQuota();

        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'other@test.local');
        $this->token = $this->tokenForUser('other@test.local');

        $this->assertSame(200, $this->jsonRequest('GET', '/api/manga/volume-search?q=Berserk')->getStatusCode());
    }

    public function testCoverBatchesHaveTheirOwnQuota(): void
    {
        for ($attempt = 0; $attempt < self::COVER_BATCH_LIMIT; $attempt++) {
            $this->assertSame(202, $this->startCoverBatch()->getStatusCode());
        }

        $this->assertJsonStatus(429, $this->startCoverBatch());
        // The lookup quota is untouched by the batches.
        $this->assertSame(200, $this->jsonRequest('GET', '/api/manga/volume-search?q=Berserk')->getStatusCode());
    }

    private function spendTheExternalLookupQuota(): void
    {
        for ($attempt = 0; $attempt < self::EXTERNAL_LOOKUP_LIMIT; $attempt++) {
            $this->assertSame(200, $this->jsonRequest('GET', '/api/manga/volume-search?q=Berserk')->getStatusCode());
        }
    }

    private function startCoverBatch(): Response
    {
        return $this->jsonRequest('POST', '/api/manga/' . $this->mangaId . '/auto-covers', ['force' => false]);
    }

    private function resolve(string $url): string
    {
        return str_replace(['{manga}', '{volume}'], [$this->mangaId, $this->volumeId], $url);
    }
}
