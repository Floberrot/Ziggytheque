<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;
use App\Manga\Domain\EditionDiscoveryInterface;
use App\Manga\Domain\ExternalEditionDto;
use Psr\Log\LoggerInterface;

final readonly class CompositeEditionDiscoveryClient implements EditionDiscoveryInterface
{
    /**
     * @param iterable<EditionDiscoveryInterface> $providers ordered by priority (highest first)
     */
    public function __construct(
        private iterable $providers,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return ExternalEditionDto[]
     */
    public function discoverEditions(string $workTitle, Country $country): array
    {
        /** @var array<string, ExternalEditionDto> $seenByKey */
        $seenByKey = [];
        $allEditions = [];

        foreach ($this->providers as $provider) {
            $editions = $provider->discoverEditions($workTitle, $country);

            $this->logger->info('COMPOSITE_EDITIONS : provider returned editions.', [
                'provider' => $provider::class,
                'count'    => count($editions),
            ]);

            foreach ($editions as $edition) {
                $deduplicationKey = strtolower($edition->publisher) . '|' . ($edition->year ?? '');

                if (isset($seenByKey[$deduplicationKey])) {
                    continue;
                }

                $seenByKey[$deduplicationKey] = $edition;
                $allEditions[] = $edition;
            }
        }

        return $allEditions;
    }
}
