<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine;

use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findById(string $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => strtolower($email)]);
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function delete(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    /** @return array{items: list<User>, total: int} */
    public function findPaginated(string $search, ?UserStatusEnum $status, int $page, int $limit): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.createdAt', 'DESC');

        if ($search !== '') {
            $queryBuilder
                ->andWhere('u.email LIKE :search OR u.displayName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status !== null) {
            $queryBuilder
                ->andWhere('u.status = :status')
                ->setParameter('status', $status);
        }

        $countQuery = (clone $queryBuilder)->select('COUNT(u.id)')->resetDQLPart('orderBy');
        $total      = (int) $countQuery->getQuery()->getSingleScalarResult();

        $items = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function hasAnyAdmin(): bool
    {
        return (bool) $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['role' => UserRoleEnum::Admin]);
    }
}
