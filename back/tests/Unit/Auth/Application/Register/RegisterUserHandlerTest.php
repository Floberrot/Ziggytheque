<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Register;

use App\Auth\Application\Register\RegisterUserCommand;
use App\Auth\Application\Register\RegisterUserHandler;
use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\Exception\EmailAlreadyTakenException;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Shared\Event\RegisterFailedEvent;
use App\Auth\Shared\Event\RegistrationOnExistingAccountEvent;
use App\Auth\Shared\Event\UserRegisteredEvent;
use App\Shared\Application\Bus\EventBusInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegisterUserHandlerTest extends TestCase
{
    /** @var list<object> */
    private array $publishedEvents = [];

    private function eventBus(): EventBusInterface
    {
        $eventBus = $this->createStub(EventBusInterface::class);
        $eventBus->method('publish')->willReturnCallback(function (object $event): void {
            $this->publishedEvents[] = $event;
        });

        return $eventBus;
    }

    /** @return list<class-string> */
    private function publishedEventClasses(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->publishedEvents);
    }

    public function testAnExistingAddressIsAnsweredQuietlyAndItsOwnerTold(): void
    {
        $owner = new User(
            id: 'owner-1',
            email: 'owner@example.com',
            passwordHash: 'owner-hash',
            displayName: 'Owner',
            status: UserStatusEnum::Active,
        );
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findByEmail')->willReturn($owner);
        $userRepository->expects($this->never())->method('save');
        $tokenRepository = $this->createMock(AuthTokenRepositoryInterface::class);
        $tokenRepository->expects($this->never())->method('save');
        // The same hashing work as a real registration: the timing tells nothing.
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects($this->once())->method('hashPassword')->willReturn('wasted-hash');

        $handler = new RegisterUserHandler(
            $userRepository,
            $tokenRepository,
            $passwordHasher,
            $this->createStub(TokenGeneratorInterface::class),
            $this->eventBus(),
        );
        $handler(new RegisterUserCommand('owner@example.com', 'Password1!', 'Someone'));

        $notice = array_values(array_filter(
            $this->publishedEvents,
            static fn (object $event): bool => $event instanceof RegistrationOnExistingAccountEvent,
        ));
        $this->assertCount(1, $notice);
        $this->assertSame('owner@example.com', $notice[0]->email);
        $this->assertSame('Owner', $notice[0]->displayName);

        $failure = array_values(array_filter(
            $this->publishedEvents,
            static fn (object $event): bool => $event instanceof RegisterFailedEvent,
        ));
        $this->assertCount(1, $failure);
        $this->assertSame(EmailAlreadyTakenException::class, $failure[0]->exceptionClass);
        $this->assertNotContains(UserRegisteredEvent::class, $this->publishedEventClasses());
        // The account itself is untouched.
        $this->assertSame('owner-hash', $owner->passwordHash);
        $this->assertSame('Owner', $owner->displayName);
    }

    public function testANewAddressCreatesAPendingAccount(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findByEmail')->willReturn(null);
        $userRepository->expects($this->once())->method('save')->with($this->callback(
            static fn (User $user): bool => $user->email === 'new@example.com'
                && $user->status === UserStatusEnum::PendingEmailVerification
                && $user->passwordHash === 'new-hash',
        ));
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('new-hash');
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('plain-token');
        $tokenGenerator->method('hash')->willReturn('token-hash');

        $handler = new RegisterUserHandler(
            $userRepository,
            $this->createStub(AuthTokenRepositoryInterface::class),
            $passwordHasher,
            $tokenGenerator,
            $this->eventBus(),
        );
        $handler(new RegisterUserCommand('New@Example.com', 'Password1!', 'Newcomer'));

        $this->assertContains(UserRegisteredEvent::class, $this->publishedEventClasses());
        $this->assertNotContains(RegistrationOnExistingAccountEvent::class, $this->publishedEventClasses());
    }
}
