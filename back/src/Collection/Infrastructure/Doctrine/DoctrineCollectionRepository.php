<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Doctrine;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCollectionRepository implements CollectionRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findById(string $id): ?CollectionEntry
    {
        return $this->em->find(CollectionEntry::class, $id);
    }

    public function findByMangaId(string $mangaId): ?CollectionEntry
    {
        return $this->em->getRepository(CollectionEntry::class)
            ->findOneBy(['manga' => $mangaId]);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(CollectionEntry::class)
            ->findBy([], ['addedAt' => 'DESC']);
    }

    public function findWithWishedVolumes(): array
    {
        return $this->em->createQueryBuilder()
            ->select('DISTINCT c')
            ->from(CollectionEntry::class, 'c')
            ->join('c.volumeEntries', 've')
            ->where('ve.isWished = true')
            ->andWhere('ve.isOwned = false')
            ->orderBy('c.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFollowed(): array
    {
        return $this->em->createQueryBuilder()
            ->select('e')
            ->from(CollectionEntry::class, 'e')
            ->where('e.notificationsEnabled = true')
            ->getQuery()
            ->getResult();
    }

    public function save(CollectionEntry $entry): void
    {
        $this->em->persist($entry);
        $this->em->flush();
    }

    public function delete(CollectionEntry $entry): void
    {
        $this->em->remove($entry);
        $this->em->flush();
    }
}
