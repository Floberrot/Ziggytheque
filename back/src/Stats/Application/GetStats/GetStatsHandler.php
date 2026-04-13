<?php

declare(strict_types=1);

namespace App\Stats\Application\GetStats;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use App\Wishlist\Domain\WishlistItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetStatsHandler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

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
            ->select('COUNT(w.id)')
            ->from(WishlistItem::class, 'w')
            ->where('w.isPurchased = false')
            ->getQuery()
            ->getSingleScalarResult();

        // Collection value: sum of price codes for owned volumes
        $collectionValue = (float) ($this->em->createQueryBuilder()
            ->select('SUM(pc.value)')
            ->from(VolumeEntry::class, 've')
            ->join('ve.volume', 'v')
            ->join('v.priceCode', 'pc')
            ->where('ve.isOwned = true')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

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
            $genre = $row['genre'] instanceof \BackedEnum ? $row['genre']->value : ($row['genre'] ?? 'other');
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
            'collectionValue' => round($collectionValue, 2),
            'genreBreakdown' => $genreBreakdown,
            'recentAdditions' => array_map(
                static fn (CollectionEntry $e) => $e->toArray(),
                $recent,
            ),
        ];
    }
}
