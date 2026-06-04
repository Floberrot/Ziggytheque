<?php

declare(strict_types=1);

namespace App\Share\Infrastructure\Doctrine;

use App\Share\Domain\ShareSnapshot;
use App\Share\Domain\ShareSnapshotRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineShareSnapshotRepository implements ShareSnapshotRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(ShareSnapshot $snapshot): void
    {
        $this->em->persist($snapshot);
        $this->em->flush();
    }

    public function findByToken(string $token): ?ShareSnapshot
    {
        return $this->em->getRepository(ShareSnapshot::class)
            ->findOneBy(['token' => $token]);
    }
}
