<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fixtures;

use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class UserFixtureFactory
{
    public static function createActiveAdmin(
        ContainerInterface $container,
        string $email = 'admin@test.local',
        string $plainPassword = 'Test1234!',
        string $displayName = 'Test Admin',
    ): User {
        return self::create($container, $email, $plainPassword, $displayName, UserRoleEnum::Admin, UserStatusEnum::Active);
    }

    public static function createActiveUser(
        ContainerInterface $container,
        string $email = 'user@test.local',
        string $plainPassword = 'Test1234!',
        string $displayName = 'Test User',
    ): User {
        return self::create($container, $email, $plainPassword, $displayName, UserRoleEnum::User, UserStatusEnum::Active);
    }

    public static function createPendingUser(
        ContainerInterface $container,
        string $email = 'pending@test.local',
        string $plainPassword = 'Test1234!',
        string $displayName = 'Pending User',
    ): User {
        return self::create($container, $email, $plainPassword, $displayName, UserRoleEnum::User, UserStatusEnum::PendingAdminApproval);
    }

    private static function create(
        ContainerInterface $container,
        string $email,
        string $plainPassword,
        string $displayName,
        UserRoleEnum $role,
        UserStatusEnum $status,
    ): User {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(
            id: Uuid::v4()->toRfc4122(),
            email: strtolower($email),
            passwordHash: '',
            displayName: $displayName,
            role: $role,
            status: $status,
            notificationChannel: NotificationChannelEnum::Email,
        );

        $user->passwordHash = $hasher->hashPassword($user, $plainPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
