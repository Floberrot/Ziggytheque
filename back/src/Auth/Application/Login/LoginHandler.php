<?php

declare(strict_types=1);

namespace App\Auth\Application\Login;

use App\Auth\Domain\Exception\AccountNotActivatedException;
use App\Auth\Domain\Exception\InvalidCredentialsException;
use App\Auth\Domain\Service\PasswordHasherInterface;
use App\Auth\Domain\Service\SessionTokenIssuerInterface;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Shared\Event\LoginFailedEvent;
use App\Auth\Shared\Event\LoginStartedEvent;
use App\Auth\Shared\Event\LoginSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class LoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private SessionTokenIssuerInterface $sessionTokenIssuer,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(LoginCommand $command): string
    {
        $started = new LoginStartedEvent();
        $this->eventBus->publish($started);

        try {
            $user = $this->userRepository->findByEmail($command->email);

            if ($user === null) {
                // Hash anyway: an unknown address must take as long as a wrong
                // password, or the response time would tell which emails exist.
                $this->passwordHasher->hash($command->password);

                throw new InvalidCredentialsException();
            }

            if (!$this->passwordHasher->isValid($user, $command->password)) {
                throw new InvalidCredentialsException();
            }

            if ($user->status !== UserStatusEnum::Active) {
                throw new AccountNotActivatedException($user->status);
            }

            $user->recordLogin();
            $this->userRepository->save($user);

            $token = $this->sessionTokenIssuer->issue($user);

            $this->eventBus->publish(new LoginSucceededEvent(
                correlationId: $started->correlationId,
                userId: $user->id,
                email: $user->email,
            ));

            return $token;
        } catch (Throwable $exception) {
            $this->eventBus->publish(new LoginFailedEvent(
                correlationId: $started->correlationId,
                error: $exception->getMessage(),
                exceptionClass: $exception::class,
                email: $command->email,
            ));
            throw $exception;
        }
    }
}
