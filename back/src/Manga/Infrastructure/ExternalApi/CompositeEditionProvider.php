<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\EditionProviderInterface;
use App\Manga\Domain\ExternalEditionDto;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class CompositeEditionProvider implements EditionProviderInterface
{
    /** @param iterable<EditionProviderInterface> $providers */
    public function __construct(
        private iterable $providers,
        private LoggerInterface $logger,
    ) {
    }

    public function findEditions(string $workTitle, ?string $author, ?string $language): array
    {
        /** @var list<ExternalEditionDto> $editions */
        $editions = [];

        foreach ($this->providers as $provider) {
            try {
                $providerEditions = $provider->findEditions($workTitle, $author, $language);

                $this->logger->info('COMPOSITE EDITIONS : source result.', [
                    'provider' => $provider::class,
                    'count'    => count($providerEditions),
                ]);

                foreach ($providerEditions as $edition) {
                    $editions[] = $edition;
                }
            } catch (Throwable $exception) {
                $this->logger->error('COMPOSITE EDITIONS : provider failed, skipping.', [
                    'provider' => $provider::class,
                    'error'    => $exception->getMessage(),
                ]);
            }
        }

        return $editions;
    }
}
