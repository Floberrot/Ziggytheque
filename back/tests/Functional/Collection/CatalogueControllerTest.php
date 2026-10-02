<?php

declare(strict_types=1);

namespace App\Tests\Functional\Collection;

use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Isbn;
use App\Tests\Doubles\Manga\InMemoryCatalogue;
use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use Symfony\Component\HttpFoundation\Response;

final class CatalogueControllerTest extends AbstractApiTestCase
{
    private const string PRESTIGE_TOME_2 = '9782344036082';

    protected function setUp(): void
    {
        parent::setUp();
        // Keep the kernel between requests so the catalogue seeded here is the one
        // the handlers resolve.
        $this->client->disableReboot();

        /** @var InMemoryCatalogue $catalogue */
        $catalogue = static::getContainer()->get(InMemoryCatalogue::class);
        $catalogue->reset();
        $catalogue->add(
            $this->record(1, null, '9782723425483'),
            $this->record(2, null, '9782723425490'),
            $this->record(1, 'Prestige', '9782344036075'),
            $this->record(2, 'Prestige', self::PRESTIGE_TOME_2),
            $this->record(3, 'Prestige', null),
        );
    }

    private function record(int $number, ?string $specialEdition, ?string $isbn): CatalogueRecord
    {
        return new CatalogueRecord(
            workTitle: 'Berserk',
            headQualifier: $specialEdition,
            volumeNumber: $number,
            trailingQualifier: null,
            publisher: 'Glénat (Grenoble)',
            author: 'Kentaro Miura',
            isbn: Isbn::tryFrom($isbn),
            coverUrl: 'https://catalogue.bnf.fr/couverture?appName=NE&idArk=ark:/12148/cb' . $number,
            source: 'bnf',
        );
    }

    /** @return array<string, mixed> */
    private function prestigePayload(array $ownedNumbers = [2]): array
    {
        return [
            'workTitle'      => 'Berserk',
            'publisher'      => 'Glénat',
            'specialEdition' => 'Prestige',
            'author'         => 'Kentaro Miura',
            'coverUrl'       => 'https://catalogue.bnf.fr/couverture?appName=NE&idArk=ark:/12148/cb1',
            'volumeCount'    => 3,
            'volumes'        => [
                ['number' => 1, 'isbn' => '9782344036075', 'coverUrl' => null],
                ['number' => 2, 'isbn' => self::PRESTIGE_TOME_2],
            ],
            'ownedNumbers'   => $ownedNumbers,
        ];
    }

    private function requestAs(string $token, string $method, string $url, array $body = []): Response
    {
        $this->client->request(
            $method,
            $url,
            [],
            [],
            [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_ACCEPT'        => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            $body !== [] ? (string) json_encode($body) : '',
        );

        return $this->client->getResponse();
    }

    // ── GET /api/catalogue/search ────────────────────────────────────────────

    public function testSearchRequiresAuth(): void
    {
        $this->assertSame(401, $this->jsonRequest('GET', '/api/catalogue/search?q=berserk', auth: false)->getStatusCode());
    }

    public function testSearchByTitleReturnsEverySeriesAndTheRequestedTome(): void
    {
        $data = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/catalogue/search?q=berserk%202'));

        $this->assertSame('berserk', $data['query']);
        $this->assertSame(2, $data['requestedVolume']);
        $this->assertCount(2, $data['editions']);

        [$standard, $prestige] = $data['editions'];
        $this->assertSame('Glénat', $standard['publisher']);
        $this->assertNull($standard['specialEdition']);
        $this->assertSame(2, $standard['volumeCount']);
        $this->assertNull($standard['collection']);
        $this->assertSame('Prestige', $prestige['specialEdition']);
        $this->assertSame(3, $prestige['volumeCount']);
        $this->assertSame(['number' => 2, 'isbn' => self::PRESTIGE_TOME_2, 'coverUrl' => 'https://catalogue.bnf.fr/couverture?appName=NE&idArk=ark:/12148/cb2'], $prestige['volumes'][1]);
    }

    public function testSearchShowsWhatTheUserAlreadyOwns(): void
    {
        $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/catalogue/add', $this->prestigePayload([1, 3])));

        $data = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/catalogue/search?q=berserk'));

        $this->assertNull($data['editions'][0]['collection']);
        $this->assertSame([1, 3], $data['editions'][1]['collection']['ownedNumbers']);
    }

    public function testSearchByAuthor(): void
    {
        $data = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/catalogue/search?mode=author&q=miura'));

        $this->assertNull($data['requestedVolume']);
        $this->assertCount(2, $data['editions']);
    }

    public function testSearchByIsbnPlacesTheBookInItsSeries(): void
    {
        $data = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/catalogue/search?mode=isbn&q=978-2-344-03608-2'));

        $this->assertSame(2, $data['requestedVolume']);
        $this->assertCount(1, $data['editions']);
        $this->assertSame('Prestige', $data['editions'][0]['specialEdition']);
    }

    public function testSearchByUnknownIsbnReturns404(): void
    {
        $this->assertJsonStatus(404, $this->jsonRequest('GET', '/api/catalogue/search?mode=isbn&q=9782811645632'));
    }

    public function testSearchByInvalidIsbnReturns422(): void
    {
        $this->assertJsonStatus(422, $this->jsonRequest('GET', '/api/catalogue/search?mode=isbn&q=12345678'));
    }

    public function testSearchWithATooShortQueryReturns422(): void
    {
        $this->assertJsonStatus(422, $this->jsonRequest('GET', '/api/catalogue/search?q=b'));
    }

    public function testSearchWithNoMatchReturnsNoEdition(): void
    {
        $data = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/catalogue/search?q=naruto'));

        $this->assertSame([], $data['editions']);
    }

    public function testSearchIsRateLimitedPerUser(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->assertSame(200, $this->jsonRequest('GET', '/api/catalogue/search?q=naruto')->getStatusCode());
        }

        $this->assertJsonStatus(429, $this->jsonRequest('GET', '/api/catalogue/search?q=naruto'));
    }

