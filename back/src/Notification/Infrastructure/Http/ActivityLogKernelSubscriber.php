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
    /**
     * /api/auth is excluded for successful responses only: successful logins,
     * registrations and gate unlocks are logged as semantic AuthAction events
     * by the Auth handlers (single clean source). Failed auth attempts cannot
     * be logged there (the command transaction rolls back), so they flow
     * through the generic HTTP >= 400 pipeline below.
     */
    private const EXCLUDED_PREFIXES = ['/api/auth'];

    /** Path prefixes whose following segments may carry secrets (share/scan tokens). */
    private const MASKED_PREFIXES = ['/api/share/', '/api/scan/'];

    private const MAX_USER_AGENT_LENGTH = 255;

    public function __construct(private EventBusInterface $eventBus)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request    = $event->getRequest();
        $path       = $request->getPathInfo();
        $statusCode = $event->getResponse()->getStatusCode();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) && $statusCode < 400) {
                return;
            }
        }

        $startTime  = $request->server->get('REQUEST_TIME_FLOAT') ?? microtime(true);
        $durationMs = (int) ((microtime(true) - (float) $startTime) * 1000);

        $userAgent = $request->headers->get('User-Agent');

        $this->eventBus->publish(new UserActionEvent(
            method: $request->getMethod(),
            path: $this->maskSensitivePath($path),
            statusCode: $statusCode,
            routeName: (string) ($request->attributes->get('_route') ?? ''),
            durationMs: $durationMs,
            clientIp: $request->getClientIp(),
            userAgent: $userAgent !== null ? mb_substr($userAgent, 0, self::MAX_USER_AGENT_LENGTH) : null,
        ));
    }

    /**
     * Masks token-looking segments in share/scan paths so secrets never land
     * in the journal metadata (/api/share/{token} → /api/share/***).
     */
    private function maskSensitivePath(string $path): string
    {
        foreach (self::MASKED_PREFIXES as $prefix) {
            if (!str_starts_with($path, $prefix)) {
                continue;
            }

            $segments = explode('/', substr($path, strlen($prefix)));
            $maskedSegments = array_map(
                static fn (string $segment): string => preg_match('/^[A-Za-z0-9._~-]{16,}$/', $segment) === 1
                    ? '***'
                    : $segment,
                $segments,
            );

            return $prefix . implode('/', $maskedSegments);
        }

        return $path;
    }
}
