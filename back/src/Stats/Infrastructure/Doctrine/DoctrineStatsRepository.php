<?php

declare(strict_types=1);

namespace App\Stats\Infrastructure\Doctrine;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\VolumeEntry;
use App\Stats\Domain\StatsRepositoryInterface;
use BackedEnum;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineStatsRepository implements StatsRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return array<string, mixed> */
    public function getStats(): array
    {
        $totalMangas = (int) $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(CollectionEntry::class, 'c')
            ->getQuery()
            ->getSingleScalarResult();

        $totalOwned = (int) $this->em->createQueryBuilder()
            ->select('COUNT(ve.id)')
            ->from(VolumeEntry::class, 've')
            ->where('ve.isOwned = true')
            ->getQuery()
            ->getSingleScalarResult();

        $totalRead = (int) $this->em->createQueryBuilder()
            ->select('COUNT(ve.id)')
            ->from(VolumeEntry::class, 've')
            ->where('ve.isRead = true')
            ->getQuery()
            ->getSingleScalarResult();

        $totalWishlist = (int) $this->em->createQueryBuilder()
            ->select('COUNT(ve.id)')
            ->from(VolumeEntry::class, 've')
            ->where('ve.isWished = true')
            ->andWhere('ve.isOwned = false')
            ->getQuery()
            ->getSingleScalarResult();

        $ownedValue = (float) ($this->em->createQueryBuilder()
            ->select('SUM(v.price)')
            ->from(VolumeEntry::class, 've')
            ->join('ve.volume', 'v')
            ->where('ve.isOwned = true')
            ->andWhere('v.price IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $wishlistValue = (float) ($this->em->createQueryBuilder()
            ->select('SUM(v.price)')
            ->from(VolumeEntry::class, 've')
            ->join('ve.volume', 'v')
            ->where('ve.isWished = true')
            ->andWhere('ve.isOwned = false')
            ->andWhere('v.price IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $totalValue = $ownedValue + $wishlistValue;

        // Genre breakdown
        $genreRows = $this->em->createQueryBuilder()
            ->select('m.genre, COUNT(c.id) as count')
            ->from(CollectionEntry::class, 'c')
            ->join('c.manga', 'm')
            ->groupBy('m.genre')
            ->getQuery()
            ->getResult();

        $genreBreakdown = [];
        foreach ($genreRows as $row) {
            $genre = $row['genre'] instanceof BackedEnum ? $row['genre']->value : 'other';
            $genreBreakdown[$genre] = (int) $row['count'];
        }

        // Reading-status breakdown (series count per reading status)
        $statusRows = $this->em->createQueryBuilder()
            ->select('c.readingStatus as status, COUNT(c.id) as count')
            ->from(CollectionEntry::class, 'c')
            ->groupBy('c.readingStatus')
            ->getQuery()
            ->getResult();

        $readingStatusBreakdown = [];
        foreach ($statusRows as $row) {
            // readingStatus is a non-nullable backed enum, so it always hydrates
            // to an enum instance.
            $readingStatusBreakdown[$row['status']->value] = (int) $row['count'];
        }

        // Top authors by number of series in the collection
        $authorRows = $this->em->createQueryBuilder()
            ->select('m.author as author, COUNT(c.id) as count')
            ->from(CollectionEntry::class, 'c')
            ->join('c.manga', 'm')
            ->where('m.author IS NOT NULL')
            ->andWhere("m.author <> ''")
            ->groupBy('m.author')
            ->orderBy('count', 'DESC')
            ->addOrderBy('m.author', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $topAuthors = array_map(
            static fn (array $row): array => [
                'author' => (string) $row['author'],
                'count' => (int) $row['count'],
            ],
            $authorRows,
        );

        // Average rating across rated series
        $ratingRow = $this->em->createQueryBuilder()
            ->select('AVG(c.rating) as avgRating, COUNT(c.rating) as ratedCount')
            ->from(CollectionEntry::class, 'c')
            ->getQuery()
            ->getSingleResult();

        $ratedCount    = (int) $ratingRow['ratedCount'];
        $averageRating = $ratingRow['avgRating'] !== null ? round((float) $ratingRow['avgRating'], 1) : null;

        // Additions over the last 12 months (oldest → newest), zero-filled
        $monthlyAdditions = $this->monthlyAdditions();

        // Recent additions
        $recent = $this->em->createQueryBuilder()
            ->select('c', 'm')
            ->from(CollectionEntry::class, 'c')
            ->join('c.manga', 'm')
            ->orderBy('c.addedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return [
            'totalMangas' => $totalMangas,
            'totalOwned' => $totalOwned,
            'totalRead' => $totalRead,
            'totalWishlist' => $totalWishlist,
            'ownedValue' => round($ownedValue, 2),
            'wishlistValue' => round($wishlistValue, 2),
            'totalValue' => round($totalValue, 2),
            'genreBreakdown' => $genreBreakdown,
            'readingStatusBreakdown' => $readingStatusBreakdown,
            'topAuthors' => $topAuthors,
            'averageRating' => $averageRating,
            'ratedCount' => $ratedCount,
            'monthlyAdditions' => $monthlyAdditions,
            'recentAdditions' => array_map(
                static fn (CollectionEntry $e) => $e->toArray(),
                $recent,
            ),
        ];
    }

    /**
     * Series added per month over the trailing 12-month window, oldest first.
     * Months with no additions are returned with a zero count so the chart keeps
     * a continuous timeline.
     *
     * @return list<array{month: string, count: int}>
     */
    private function monthlyAdditions(): array
    {
        $now      = new DateTimeImmutable('first day of this month 00:00:00');
        $earliest = $now->modify('-11 months');

        $buckets = [];
        $cursor  = $earliest;
        while ($cursor <= $now) {
            $buckets[$cursor->format('Y-m')] = 0;
            $cursor                          = $cursor->modify('+1 month');
        }

        $addedDates = $this->em->createQueryBuilder()
            ->select('c.addedAt')
            ->from(CollectionEntry::class, 'c')
            ->where('c.addedAt >= :earliest')
            ->setParameter('earliest', $earliest)
            ->getQuery()
            ->getResult();

        foreach ($addedDates as $row) {
            $key = $row['addedAt']->format('Y-m');
            if (isset($buckets[$key])) {
                ++$buckets[$key];
            }
        }

        $result = [];
        foreach ($buckets as $month => $count) {
            $result[] = ['month' => $month, 'count' => $count];
        }

        return $result;
    }
}
