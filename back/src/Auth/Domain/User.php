<?php

declare(strict_types=1);

namespace App\Auth\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_1483A5E9E7927C74', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Column]
    public DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?DateTimeImmutable $lastLoginAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\Column(length: 180)]
        public string $email,
        #[ORM\Column(length: 255)]
        public string $passwordHash,
        #[ORM\Column(length: 100)]
        public string $displayName,
        #[ORM\Column(enumType: UserRoleEnum::class)]
        public UserRoleEnum $role = UserRoleEnum::User,
        #[ORM\Column(enumType: UserStatusEnum::class)]
        public UserStatusEnum $status = UserStatusEnum::PendingEmailVerification,
        #[ORM\Column(enumType: NotificationChannelEnum::class)]
        public NotificationChannelEnum $notificationChannel = NotificationChannelEnum::Email,
        #[ORM\Column(length: 180, nullable: true)]
        public ?string $notificationEmail = null,
        #[ORM\Column(length: 500, nullable: true)]
        public ?string $discordWebhookUrl = null,
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    public static function createAdmin(string $id, string $email, string $passwordHash, string $displayName): self
    {
        return new self(
            id: $id,
            email: strtolower($email),
            passwordHash: $passwordHash,
            displayName: $displayName,
            role: UserRoleEnum::Admin,
            status: UserStatusEnum::Active,
        );
    }

    public function markEmailVerified(): void
    {
        if ($this->status !== UserStatusEnum::PendingEmailVerification) {
            return;
        }

        $this->status = UserStatusEnum::PendingAdminApproval;
    }

    public function approve(): void
    {
        $this->status = UserStatusEnum::Active;
    }

    public function disable(): void
    {
        $this->status = UserStatusEnum::Disabled;
    }

    public function changePassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
    }

    public function updateNotificationPreferences(
        NotificationChannelEnum $channel,
        ?string $notificationEmail,
        ?string $discordWebhookUrl,
    ): void {
        $this->notificationChannel = $channel;
        $this->notificationEmail   = $notificationEmail;
        $this->discordWebhookUrl   = $discordWebhookUrl;
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = new DateTimeImmutable();
    }

    public function getRoles(): array
    {
        $roles = [UserRoleEnum::User->value];

        if ($this->role === UserRoleEnum::Admin) {
            $roles[] = UserRoleEnum::Admin->value;
        }

        return $roles;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'email'               => $this->email,
            'displayName'         => $this->displayName,
            'role'                => $this->role->value,
            'status'              => $this->status->value,
            'notificationChannel' => $this->notificationChannel->value,
            'notificationEmail'   => $this->notificationEmail,
            'discordWebhookUrl'   => $this->discordWebhookUrl,
            'createdAt'           => $this->createdAt->format('c'),
            'lastLoginAt'         => $this->lastLoginAt?->format('c'),
        ];
    }
}
