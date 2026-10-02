<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** /messenger sits behind HTTP Basic with MONITOR_USER / MONITOR_PASSWORD (see .env.test). */
final class MessengerMonitorTest extends WebTestCase
{
    public function testAsksForCredentials(): void
    {
        $client = static::createClient();
        $client->request('GET', '/messenger');

        $this->assertResponseStatusCodeSame(401);
        $this->assertResponseHasHeader('WWW-Authenticate');
    }

    public function testRefusesAWrongPassword(): void
    {
        $client = static::createClient();
        $client->request('GET', '/messenger', server: ['PHP_AUTH_USER' => 'monitor', 'PHP_AUTH_PW' => 'monitor']);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLetsTheMonitorIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/messenger', server: [
            'PHP_AUTH_USER' => 'monitor',
            'PHP_AUTH_PW'   => 'test-monitor-password',
        ]);

        $this->assertNotSame(401, $client->getResponse()->getStatusCode());
        $this->assertNotSame(403, $client->getResponse()->getStatusCode());
    }
}
