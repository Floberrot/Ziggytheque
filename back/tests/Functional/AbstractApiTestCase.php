<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Functional\Fixtures\UserFixtureFactory;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->clearRateLimitCounters();
        $this->token  = $this->fetchAuthToken();
    }

    /**
     * Rate-limit counters live in a filesystem pool in the test env (an array
     * pool is reset after every request, so a counter could never span the
     * requests a rate-limit test makes). That pool outlives the kernel, so it
     * is emptied here: no test may spend another test's budget.
     */
    private function clearRateLimitCounters(): void
    {
        /** @var CacheItemPoolInterface $pool */
        $pool = static::getContainer()->get('test.rate_limiter_cache');
        $pool->clear();
    }

    private function fetchAuthToken(): string
    {
        UserFixtureFactory::createActiveAdmin(
            static::getContainer(),
            email: 'admin@test.local',
            plainPassword: 'Test1234!',
        );

        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['email' => 'admin@test.local', 'password' => 'Test1234!']),
        );

        /** @var array{token?: string} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        return $data['token'] ?? '';
    }

    /** Logs in as an existing user and returns their bearer token. */
    protected function tokenForUser(string $email, string $password = 'Test1234!'): string
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['email' => $email, 'password' => $password]),
        );

        /** @var array{token?: string} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        return $data['token'] ?? '';
    }

    protected function jsonRequest(
        string $method,
        string $url,
        array $body = [],
        bool $auth = true,
    ): Response {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'  => 'application/json',
        ];

        if ($auth && $this->token !== '') {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
        }

        $this->client->request(
            $method,
            $url,
            [],
            [],
            $headers,
            $body !== [] ? (string) json_encode($body) : '',
        );

        return $this->client->getResponse();
    }

    /** @return array<mixed> */
    protected function assertJsonStatus(int $expectedStatus, Response $response): array
    {
        $this->assertSame(
            $expectedStatus,
            $response->getStatusCode(),
            sprintf('Expected HTTP %d, got %d: %s', $expectedStatus, $response->getStatusCode(), $response->getContent()),
        );

        $content = (string) $response->getContent();

        return $content !== '' && $content !== 'null' ? (array) json_decode($content, true) : [];
    }
}
