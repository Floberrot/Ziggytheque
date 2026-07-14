<?php

declare(strict_types=1);

namespace App\Auth\Application\Login;

use App\Auth\Domain\Exception\AccountNotActivatedException;
use App\Auth\Domain\Exception\InvalidCredentialsException;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Shared\Event\LoginFailedEvent;
use App\Auth\Shared\Event\LoginStartedEvent;
use App\Auth\Shared\Event\LoginSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class LoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(LoginCommand $command): string
    {
        $started = new LoginStartedEvent();
        $this->eventBus->publish($started);

        try {
            $user = $this->userRepository->findByEmail($command->email);

            if ($user === null || !$this->passwordHasher->isPasswordValid($user, $command->password)) {
                throw new InvalidCredentialsException();
            }

            if ($user->status !== UserStatusEnum::Active) {
                throw new AccountNotActivatedException($user->status);
            }

            $user->recordLogin();
            $this->userRepository->save($user);

            $token = $this->jwtManager->create($user);

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
