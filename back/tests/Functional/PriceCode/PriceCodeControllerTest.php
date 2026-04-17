<?php

declare(strict_types=1);

namespace App\Tests\Functional\PriceCode;

use App\PriceCode\Application\Create\CreatePriceCodeCommand;
use App\PriceCode\Application\Delete\DeletePriceCodeCommand;
use App\PriceCode\Application\List\ListPriceCodesQuery;
use App\PriceCode\Application\Update\UpdatePriceCodeCommand;
use App\PriceCode\Domain\Exception\PriceCodeAlreadyExistsException;
use App\PriceCode\Domain\Exception\PriceCodeNotFoundException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Tests\Functional\BaseFunctionalTest;

class PriceCodeControllerTest extends BaseFunctionalTest
{
    public function testListReturns200WithPriceCodes(): void
    {
        $client = $this->createAuthenticatedClient();

        $mockQuery = $this->createMock(QueryBusInterface::class);
        $mockQuery->method('ask')
            ->with($this->isInstanceOf(ListPriceCodesQuery::class))
            ->willReturn([['code' => 'POCHE', 'label' => 'Poche', 'value' => 6.99, 'createdAt' => '2024-01-01T00:00:00+00:00']]);

        static::getContainer()->set(QueryBusInterface::class, $mockQuery);

        $client->request('GET', '/api/price-codes');

        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertCount(1, $body);
        $this->assertSame('POCHE', $body[0]['code']);
    }

    public function testCreateReturns201(): void
    {
        $client = $this->createAuthenticatedClient();

        $mockCommand = $this->createMock(CommandBusInterface::class);
        $mockCommand->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CreatePriceCodeCommand::class));

        static::getContainer()->set(CommandBusInterface::class, $mockCommand);

        $client->request(
            'POST',
            '/api/price-codes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['code' => 'POCHE', 'label' => 'Poche', 'value' => 6.99]),
        );

        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateReturns409WhenCodeAlreadyExists(): void
    {
        $client = $this->createAuthenticatedClient();

        $mockCommand = $this->createMock(CommandBusInterface::class);
        $mockCommand->method('dispatch')->willThrowException(new PriceCodeAlreadyExistsException('POCHE'));

        static::getContainer()->set(CommandBusInterface::class, $mockCommand);

        $client->request(
            'POST',
            '/api/price-codes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['code' => 'POCHE', 'label' => 'Poche', 'value' => 6.99]),
        );

        $this->assertResponseStatusCodeSame(409);
    }

    public function testUpdateReturns204(): void
    {
        $client = $this->createAuthenticatedClient();

        $mockCommand = $this->createMock(CommandBusInterface::class);
        $mockCommand->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(UpdatePriceCodeCommand::class));

        static::getContainer()->set(CommandBusInterface::class, $mockCommand);

        $client->request(
            'PATCH',
            '/api/price-codes/POCHE',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['label' => 'New Label', 'value' => 7.99]),
        );

        $this->assertResponseStatusCodeSame(204);
    }

    public function testUpdateReturns404WhenNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $mockCommand = $this->createMock(CommandBusInterface::class);
        $mockCommand->method('dispatch')->willThrowException(new PriceCodeNotFoundException('MISSING'));

        static::getContainer()->set(CommandBusInterface::class, $mockCommand);

        $client->request(
            'PATCH',
            '/api/price-codes/MISSING',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['label' => 'X', 'value' => 1.0]),
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteReturns204(): void
    {
        $client = $this->createAuthenticatedClient();

        $mockCommand = $this->createMock(CommandBusInterface::class);
        $mockCommand->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DeletePriceCodeCommand::class));

        static::getContainer()->set(CommandBusInterface::class, $mockCommand);

        $client->request('DELETE', '/api/price-codes/POCHE');

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteReturns404WhenNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $mockCommand = $this->createMock(CommandBusInterface::class);
        $mockCommand->method('dispatch')->willThrowException(new PriceCodeNotFoundException('MISSING'));

        static::getContainer()->set(CommandBusInterface::class, $mockCommand);

        $client->request('DELETE', '/api/price-codes/MISSING');

        $this->assertResponseStatusCodeSame(404);
    }
}
