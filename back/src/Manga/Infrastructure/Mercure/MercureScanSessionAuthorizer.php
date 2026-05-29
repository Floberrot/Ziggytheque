<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Mercure;

use App\Manga\Domain\ScanSessionAuthorizerInterface;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use RuntimeException;

final readonly class MercureScanSessionAuthorizer implements ScanSessionAuthorizerInterface
{
    public function __construct(
        private string $subscriberJwtKey,
        private string $publicHubUrlValue,
    ) {
    }

    public function issueSubscriberToken(string $sessionId, int $ttlSeconds): string
    {
        if (strlen($this->subscriberJwtKey) < 32) {
            throw new RuntimeException(
                sprintf(
                    'MERCURE_SUBSCRIBER_JWT_KEY must be at least 32 characters (256 bits), %d given.',
                    strlen($this->subscriberJwtKey)
                )
            );
        }

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->subscriberJwtKey),
        );

        $now = new DateTimeImmutable();

        $token = $config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify(sprintf('+%d seconds', $ttlSeconds)))
            ->withClaim('mercure', ['subscribe' => [$this->topicFor($sessionId)]])
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    public function topicFor(string $sessionId): string
    {
        return sprintf('https://ziggytheque.app/scan-session/%s', $sessionId);
    }

    public function publicHubUrl(): string
    {
        return $this->publicHubUrlValue;
    }
}
