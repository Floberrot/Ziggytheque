<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Tests\Functional\AbstractApiTestCase;

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
