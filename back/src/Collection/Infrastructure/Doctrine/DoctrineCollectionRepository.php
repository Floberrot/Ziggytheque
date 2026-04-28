<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Doctrine;

use App\Collection\Application\Get\GetCollectionQuery;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\CollectionSortEnum;
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

    public function findFiltered(GetCollectionQuery $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('ce')
            ->from(CollectionEntry::class, 'ce')
            ->join('ce.manga', 'm');

        if ($query->search !== null && $query->search !== '') {
            $qb->andWhere('LOWER(m.title) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $query->search . '%');
        }

        if ($query->genre !== null) {
            $qb->andWhere('m.genre = :genre')
               ->setParameter('genre', $query->genre->value);
        }

        if ($query->edition !== null && $query->edition !== '') {
            $qb->andWhere('LOWER(m.edition) LIKE LOWER(:edition)')
               ->setParameter('edition', '%' . $query->edition . '%');
        }

        if ($query->readingStatus !== null) {
            $qb->andWhere('ce.readingStatus = :readingStatus')
               ->setParameter('readingStatus', $query->readingStatus->value);
        }

        if ($query->followedOnly) {
            $qb->andWhere('ce.notificationsEnabled = true');
        }

        // Count total before sorting/pagination
        $countQb = clone $qb;
        $countQb->select('COUNT(ce.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Sorting — PostgreSQL ASC default is NULLS LAST; DESC NULLS LAST requires COALESCE workaround
        match ($query->sort) {
            CollectionSortEnum::RatingAsc  => $qb->orderBy('ce.rating', 'ASC'),
            CollectionSortEnum::RatingDesc => $qb
                ->addSelect('COALESCE(ce.rating, -1) AS HIDDEN sort_key')
                ->orderBy('sort_key', 'DESC'),
            default => $qb->orderBy('ce.addedAt', 'DESC'),
        };

        $offset = ($query->page - 1) * $query->limit;

        /** @var list<CollectionEntry> $items */
        $items = $qb
            ->setFirstResult($offset)
            ->setMaxResults($query->limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
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
