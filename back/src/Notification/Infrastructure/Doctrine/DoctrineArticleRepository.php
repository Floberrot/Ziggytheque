<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineArticleRepository implements ArticleRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function existsByCollectionEntryAndUrl(string $collectionEntryId, string $url): bool
    {
        return $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Article::class, 'a')
            ->where('a.collectionEntry = :ceId')
            ->andWhere('a.url = :url')
            ->setParameter('ceId', $collectionEntryId)
            ->setParameter('url', $url)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function save(Article $article): void
    {
        $this->em->persist($article);
        $this->em->flush();
    }

    public function findPaginated(int $page, int $limit, ?string $collectionEntryId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->join('a.collectionEntry', 'ce')
            ->orderBy('a.createdAt', 'DESC');

        if ($collectionEntryId !== null) {
            $qb->where('ce.id = :ceId')
               ->setParameter('ceId', $collectionEntryId);
        } else {
            // TOUS : masquer les entrées dont les notifs sont désactivées
            $qb->where('ce.notificationsEnabled = true');
        }

        $total = (clone $qb)->select('COUNT(a.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => (int) $total];
    }
}
