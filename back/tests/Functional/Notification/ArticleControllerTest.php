<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Auth\Domain\UserRepositoryInterface;
use App\Collection\Domain\CollectionEntry;
use App\Notification\Domain\Article;
use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class ArticleControllerTest extends AbstractApiTestCase
{
    // ── GET /api/articles ────────────────────────────────────────────────────

    public function testListRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/articles', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testListReturnsPagedResult(): void
    {
        $response = $this->jsonRequest('GET', '/api/articles');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertArrayHasKey('totalPages', $data);
        $this->assertIsArray($data['items']);
        $this->assertSame(1, $data['page']);
    }

    public function testListWithCustomPagination(): void
    {
        $response = $this->jsonRequest('GET', '/api/articles?page=2&limit=5');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertSame(2, $data['page']);
        $this->assertSame(5, $data['limit']);
    }

    public function testListFilteredByCollectionEntry(): void
    {
        $response = $this->jsonRequest('GET', '/api/articles?collectionEntryId=some-id');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertIsArray($data['items']);
        $this->assertSame(0, $data['total']);
    }

    public function testArticlesAreScopedToOwnerAccount(): void
    {
        // The setUp admin owns a collection entry …
        $mangaResponse = $this->jsonRequest('POST', '/api/manga', [
            'title'        => 'News Series',
            'language'     => 'fr',
            'totalVolumes' => null,
        ]);
        $mangaId = (string) ((array) json_decode((string) $mangaResponse->getContent(), true))['id'];

        $entryResponse = $this->jsonRequest('POST', '/api/collection', ['mangaId' => $mangaId]);
        $entryId = (string) ((array) json_decode((string) $entryResponse->getContent(), true))['id'];

        // Articles only surface for followed entries.
        $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/follow');

        // … with one news article persisted under that account.
        $container     = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin         = $container->get(UserRepositoryInterface::class)->findByEmail('admin@test.local');

        $article = new Article(
            id: Uuid::v4()->toRfc4122(),
            collectionEntry: $entityManager->getReference(CollectionEntry::class, $entryId),
            title: 'Owner-only news',
            url: 'https://example.test/news',
            sourceName: 'rss',
            author: null,
            imageUrl: null,
            publishedAt: null,
        );
        $article->owner = $admin;
        $entityManager->persist($article);
        $entityManager->flush();

        // The owner sees the article …
        $ownerData = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/articles'));
        $this->assertSame(1, $ownerData['total']);

        // … a different account does not.
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'reader@test.local');
        $this->client->request('GET', '/api/articles', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenForUser('reader@test.local'),
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $otherData = (array) json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame(0, $otherData['total']);
    }

    // ── GET /api/articles/activity-logs ──────────────────────────────────────

    public function testActivityLogsRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/articles/activity-logs', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testActivityLogsReturnsPagedResult(): void
    {
        $response = $this->jsonRequest('GET', '/api/articles/activity-logs');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertArrayHasKey('totalPages', $data);
    }

    public function testActivityLogsWithFilters(): void
    {
        $response = $this->jsonRequest('GET', '/api/articles/activity-logs?eventType=auth_action&status=success');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertIsArray($data['items']);
    }

    public function testActivityLogsWithCustomPagination(): void
    {
        $response = $this->jsonRequest('GET', '/api/articles/activity-logs?page=1&limit=10');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertSame(1, $data['page']);
        $this->assertSame(10, $data['limit']);
    }
}
