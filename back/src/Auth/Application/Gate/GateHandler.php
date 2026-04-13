<?php

declare(strict_types=1);

namespace App\Auth\Application\Gate;

use App\Auth\Domain\Exception\InvalidGatePasswordException;
use App\Auth\Domain\GateUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class GateHandler
{
    public function __construct(
        private string $gatePassword,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function __invoke(GateCommand $command): string
    {
        if (!hash_equals($this->gatePassword, $command->password)) {
            throw new InvalidGatePasswordException();
        }

        return $this->jwtManager->create(new GateUser());
    }
}
