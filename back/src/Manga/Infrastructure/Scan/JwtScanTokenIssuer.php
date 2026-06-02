<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Scan;

use App\Manga\Domain\Exception\InvalidScanTokenException;
use App\Manga\Domain\ScanTokenIssuerInterface;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use RuntimeException;
use Symfony\Component\Clock\NativeClock;
use Throwable;

final readonly class JwtScanTokenIssuer implements ScanTokenIssuerInterface
{
    public function __construct(
        private string $secret,
    ) {
    }

    public function issue(string $sessionId, int $ttlSeconds): string
    {
        $config = $this->config();
        $now = new DateTimeImmutable();

        $token = $config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify(sprintf('+%d seconds', $ttlSeconds)))
            ->withClaim('sid', $sessionId)
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    public function verify(string $token): string
    {
        if ($token === '') {
            throw new InvalidScanTokenException('Scan token is empty.');
        }

        try {
            $config = $this->config();
            $parsed = $config->parser()->parse($token);

            $valid = $config->validator()->validate(
                $parsed,
                new SignedWith($config->signer(), $config->signingKey()),
                new LooseValidAt(new NativeClock()),
            );

            if (!$valid) {
                throw new InvalidScanTokenException('Invalid or expired scan token.');
            }

            if (!$parsed instanceof UnencryptedToken) {
                throw new InvalidScanTokenException('Unexpected token type.');
            }

            $sidClaim = $parsed->claims()->get('sid');

            if (!is_string($sidClaim) || $sidClaim === '') {
                throw new InvalidScanTokenException('Scan token is missing session ID claim.');
            }

            return $sidClaim;
        } catch (InvalidScanTokenException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new InvalidScanTokenException('Malformed scan token.');
        }
    }

    private function config(): Configuration
    {
        if (strlen($this->secret) < 32) {
            throw new RuntimeException(
                sprintf(
                    'SCAN_TOKEN_SECRET must be at least 32 characters (256 bits), %d given.',
                    strlen($this->secret),
                ),
            );
        }

        return Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->secret),
        );
    }
}
