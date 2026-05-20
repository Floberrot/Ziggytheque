<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Security\CurrentUserProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Enables the per-owner Doctrine filters for the authenticated user on every
 * main HTTP request, scoping all user-owned data (collections, wishlist,
 * notifications, articles) to that account. Unauthenticated requests and the
 * worker / CLI context leave the filters disabled.
 */
#[AsEventListener]
final readonly class OwnerFilterListener
{
    /** @var list<string> */
    private const FILTERS = ['collection_owner', 'notification_owner'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentUserProviderInterface $currentUserProvider,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $ownerId = $this->currentUserProvider->currentUserIdOrNull();

        if ($ownerId === null) {
            return;
        }

        $filters = $this->entityManager->getFilters();

        foreach (self::FILTERS as $filterName) {
            $filters->enable($filterName)->setParameter('ownerId', $ownerId, 'string');
        }
    }
}
