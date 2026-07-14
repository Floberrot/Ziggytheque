<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogOwnerResolverInterface;
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
 *
 * Metadata extracted from events is sanitized: properties whose name matches
 * the sensitive denylist (token, password, secret, jwt, authorization, apikey)
 * are never copied, only scalars / arrays of scalars are kept, and strings are
 * truncated so a single event can never flood the journal.
 */
final readonly class ActivityLogEventHandler
{
    private const SENSITIVE_NAME_PATTERN = '/token|password|secret|jwt|authorization|apikey/i';
    private const MAX_STRING_LENGTH      = 500;

    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private CollectionRepositoryInterface $collectionRepository,
        private ActivityLogOwnerResolverInterface $ownerResolver,
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
            owner: $this->ownerResolver->currentOwner(),
            collectionEntry: $collectionEntry,
        );

        $this->activityLogRepository->save($log);
    }

    /**
     * Handle a Succeeded event: find ActivityLog by correlationId and mark success.
     *
     * @param object $event Must have: correlationId. Optional: newCount, addedCount, itemsScanned, userId
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

        $this->attributeOwnerFromEvent($log, $event);

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

        $metadata = array_merge(
            $this->extractMetadata($event),
            ['exception_class' => $exceptionClass],
        );
        $log->markError((string) $error, $metadata);

        $this->activityLogRepository->save($log);
    }

    /** Detect EventType from event class namespace */
    public function detectEventTypeEnum(object $event): EventTypeEnum
    {
        $namespace = $event::class;

        return match (true) {
            str_contains($namespace, 'Wishlist') => EventTypeEnum::WishlistAction,
            str_contains($namespace, 'Auth\\Shared\\') => EventTypeEnum::AuthAction,
            str_contains($namespace, 'Collection\\Shared\\') => EventTypeEnum::CollectionAction,
            str_contains($namespace, 'Manga\\Shared\\') => EventTypeEnum::MangaAction,
            str_contains($namespace, 'Notification\\Shared\\') => EventTypeEnum::UserAction,
            default => EventTypeEnum::UserAction,
        };
    }

    /** Attribute the log to the user identified by the event's userId, if not already owned. */
    private function attributeOwnerFromEvent(ActivityLog $log, object $event): void
    {
        if ($log->owner !== null) {
            return;
        }

        $userId = $this->extractProperty($event, 'userId');
        if (!is_string($userId) || $userId === '') {
            return;
        }

        $log->owner = $this->ownerResolver->findById($userId);
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
        foreach (['newCount', 'addedCount', 'itemsScanned', 'count', 'articleCount'] as $propertyName) {
            $value = $this->extractProperty($event, $propertyName);
            if ($value !== null && is_int($value)) {
                return $value;
            }
        }
        return 0;
    }

    /** Extract non-reserved, non-sensitive properties as sanitized metadata.
     * @return array<string, mixed>
     */
    private function extractMetadata(object $event): array
    {
        $reserved = [
            'correlationId', 'newCount', 'addedCount', 'itemsScanned',
            'sourceName', 'collectionEntryId', 'eventType', 'count', 'articleCount',
            'error', 'exceptionClass', 'userId',
        ];
        $metadata = [];

        try {
            $reflection = new ReflectionObject($event);
            foreach ($reflection->getProperties() as $property) {
                $propertyName = $property->getName();
                if (in_array($propertyName, $reserved, true)) {
                    continue;
                }
                if (preg_match(self::SENSITIVE_NAME_PATTERN, $propertyName) === 1) {
                    continue;
                }
                $property->setAccessible(true);
                $sanitizedValue = $this->sanitizeValue($property->getValue($event));
                if ($sanitizedValue !== null) {
                    $metadata[$propertyName] = $sanitizedValue;
                }
            }
        } catch (Throwable) {
            // If reflection fails, return empty metadata
        }

        return $metadata;
    }

    /**
     * Keep only scalars and arrays of scalars; truncate long strings; drop
     * objects, resources and sensitive array keys.
     */
    private function sanitizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return mb_substr($value, 0, self::MAX_STRING_LENGTH);
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            $sanitizedArray = [];
            foreach ($value as $arrayKey => $arrayValue) {
                if (is_string($arrayKey) && preg_match(self::SENSITIVE_NAME_PATTERN, $arrayKey) === 1) {
                    continue;
                }
                $sanitizedItem = $this->sanitizeValue($arrayValue);
                if ($sanitizedItem !== null) {
                    $sanitizedArray[$arrayKey] = $sanitizedItem;
                }
            }
            return $sanitizedArray;
        }

        return null;
    }
}
