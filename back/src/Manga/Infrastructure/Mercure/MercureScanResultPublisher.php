<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Mercure;

use App\Manga\Domain\ScanResultPublisherInterface;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class MercureScanResultPublisher implements ScanResultPublisherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $hubUrl,
        private string $publisherJwtKey,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(string $sessionId, string $isbn): void
    {
        $topic = sprintf('https://ziggytheque.app/cover-batch/%s', $sessionId);

        try {
            $response = $this->httpClient->request('POST', $this->hubUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->mintPublisherJwt(),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'topic' => $topic,
                    'data' => (string) json_encode(['isbn' => $isbn]),
                    'private' => '1',
                ]),
            ]);
            $response->getStatusCode();
        } catch (Throwable $throwable) {
            $this->logger->warning('Mercure scan publish failed for session {sessionId}: {message}', [
                'sessionId' => $sessionId,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function mintPublisherJwt(): string
    {
        if (strlen($this->publisherJwtKey) < 32) {
            throw new RuntimeException(
                sprintf(
                    'MERCURE_PUBLISHER_JWT_KEY must be at least 32 characters (256 bits), %d given.',
                    strlen($this->publisherJwtKey),
                ),
            );
        }

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->publisherJwtKey),
        );

        $now = new DateTimeImmutable();

        $token = $config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('mercure', ['publish' => ['*']])
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }
}
