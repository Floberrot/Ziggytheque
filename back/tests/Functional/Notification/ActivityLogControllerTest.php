<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\EventTypeEnum;
use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ActivityLogControllerTest extends AbstractApiTestCase
{
    // ── Authorization ────────────────────────────────────────────────────────

    public function testActivityLogsRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/articles/activity-logs', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testActivityLogsForbiddenForPlainUser(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'plain@test.local');
        $userToken = $this->tokenForUser('plain@test.local');

        $response = $this->requestWithToken('GET', '/api/articles/activity-logs', $userToken);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testActivityLogsForbiddenForAdminWithoutGate(): void
    {
        // $this->token is ROLE_ADMIN but NOT ROLE_ADMIN_UNLOCKED
        $response = $this->jsonRequest('GET', '/api/articles/activity-logs');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testActivityLogsReturnsPagedResultForAdminUnlocked(): void
    {
        $data = $this->fetchActivityLogs();

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertIsArray($data['items']);
    }

    public function testActivityLogsWithCustomPagination(): void
    {
        $data = $this->fetchActivityLogs('page=1&limit=10');

        $this->assertSame(1, $data['page']);
        $this->assertSame(10, $data['limit']);
    }

    // ── Owner exposure & owner filter ────────────────────────────────────────

    public function testActivityLogItemsExposeOwner(): void
    {
        // The setUp admin performs an API call → owner must be attributed.
        $this->jsonRequest('GET', '/api/collection');

        $items = $this->itemsFor('eventType=user_action&search=/api/collection');

        $this->assertNotEmpty($items);
        $ownerEmails = array_map(static fn (array $item) => $item['owner']['email'] ?? null, $items);
        $this->assertContains('admin@test.local', $ownerEmails);
    }

    public function testActivityLogsFilterByOwnerId(): void
    {
        $reader = UserFixtureFactory::createActiveUser(static::getContainer(), email: 'owner-filter@test.local');
        $readerToken = $this->tokenForUser('owner-filter@test.local');

        // Each account performs one API call → one user_action log per owner.
        $this->jsonRequest('GET', '/api/collection');
        $this->requestWithToken('GET', '/api/collection', $readerToken);

        $items = $this->itemsFor('eventType=user_action&ownerId=' . $reader->id);

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertSame($reader->id, $item['owner']['id']);
        }
    }

    // ── from / to / search filters ───────────────────────────────────────────

    public function testActivityLogsFilterByFromAndTo(): void
    {
        $this->persistLog(sourceName: 'old-window-source', startedAt: new DateTimeImmutable('-10 days'));
        $this->persistLog(sourceName: 'recent-window-source', startedAt: new DateTimeImmutable('-1 hour'));

        $fromYesterday = urlencode((new DateTimeImmutable('-1 day'))->format(DateTimeInterface::ATOM));
        $recentOnly    = $this->itemsFor('search=window-source&from=' . $fromYesterday);
        $this->assertSame(['recent-window-source'], array_column($recentOnly, 'sourceName'));

        $toTwoDaysAgo = urlencode((new DateTimeImmutable('-2 days'))->format(DateTimeInterface::ATOM));
        $oldOnly      = $this->itemsFor('search=window-source&to=' . $toTwoDaysAgo);
        $this->assertSame(['old-window-source'], array_column($oldOnly, 'sourceName'));
    }

    public function testActivityLogsSearchMatchesSourceNameErrorMessageAndPath(): void
    {
        $this->persistLog(sourceName: 'zzz-unique-source');
        $this->persistLog(sourceName: 'other', errorMessage: 'yyy-unique-error happened');
        $this->persistLog(sourceName: 'http', metadata: ['path' => '/api/xxx-unique-path']);

        $this->assertSame(
            ['zzz-unique-source'],
            array_column($this->itemsFor('search=ZZZ-UNIQUE'), 'sourceName'),
        );

        $errorItems = $this->itemsFor('search=yyy-unique');
        $this->assertCount(1, $errorItems);
        $this->assertSame('yyy-unique-error happened', $errorItems[0]['errorMessage']);

        $pathItems = $this->itemsFor('search=xxx-unique');
        $this->assertCount(1, $pathItems);
        $this->assertSame('/api/xxx-unique-path', $pathItems[0]['metadata']['path']);
    }

    // ── Auth coverage ────────────────────────────────────────────────────────

    public function testSuccessfulLoginCreatesAuthActionLogWithoutSensitiveData(): void
    {
        // setUp already logged in as admin@test.local.
        $items = $this->itemsFor('eventType=auth_action&search=login');

        $loginItems = array_values(array_filter(
            $items,
            static fn (array $item) => $item['sourceName'] === 'login',
        ));

        $this->assertNotEmpty($loginItems);
        $loginLog = $loginItems[0];
        $this->assertSame('success', $loginLog['status']);
        $this->assertSame('admin@test.local', $loginLog['metadata']['email'] ?? null);
        $this->assertSame('admin@test.local', $loginLog['owner']['email'] ?? null);

        $metadataJson = strtolower((string) json_encode($loginLog['metadata']));
        $this->assertStringNotContainsString('password', $metadataJson);
        $this->assertStringNotContainsString('token', $metadataJson);
    }

    public function testFailedLoginCreatesHttpErrorLog(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/auth/login',
            ['email' => 'admin@test.local', 'password' => 'wrong-password'],
            auth: false,
        );
        $this->assertSame(401, $response->getStatusCode());

        $items = $this->itemsFor('eventType=http_error&search=/api/auth/login');

        $this->assertNotEmpty($items);
        $failedLog = $items[0];
        $this->assertSame('error', $failedLog['status']);
        $this->assertSame(401, $failedLog['metadata']['status_code']);
        $this->assertSame('/api/auth/login', $failedLog['metadata']['path']);
        $this->assertStringContainsString('HTTP 401', (string) $failedLog['errorMessage']);
    }

    public function testRegistrationCreatesAuthActionLogWithoutVerificationToken(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/auth/register',
            ['email' => 'journal-reg@test.local', 'password' => 'Password1!', 'displayName' => 'Journal Reg'],
            auth: false,
        );
        $this->assertSame(201, $response->getStatusCode());

        $items = $this->itemsFor('eventType=auth_action&search=register');

        $registerItems = array_values(array_filter(
            $items,
            static fn (array $item) => $item['sourceName'] === 'register',
        ));

        $this->assertNotEmpty($registerItems);
        $registerLog = $registerItems[0];
        $this->assertSame('success', $registerLog['status']);
        $this->assertSame('journal-reg@test.local', $registerLog['metadata']['email'] ?? null);
        $this->assertSame('journal-reg@test.local', $registerLog['owner']['email'] ?? null);

        $metadataJson = strtolower((string) json_encode($registerLog['metadata']));
        $this->assertStringNotContainsString('token', $metadataJson);
        $this->assertStringNotContainsString('password', $metadataJson);
    }

    public function testGateTokenNeverAppearsInActivityLogs(): void
    {
        $unlockedToken = $this->getAdminUnlockedToken();

        $response = $this->requestWithToken(
            'GET',
            '/api/articles/activity-logs?eventType=auth_action',
            $unlockedToken,
        );
        $this->assertSame(200, $response->getStatusCode());

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString($unlockedToken, $body);

        /** @var array{items: list<array<string, mixed>>} $data */
        $data      = (array) json_decode($body, true);
        $gateItems = array_values(array_filter(
            $data['items'],
            static fn (array $item) => $item['sourceName'] === 'gate',
        ));

        $this->assertNotEmpty($gateItems);
        $this->assertSame('success', $gateItems[0]['status']);
        $metadataJson = strtolower((string) json_encode($gateItems[0]['metadata']));
        $this->assertStringNotContainsString('token', $metadataJson);
    }

    // ── HTTP error pipeline & path masking ───────────────────────────────────

    public function testNotFoundRequestCreatesHttpErrorLog(): void
    {
        $response = $this->jsonRequest('GET', '/api/does-not-exist');
        $this->assertSame(404, $response->getStatusCode());

        $items = $this->itemsFor('eventType=http_error&search=/api/does-not-exist');

        $this->assertNotEmpty($items);
        $notFoundLog = $items[0];
        $this->assertSame('error', $notFoundLog['status']);
        $this->assertSame('HTTP 404 GET /api/does-not-exist', $notFoundLog['errorMessage']);
        $this->assertSame(404, $notFoundLog['metadata']['status_code']);
        $this->assertArrayHasKey('ip', $notFoundLog['metadata']);
        $this->assertArrayHasKey('user_agent', $notFoundLog['metadata']);
    }

    public function testSharePathTokenIsMaskedInActivityLog(): void
    {
        $shareToken = str_repeat('a1b2', 8); // 32 hex chars, matches the share route
        $response   = $this->jsonRequest('GET', '/api/share/' . $shareToken, auth: false);
        $this->assertSame(404, $response->getStatusCode());

        $items = $this->itemsFor('eventType=http_error&search=/api/share/');

        $this->assertNotEmpty($items);
        $maskedLog = $items[0];
        $this->assertSame('/api/share/***', $maskedLog['metadata']['path']);
        $this->assertStringNotContainsString($shareToken, (string) json_encode($maskedLog));
    }

    public function testScanPathTokenIsMaskedInActivityLog(): void
    {
        $scanToken = 'scan-secret-token-abcdef123456';
        $response  = $this->jsonRequest('GET', '/api/scan/' . $scanToken);
        $this->assertSame(404, $response->getStatusCode());

        $items = $this->itemsFor('eventType=http_error&search=/api/scan/');

        $this->assertNotEmpty($items);
        $maskedLog = $items[0];
        $this->assertSame('/api/scan/***', $maskedLog['metadata']['path']);
        $this->assertStringNotContainsString($scanToken, (string) json_encode($maskedLog));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getAdminUnlockedToken(): string
    {
        $gateResponse = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'ziggy123']);
        /** @var array{token?: string} $data */
        $data = json_decode((string) $gateResponse->getContent(), true);

        return $data['token'] ?? '';
    }

    private function requestWithToken(string $method, string $url, string $token, string $body = ''): Response
    {
        $this->client->request($method, $url, [], [], [
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_ACCEPT'        => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], $body);

        return $this->client->getResponse();
    }

    /** Fetches the journal as admin-unlocked and returns the decoded payload. */
    private function fetchActivityLogs(string $queryString = ''): array
    {
        $url = '/api/articles/activity-logs' . ($queryString !== '' ? '?' . $queryString : '');

        $response = $this->requestWithToken('GET', $url, $this->getAdminUnlockedToken());
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return (array) json_decode((string) $response->getContent(), true);
    }

    /** @return list<array<string, mixed>> */
    private function itemsFor(string $queryString): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = $this->fetchActivityLogs($queryString)['items'] ?? [];

        return $items;
    }

    private function persistLog(
        string $sourceName,
        ?DateTimeImmutable $startedAt = null,
        ?string $errorMessage = null,
        ?array $metadata = null,
    ): void {
        $container     = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            eventType: EventTypeEnum::UserAction,
            sourceName: $sourceName,
            metadata: $metadata,
        );
        if ($errorMessage !== null) {
            $log->markError($errorMessage);
        } else {
            $log->markSuccess();
        }
        if ($startedAt !== null) {
            $log->startedAt = $startedAt;
        }

        $entityManager->persist($log);
        $entityManager->flush();
    }
}
