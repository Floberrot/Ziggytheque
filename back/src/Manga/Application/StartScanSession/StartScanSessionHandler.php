<?php

declare(strict_types=1);

namespace App\Manga\Application\StartScanSession;

use App\Manga\Domain\ScanSessionAuthorizerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class StartScanSessionHandler
{
    public function __construct(private ScanSessionAuthorizerInterface $scanSessionAuthorizer)
    {
    }

    public function __invoke(StartScanSessionCommand $command): StartScanSessionResult
    {
        $sessionId = Uuid::v4()->toRfc4122();

        $subscriberToken = $this->scanSessionAuthorizer->issueSubscriberToken($sessionId, ttlSeconds: 600);
        $topic = $this->scanSessionAuthorizer->topicFor($sessionId);
        $mercureUrl = $this->scanSessionAuthorizer->publicHubUrl();

        return new StartScanSessionResult(
            sessionId: $sessionId,
            mercureUrl: $mercureUrl,
            subscriberToken: $subscriberToken,
            topic: $topic,
        );
    }
}
