<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine;

use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAuthTokenRepository implements AuthTokenRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(AuthToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    public function findValidByHash(string $tokenHash, AuthTokenTypeEnum $type): ?AuthToken
    {
        return $this->entityManager
            ->getRepository(AuthToken::class)
            ->createQueryBuilder('t')
            ->where('t.tokenHash = :hash')
            ->andWhere('t.type = :type')
            ->andWhere('t.expiresAt > :now')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('hash', $tokenHash)
            ->setParameter('type', $type)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
