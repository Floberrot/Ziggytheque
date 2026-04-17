<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Gate;

use App\Auth\Application\Gate\GateCommand;
use App\Auth\Application\Gate\GateHandler;
use App\Auth\Domain\Exception\InvalidGatePasswordException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;

class GateHandlerTest extends TestCase
{
    public function testReturnsTokenOnValidPassword(): void
    {
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->method('create')->willReturn('test-jwt-token');

        $handler = new GateHandler('secret123', $jwtManager);
        $token = $handler(new GateCommand('secret123'));

        $this->assertSame('test-jwt-token', $token);
    }

    public function testThrowsOnInvalidPassword(): void
    {
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->never())->method('create');

        $handler = new GateHandler('secret123', $jwtManager);

        $this->expectException(InvalidGatePasswordException::class);
        $handler(new GateCommand('wrong'));
    }
}
