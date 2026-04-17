<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Auth\Application\Gate\GateCommand;
use App\Auth\Application\Gate\GateHandler;
use App\Auth\Domain\Exception\InvalidGatePasswordException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Tests\Functional\BaseFunctionalTest;

class GateControllerTest extends BaseFunctionalTest
{
    public function testReturnsTokenOnValidCredentials(): void
    {
        $client = static::createClient();

        $mockBus = $this->createMock(CommandBusInterface::class);
        $mockBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(GateCommand::class))
            ->willReturn('fake-jwt-token');

        static::getContainer()->set(CommandBusInterface::class, $mockBus);

        $client->request('POST', '/api/auth/gate', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['password' => 'ziggy123']));

        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertSame('fake-jwt-token', $body['token']);
    }

    public function testReturns401OnInvalidCredentials(): void
    {
        $client = static::createClient();

        $mockBus = $this->createMock(CommandBusInterface::class);
        $mockBus->method('dispatch')->willThrowException(new InvalidGatePasswordException());

        static::getContainer()->set(CommandBusInterface::class, $mockBus);

        $client->request('POST', '/api/auth/gate', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['password' => 'wrong']));

        $this->assertResponseStatusCodeSame(401);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testReturns422OnMissingPassword(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/gate', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([]));

        $this->assertResponseStatusCodeSame(422);
    }
}
