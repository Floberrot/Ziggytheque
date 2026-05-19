<?php

declare(strict_types=1);

namespace App\Auth\Domain;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;

    public function delete(User $user): void;

    /** @return array{items: list<User>, total: int} */
    public function findPaginated(string $search, ?UserStatusEnum $status, int $page, int $limit): array;

    public function hasAnyAdmin(): bool;
}
