<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Auth\Domain\GateUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BaseFunctionalTest extends WebTestCase
{
    protected function createAuthenticatedClient(): KernelBrowser
    {
        $client = static::createClient();
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $token = $jwtManager->create(new GateUser());
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $client;
    }
}
