<?php

declare(strict_types=1);

namespace App\Auth\Application\BootstrapAdmin;

use App\Auth\Domain\Exception\EmailAlreadyTakenException;
use App\Auth\Domain\Service\AdminBackfillServiceInterface;
use App\Auth\Domain\Service\PasswordHasherInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\BackfillReport;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class BootstrapAdminHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private AdminBackfillServiceInterface $backfillService,
    ) {
    }

    public function __invoke(BootstrapAdminCommand $command): BackfillReport
    {
        if (!$command->force && $this->userRepository->hasAnyAdmin()) {
            throw new EmailAlreadyTakenException();
        }

        $admin = User::createAdmin(
            id: Uuid::v4()->toRfc4122(),
            email: $command->email,
            passwordHash: $this->passwordHasher->hash($command->password),
            displayName: $command->displayName,
        );

        $this->userRepository->save($admin);

        return $this->backfillService->assignAllOrphans($admin->id);
    }
}
