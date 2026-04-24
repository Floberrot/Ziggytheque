<?php

declare(strict_types=1);

namespace App\Stats\Application\GetStats;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\VolumeEntry;
use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetStatsHandler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetStatsQuery $query): array
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
            'recentAdditions' => array_map(
                static fn (CollectionEntry $e) => $e->toArray(),
                $recent,
            ),
        ];
    }
}