    // ── GET /api/catalogue/edition ───────────────────────────────────────────

    public function testEditionRequiresAuth(): void
    {
        $this->assertSame(401, $this->jsonRequest('GET', '/api/catalogue/edition?workTitle=Berserk', auth: false)->getStatusCode());
    }

    public function testEditionReturnsEveryTomeOfTheSeries(): void
    {
        $data = $this->assertJsonStatus(200, $this->jsonRequest(
            'GET',
            '/api/catalogue/edition?' . http_build_query(['workTitle' => 'Berserk', 'publisher' => 'Glénat', 'specialEdition' => 'Prestige']),
        ));

        $this->assertSame('Prestige', $data['specialEdition']);
        $this->assertSame([1, 2, 3], array_column($data['volumes'], 'number'));
        $this->assertNull($data['collection']);
    }

    public function testEditionOfAnUnknownSeriesReturns404(): void
    {
        $this->assertJsonStatus(404, $this->jsonRequest('GET', '/api/catalogue/edition?workTitle=Berserk&publisher=Kana'));
    }

    public function testEditionWithoutWorkTitleReturns422(): void
    {
        $this->assertJsonStatus(422, $this->jsonRequest('GET', '/api/catalogue/edition'));
    }

    // ── POST /api/catalogue/add ──────────────────────────────────────────────

    public function testAddRequiresAuth(): void
    {
        $this->assertSame(401, $this->jsonRequest('POST', '/api/catalogue/add', $this->prestigePayload(), auth: false)->getStatusCode());
    }

    public function testAddCreatesTheSeriesWithAllItsTomesAndOwnsThePickedOnes(): void
    {
        $data = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/catalogue/add', $this->prestigePayload([2])));

        $this->assertTrue($data['seriesCreated']);
        $this->assertTrue($data['entryCreated']);
        $this->assertSame(3, $data['totalVolumes']);
        $this->assertSame([2], $data['addedNumbers']);
        $this->assertArrayHasKey('2', $data['volumeEntryIds']);

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/collection/' . $data['collectionEntryId']));
        $this->assertSame('Berserk', $detail['manga']['title']);
        $this->assertSame('Glénat', $detail['manga']['edition']);
        $this->assertSame('Prestige', $detail['manga']['specialEdition']);
        $this->assertSame(1, $detail['ownedCount']);
        $this->assertSame('in_progress', $detail['readingStatus']);
        $this->assertSame([false, true, false], array_column($detail['volumes'], 'isOwned'));
        $this->assertSame(self::PRESTIGE_TOME_2, $detail['volumes'][1]['isbn']);
    }

    public function testAddingToAnExistingEntryReturns200AndReportsOwnedTomes(): void
    {
        $first  = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/catalogue/add', $this->prestigePayload([2])));
        $second = $this->assertJsonStatus(200, $this->jsonRequest('POST', '/api/catalogue/add', $this->prestigePayload([2, 3])));

        $this->assertSame($first['collectionEntryId'], $second['collectionEntryId']);
        $this->assertFalse($second['seriesCreated']);
        $this->assertFalse($second['entryCreated']);
        $this->assertSame([3], $second['addedNumbers']);
        $this->assertSame([2], $second['alreadyOwnedNumbers']);
    }

    public function testAnotherUserReusesTheSeriesButGetsTheirOwnEntry(): void
    {
        $mine = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/catalogue/add', $this->prestigePayload([2])));

        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'catalogue-reader@test.local');
        $otherToken = $this->tokenForUser('catalogue-reader@test.local');

        $searchAsOther = $this->assertJsonStatus(200, $this->requestAs($otherToken, 'GET', '/api/catalogue/search?q=berserk'));
        $this->assertNull($searchAsOther['editions'][1]['collection']);

