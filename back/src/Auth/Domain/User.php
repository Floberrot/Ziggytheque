<?php

declare(strict_types=1);

namespace App\Auth\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;
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

    /** Transient — set per-request by JWT auth when payload has adminUnlocked=true. */
    private bool $adminUnlocked = false;

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

    public function markAdminUnlocked(): void
    {
        $this->adminUnlocked = true;
    }

    public function getRoles(): array
    {
        $roles = [UserRoleEnum::User->value];

        if ($this->role === UserRoleEnum::Admin) {
            $roles[] = UserRoleEnum::Admin->value;
        }

        if ($this->adminUnlocked) {
            $roles[] = 'ROLE_ADMIN_UNLOCKED';
        }

        return $roles;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function getUserIdentifier(): string
    {
        if ($this->email === '') {
            throw new RuntimeException('User email is empty.');
        }

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

    /**
     * Admin-safe serialization: the notification channel is visible, but the
     * actual destination (email address or Discord webhook URL) is never
     * leaked. The admin only needs to know which channel a user has picked,
     * not the personal address behind it.
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id'                     => $this->id,
            'email'                  => $this->email,
            'displayName'            => $this->displayName,
            'role'                   => $this->role->value,
            'status'                 => $this->status->value,
            'notificationChannel'    => $this->notificationChannel->value,
            'notificationConfigured' => match ($this->notificationChannel) {
                NotificationChannelEnum::Email   => $this->notificationEmail !== null
                    && $this->notificationEmail !== '',
                NotificationChannelEnum::Discord => $this->discordWebhookUrl !== null
                    && $this->discordWebhookUrl !== '',
            },
            'createdAt'              => $this->createdAt->format('c'),
            'lastLoginAt'            => $this->lastLoginAt?->format('c'),
        ];
    }
}
