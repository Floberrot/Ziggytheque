<?php

declare(strict_types=1);

namespace App\Auth\Application\Gate;

use App\Auth\Domain\Exception\InvalidGatePasswordException;
use App\Auth\Domain\User;
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

            $token = $this->jwtManager->createFromPayload($command->user, ['adminUnlocked' => true]);

            $this->eventBus->publish(new GateSucceededEvent(
                correlationId: $started->correlationId,
                token: $token,
            ));

            return $token;
        } catch (Throwable $exception) {
            $this->eventBus->publish(new GateFailedEvent(
                correlationId: $started->correlationId,
                error: $exception->getMessage(),
                exceptionClass: $exception::class,
            ));
            throw $exception;
        }
    }
}
