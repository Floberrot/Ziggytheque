<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use ReflectionObject;
use Throwable;

/**
 * Handles ActivityLog creation and updates for all domain events.
 * Used by listeners to eliminate code duplication across Started/Succeeded/Failed events.
 *
 * SOLID principles:
 * - Single Responsibility: handles ActivityLog lifecycle only
 * - Open/Closed: new event types automatically supported via reflection
 * - Dependency Inversion: depends on interfaces (ActivityLogRepositoryInterface, CollectionRepositoryInterface)
 */
final readonly class ActivityLogEventHandler
{
    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private CollectionRepositoryInterface $collectionRepository,
    ) {
    }

    /**
     * Handle a Started event: create new ActivityLog.
     *
     * @param object $event Must have: correlationId, sourceName, collectionEntryId (optional)
     */
    public function handleStartedEvent(object $event, EventTypeEnum $eventType): void
    {
        $correlationId = $this->extractProperty($event, 'correlationId');
        if ($correlationId === null) {
            return;
        }

        $sourceName = $this->extractProperty($event, 'sourceName');
        $collectionEntryId = $this->extractProperty($event, 'collectionEntryId');

        $collectionEntry = null;
        if ($collectionEntryId !== null) {
            $collectionEntry = $this->collectionRepository->findById($collectionEntryId);
        }

        $log = new ActivityLog(
            id: $correlationId,
            eventType: $eventType,
            sourceName: (string) $sourceName,
            collectionEntry: $collectionEntry,
        );

        $this->activityLogRepository->save($log);
    }

    /**
     * Handle a Succeeded event: find ActivityLog by correlationId and mark success.
     *
     * @param object $event Must have: correlationId. Optional: newCount, addedCount, itemsScanned
     */
    public function handleSucceededEvent(object $event, ?int $forcedCount = null): void
    {
        $correlationId = $this->extractProperty($event, 'correlationId');
        if ($correlationId === null) {
            return;
        }

        $log = $this->activityLogRepository->findById((string) $correlationId);
        if ($log === null) {
            return; // No corresponding Started event
        }

        // Extract count from event properties or use forced value
        $count = $forcedCount ?? $this->extractCountFromEvent($event);
        $metadata = $this->extractMetadata($event);

        $log->markSuccess($count, $metadata);
        $this->activityLogRepository->save($log);
    }

    /**
     * Handle a Failed event: find ActivityLog by correlationId and mark error.
     *
     * @param object $event Must have: correlationId, error, exceptionClass
     */
    public function handleFailedEvent(object $event): void
    {
        $correlationId = $this->extractProperty($event, 'correlationId');
        if ($correlationId === null) {
            return;
        }

        $log = $this->activityLogRepository->findById((string) $correlationId);
        if ($log === null) {
            return; // No corresponding Started event
        }

        $error = $this->extractProperty($event, 'error') ?? 'Unknown error';
        $exceptionClass = $this->extractProperty($event, 'exceptionClass') ?? Throwable::class;

        $metadata = ['exception_class' => $exceptionClass];
        $log->markError((string) $error, $metadata);

        $this->activityLogRepository->save($log);
    }

    /** Detect EventType from event class namespace */
    public function detectEventTypeEnum(object $event): EventTypeEnum
    {
        $namespace = $event::class;

        return match (true) {
            str_contains($namespace, 'Auth\\Shared\\') => EventTypeEnum::AuthAction,
            str_contains($namespace, 'Collection\\Shared\\') => EventTypeEnum::CollectionAction,
            str_contains($namespace, 'Manga\\Shared\\') => EventTypeEnum::MangaAction,
            str_contains($namespace, 'Wishlist\\Shared\\') => EventTypeEnum::WishlistAction,
            str_contains($namespace, 'Notification\\Shared\\') => EventTypeEnum::UserAction,
            default => EventTypeEnum::UserAction,
        };
    }

    /** Extract a public property value via reflection */
    private function extractProperty(object $object, string $propertyName): mixed
    {
        try {
            $reflection = new ReflectionObject($object);
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            return $property->getValue($object);
        } catch (Throwable) {
            return null;
        }
    }

    /** Extract count value from common property names */
    private function extractCountFromEvent(object $event): int
    {
        foreach (['newCount', 'addedCount', 'itemsScanned', 'count', 'articleCount'] as $prop) {
            $value = $this->extractProperty($event, $prop);
            if ($value !== null && is_int($value)) {
                return $value;
            }
        }
        return 0;
    }

    /** Extract non-reserved properties as metadata.
     * @return array<string, mixed>
     */
    private function extractMetadata(object $event): array
    {
        $reserved = [
            'correlationId', 'newCount', 'addedCount', 'itemsScanned',
            'sourceName', 'collectionEntryId', 'eventType', 'count', 'articleCount',
        ];
        $metadata = [];

        try {
            $reflection = new ReflectionObject($event);
            foreach ($reflection->getProperties() as $prop) {
                $name = $prop->getName();
                if (!in_array($name, $reserved, true)) {
                    $prop->setAccessible(true);
                    $value = $prop->getValue($event);
                    if ($value !== null) {
                        $metadata[$name] = $value;
                    }
                }
            }
        } catch (Throwable) {
            // If reflection fails, return empty metadata
        }

        return $metadata;
    }
}
