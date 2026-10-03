<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\HandTypedSeriesTrait;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every account has its own copy of each series: another account never reads nor
 * changes it (404, as if it did not exist), and what one corrects stays in its copy.
 */
final class PersonalSeriesTest extends AbstractApiTestCase
{
    use HandTypedSeriesTrait;

    private string $mangaId;
    private string $entryId;
    private string $volumeId;
    private string $intruderToken;

    protected function setUp(): void
    {
        parent::setUp();

        // The setUp admin types a 2-tome series by hand and collects it.
        $series = $this->collectHandTypedSeries('Private Series', 2);
        $this->mangaId  = $series['mangaId'];
        $this->entryId  = $series['entryId'];
        $this->volumeId = $series['volumeIds'][0];

        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'intruder@test.local');
        $this->intruderToken = $this->tokenForUser('intruder@test.local');
    }

    /** @return iterable<string, array{string, string, array<string, mixed>}> */
    public static function seriesRoutes(): iterable
    {
        yield 'rename the series' => ['PATCH', '/api/manga/{manga}', ['title' => 'Hijacked']];
        yield 'change a tome' => ['PATCH', '/api/manga/{manga}/volumes/{volume}', ['price' => 99, 'isbn' => '9782344036075']];
        yield 'fetch the covers' => ['POST', '/api/manga/{manga}/auto-covers', ['force' => true]];
        yield 'look up the prices' => ['GET', '/api/manga/{manga}/volumes/{volume}/prices', []];
        yield 'put it in a collection' => ['POST', '/api/collection', ['mangaId' => '{manga}']];
        yield 'scan a tome into it' => ['POST', '/api/scan/sessions', ['mangaId' => '{manga}', 'volumeId' => '{volume}']];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('seriesRoutes')]
    public function testAnotherAccountCannotReachTheSeries(string $method, string $url, array $body): void
    {
        $response = $this->requestAs($this->intruderToken, $method, $this->resolve($url), $this->resolveBody($body));

        $this->assertJsonStatus(404, $response);

        // The owner's series is untouched.
        $detail = $this->collectionDetail($this->entryId);
        $this->assertSame('Private Series', $detail['manga']['title']);
        $this->assertCount(2, $detail['volumes']);
        $this->assertNull($detail['volumes'][0]['price']);
        $this->assertNull($detail['volumes'][0]['isbn']);
    }

    public function testASeriesTypedByHandBelongsToWhoeverTypedIt(): void
    {
        $theirs = $this->assertJsonStatus(201, $this->requestAs($this->intruderToken, 'POST', '/api/manga', [
            'title' => 'Intruder Series', 'language' => 'fr',
        ]));

        $this->assertJsonStatus(404, $this->jsonRequest('POST', '/api/collection', ['mangaId' => $theirs['id']]));
        $this->assertJsonStatus(201, $this->requestAs($this->intruderToken, 'POST', '/api/collection', ['mangaId' => $theirs['id']]));
    }

    public function testCorrectionsStayInTheCopyOfWhoeverMadeThem(): void
    {
        $mine = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/catalogue/add', $this->cataloguePayload()));
        $theirs = $this->assertJsonStatus(201, $this->requestAs($this->intruderToken, 'POST', '/api/catalogue/add', $this->cataloguePayload()));

        $myVolumeId = (string) $this->collectionDetail($mine['collectionEntryId'])['volumes'][0]['volumeId'];
        $this->assertSame(204, $this->jsonRequest('PATCH', '/api/manga/' . $mine['mangaId'], [
            'title' => 'Berserk (my title)', 'coverUrl' => 'https://covers.example/mine.jpg',
        ])->getStatusCode());
        $this->assertSame(204, $this->jsonRequest('PATCH', '/api/manga/' . $mine['mangaId'] . '/volumes/' . $myVolumeId, [
            'price' => 12.5, 'coverUrl' => 'https://covers.example/mine-1.jpg',
        ])->getStatusCode());
        $this->assertSame(204, $this->jsonRequest('PATCH', '/api/collection/' . $mine['collectionEntryId'] . '/batch-price', [
            'price' => 8,
        ])->getStatusCode());

        $theirEntry = $this->assertJsonStatus(200, $this->requestAs($this->intruderToken, 'GET', '/api/collection/' . $theirs['collectionEntryId']));
        $this->assertSame('Berserk', $theirEntry['manga']['title']);
        $this->assertSame('https://catalogue.bnf.fr/couverture?appName=NE&idArk=ark:/12148/cb1', $theirEntry['manga']['coverUrl']);
        $this->assertSame([null, null, null], array_column($theirEntry['volumes'], 'price'));
        $this->assertNull($theirEntry['volumes'][0]['coverUrl']);
    }

    public function testRemovingASeriesFromTheCollectionDeletesOnlyOnesOwnCopy(): void
    {
        $mine = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/catalogue/add', $this->cataloguePayload()));
        $theirs = $this->assertJsonStatus(201, $this->requestAs($this->intruderToken, 'POST', '/api/catalogue/add', $this->cataloguePayload()));

        $this->assertSame(204, $this->jsonRequest('DELETE', '/api/collection/' . $mine['collectionEntryId'])->getStatusCode());

        // My copy is gone (nothing left to rename), theirs keeps its three tomes.
        $this->assertJsonStatus(404, $this->jsonRequest('PATCH', '/api/manga/' . $mine['mangaId'], ['title' => 'Gone']));
        $theirEntry = $this->assertJsonStatus(200, $this->requestAs($this->intruderToken, 'GET', '/api/collection/' . $theirs['collectionEntryId']));
        $this->assertSame([true, false, false], array_column($theirEntry['volumes'], 'isOwned'));

        // Added again, the series starts over as a fresh copy.
        $again = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/catalogue/add', $this->cataloguePayload()));
        $this->assertTrue($again['seriesCreated']);
    }

    /** @return array<string, mixed> */
    private function cataloguePayload(): array
    {
        return [
            'workTitle'      => 'Berserk',
            'publisher'      => 'Glénat',
            'specialEdition' => 'Prestige',
            'author'         => 'Kentaro Miura',
            'coverUrl'       => 'https://catalogue.bnf.fr/couverture?appName=NE&idArk=ark:/12148/cb1',
            'volumeCount'    => 3,
            'volumes'        => [['number' => 1, 'isbn' => '9782344036075', 'coverUrl' => null]],
            'ownedNumbers'   => [1],
        ];
    }

    /** @param array<string, mixed> $body */
    private function requestAs(string $token, string $method, string $url, array $body = []): Response
    {
        $this->client->request($method, $url, [], [], [
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_ACCEPT'        => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], $body !== [] ? (string) json_encode($body) : '');

        return $this->client->getResponse();
    }

    private function resolve(string $value): string
    {
        return str_replace(['{manga}', '{volume}'], [$this->mangaId, $this->volumeId], $value);
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function resolveBody(array $body): array
    {
        return array_map(
            fn (mixed $value): mixed => is_string($value) ? $this->resolve($value) : $value,
            $body,
        );
    }
}
