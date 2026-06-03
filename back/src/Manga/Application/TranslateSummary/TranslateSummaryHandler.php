<?php

declare(strict_types=1);

namespace App\Manga\Application\TranslateSummary;

use App\Manga\Domain\SummaryTranslatorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class TranslateSummaryHandler
{
    /** Source/target are fixed: only English → French is supported for now. */
    private const SOURCE_LANGUAGE = 'en';
    private const TARGET_LANGUAGE = 'fr';

    public function __construct(private SummaryTranslatorInterface $translator)
    {
    }

    /** @return array{translated: string} */
    public function __invoke(TranslateSummaryQuery $query): array
    {
        $translated = $this->translator->translate(
            $query->text,
            self::SOURCE_LANGUAGE,
            self::TARGET_LANGUAGE,
        );

        return ['translated' => $translated];
    }
}
