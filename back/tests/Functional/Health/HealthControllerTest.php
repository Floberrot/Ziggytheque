<?php

declare(strict_types=1);

namespace App\Tests\Functional\Health;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame(['status' => 'ok'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testHealthDoesNotRequireJwt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health', [], [], ['HTTP_AUTHORIZATION' => 'Bearer invalid']);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }
}
