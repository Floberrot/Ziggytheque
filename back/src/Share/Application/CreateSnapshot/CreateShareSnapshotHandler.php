<?php

declare(strict_types=1);

namespace App\Share\Application\CreateSnapshot;

use App\Auth\Domain\UserRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use App\Share\Domain\ShareSnapshot;
use App\Share\Domain\ShareSnapshotRepositoryInterface;
use App\Stats\Domain\StatsRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateShareSnapshotHandler
{
    public function __construct(
        private ShareSnapshotRepositoryInterface $repository,
        private StatsRepositoryInterface $statsRepository,
        private UserRepositoryInterface $userRepository,
        private CurrentUserProviderInterface $currentUserProvider,
    ) {
    }

    public function __invoke(CreateShareSnapshotCommand $command): string
    {
        // The owner Doctrine filter is active for the current HTTP request, so
        // getStats() is already scoped to the authenticated user's collection.
        $stats   = $this->statsRepository->getStats();
        $ownerId = $this->currentUserProvider->currentUserId();
        $owner   = $this->userRepository->findById($ownerId);

        if ($owner === null) {
            throw new NotFoundException('User', $ownerId);
        }

        // Only the public-safe subset is frozen into the snapshot: headline
        // counts and the genre breakdown. Monetary value and covers are never
        // exposed through the public share link.
        $publicStats = [
            'totalMangas'    => $stats['totalMangas'],
            'totalOwned'     => $stats['totalOwned'],
            'totalRead'      => $stats['totalRead'],
            'totalWishlist'  => $stats['totalWishlist'],
            'genreBreakdown' => $stats['genreBreakdown'],
        ];

        // The owner is the authenticated user issuing the request, so it always
        // resolves; the denormalized name is frozen alongside the stats.
        $snapshot = new ShareSnapshot(
            id: Uuid::v4()->toRfc4122(),
            token: bin2hex(random_bytes(16)),
            owner: $owner,
            ownerName: $owner->displayName,
            payload: $publicStats,
        );

        $this->repository->save($snapshot);

        return $snapshot->token;
    }
}