        $theirs = $this->assertJsonStatus(201, $this->requestAs($otherToken, 'POST', '/api/catalogue/add', $this->prestigePayload([1])));
        $this->assertFalse($theirs['seriesCreated']);
        $this->assertSame($mine['mangaId'], $theirs['mangaId']);
        $this->assertNotSame($mine['collectionEntryId'], $theirs['collectionEntryId']);
    }

    public function testAddWithoutOwnedTomesStillStartsTheSeries(): void
    {
        $data = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/catalogue/add', $this->prestigePayload([])));

        $this->assertSame([], $data['addedNumbers']);
        $this->assertSame(3, $data['totalVolumes']);
    }

    public function testAddRejectsAMissingWorkTitle(): void
    {
        $payload = $this->prestigePayload();
        $payload['workTitle'] = '';

        $this->assertSame(422, $this->jsonRequest('POST', '/api/catalogue/add', $payload)->getStatusCode());
    }

    public function testAddRejectsANonHttpsCover(): void
    {
        $payload = $this->prestigePayload();
        $payload['coverUrl'] = 'http://insecure.example/cover.jpg';

        $this->assertSame(422, $this->jsonRequest('POST', '/api/catalogue/add', $payload)->getStatusCode());
    }

    public function testAddRejectsInvalidTomeNumbers(): void
    {
        $payload = $this->prestigePayload([0]);

        $this->assertSame(422, $this->jsonRequest('POST', '/api/catalogue/add', $payload)->getStatusCode());
    }

    public function testAddRejectsAVolumeWithoutNumber(): void
    {
        $payload = $this->prestigePayload();
        $payload['volumes'] = [['isbn' => '9782344036075']];

        $this->assertSame(422, $this->jsonRequest('POST', '/api/catalogue/add', $payload)->getStatusCode());
    }

    // ── POST /api/catalogue/scan ─────────────────────────────────────────────

    public function testScanRequiresAuth(): void
    {
        $this->assertSame(401, $this->jsonRequest('POST', '/api/catalogue/scan', ['isbn' => self::PRESTIGE_TOME_2], auth: false)->getStatusCode());
    }

    public function testScanAddsTheTomeAndCreatesItsSeries(): void
    {
        $data = $this->assertJsonStatus(200, $this->jsonRequest('POST', '/api/catalogue/scan', ['isbn' => '978-2-344-03608-2']));

        $this->assertSame(2, $data['volumeNumber']);
        $this->assertFalse($data['alreadyOwned']);
        $this->assertSame('Berserk', $data['edition']['workTitle']);
        $this->assertSame('Prestige', $data['edition']['specialEdition']);
        $this->assertTrue($data['registration']['seriesCreated']);
        $this->assertSame(3, $data['registration']['totalVolumes']);

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/collection/' . $data['registration']['collectionEntryId']));
        $this->assertSame([false, true, false], array_column($detail['volumes'], 'isOwned'));
    }

    public function testScanningTheSameTomeTwiceReportsItAsAlreadyOwned(): void
    {
        $this->assertJsonStatus(200, $this->jsonRequest('POST', '/api/catalogue/scan', ['isbn' => self::PRESTIGE_TOME_2]));

        $data = $this->assertJsonStatus(200, $this->jsonRequest('POST', '/api/catalogue/scan', ['isbn' => self::PRESTIGE_TOME_2]));

        $this->assertTrue($data['alreadyOwned']);
        $this->assertFalse($data['registration']['seriesCreated']);
    }

    public function testScanOfAnUnknownIsbnReturns404(): void
    {
        $this->assertJsonStatus(404, $this->jsonRequest('POST', '/api/catalogue/scan', ['isbn' => '9782811645632']));
    }

    /** A catalogue outage is no "no French edition": the user must retry. */
    public function testScanDuringACatalogueOutageReturns503(): void
    {
        $this->catalogue()->simulateOutage();

        $this->assertJsonStatus(503, $this->jsonRequest('POST', '/api/catalogue/scan', ['isbn' => self::PRESTIGE_TOME_2]));
    }

    public function testSearchDuringACatalogueOutageReturns503(): void
    {
        $this->catalogue()->simulateOutage();

        $this->assertJsonStatus(503, $this->jsonRequest('GET', '/api/catalogue/search?q=berserk'));
    }

    private function catalogue(): InMemoryCatalogue
    {
        /** @var InMemoryCatalogue $catalogue */
        $catalogue = static::getContainer()->get(InMemoryCatalogue::class);

        return $catalogue;
    }

    public function testScanOfAnInvalidIsbnReturns422(): void
    {
        $this->assertJsonStatus(422, $this->jsonRequest('POST', '/api/catalogue/scan', ['isbn' => 'not-an-isbn']));
    }

    public function testScanWithoutIsbnReturns422(): void
    {
        $this->assertSame(422, $this->jsonRequest('POST', '/api/catalogue/scan', ['isbn' => ''])->getStatusCode());
    }
}
