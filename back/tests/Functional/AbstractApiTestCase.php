<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Functional\Fixtures\UserFixtureFactory;
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
        $this->token  = $this->fetchAuthToken();
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
