<?php

declare(strict_types=1);

namespace App\Auth\Application\Gate;

use App\Auth\Domain\Exception\InvalidGatePasswordException;
use App\Auth\Domain\GateUser;
use App\Auth\Shared\Event\GateFailedEvent;
use App\Auth\Shared\Event\GateStartedEvent;
use App\Auth\Shared\Event\GateSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class GateHandler
{
    public function __construct(
        private string $gatePassword,
        private JWTTokenManagerInterface $jwtManager,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(GateCommand $command): string
    {
        $started = new GateStartedEvent();
        $this->eventBus->publish($started);

        try {
            if (!hash_equals($this->gatePassword, $command->password)) {
                throw new InvalidGatePasswordException();
            }

            $token = $this->jwtManager->create(new GateUser());

            $this->eventBus->publish(new GateSucceededEvent(
                correlationId: $started->correlationId,
                token: $token,
            ));

            return $token;
        } catch (Throwable $e) {
            $this->eventBus->publish(new GateFailedEvent(
                correlationId: $started->correlationId,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
