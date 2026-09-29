<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Doctrine;

use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMangaRepository implements MangaRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findById(string $id): ?Manga
    {
        return $this->em->find(Manga::class, $id);
    }

    public function search(string $query): array
    {
        return $this->em->createQueryBuilder()
            ->select('m')
            ->from(Manga::class, 'm')
            ->where('LOWER(m.title) LIKE LOWER(:query)')
            ->orWhere('LOWER(m.author) LIKE LOWER(:query)')
            ->orWhere('LOWER(m.edition) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('m.title', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    public function findByTitles(array $titles): array
    {
        if ($titles === []) {
            return [];
        }

        /** @var list<Manga> $mangas */
        $mangas = $this->em->createQueryBuilder()
            ->select('m')
            ->from(Manga::class, 'm')
            ->where('LOWER(m.title) IN (:titles)')
            ->setParameter('titles', array_values(array_unique(array_map('mb_strtolower', $titles))))
            ->getQuery()
            ->getResult();

        return $mangas;
    }

    public function findAllPaginated(int $offset, int $limit): array
    {
        return $this->em->createQueryBuilder()
            ->select('m')
            ->from(Manga::class, 'm')
            ->orderBy('m.createdAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Manga::class, 'm')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Manga $manga): void
    {
        $this->em->persist($manga);
        $this->em->flush();
    }
}
