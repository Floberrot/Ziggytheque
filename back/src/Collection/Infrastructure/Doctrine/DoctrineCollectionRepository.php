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

    public function findWithWishlistVolumes(): array
    {
        return $this->em->createQueryBuilder()
            ->select('DISTINCT ce')
            ->from(CollectionEntry::class, 'ce')
            ->join('ce.volumeEntries', 've')
            ->where('ve.isWishlisted = :wishlisted')
            ->andWhere('ve.isOwned = :owned')
            ->setParameter('wishlisted', true)
            ->setParameter('owned', false)
            ->orderBy('ce.addedAt', 'DESC')
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
