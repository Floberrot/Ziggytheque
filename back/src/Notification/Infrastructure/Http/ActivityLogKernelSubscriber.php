<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Http;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Event\UserActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class ActivityLogKernelSubscriber
{
    private const EXCLUDED_PREFIXES = ['/api/auth'];

    public function __construct(private EventBusInterface $eventBus)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $startTime  = $request->server->get('REQUEST_TIME_FLOAT') ?? microtime(true);
        $durationMs = (int) ((microtime(true) - (float) $startTime) * 1000);

        $this->eventBus->publish(new UserActionEvent(
            method: $request->getMethod(),
            path: $path,
            statusCode: $event->getResponse()->getStatusCode(),
            routeName: (string) ($request->attributes->get('_route') ?? ''),
            durationMs: $durationMs,
        ));
    }
}
