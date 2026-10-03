<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\TranslateSummary;

use App\Manga\Application\TranslateSummary\TranslateSummaryHandler;
use App\Manga\Application\TranslateSummary\TranslateSummaryQuery;
use App\Manga\Domain\SummaryTranslatorInterface;
use PHPUnit\Framework\TestCase;

final class TranslateSummaryHandlerTest extends TestCase
{
    public function testTranslatesFromEnglishToFrench(): void
    {
        $translator = $this->createMock(SummaryTranslatorInterface::class);
        $translator->expects($this->once())
            ->method('translate')
            ->with('A pirate story.', 'en', 'fr')
            ->willReturn('Une histoire de pirates.');

        $result = (new TranslateSummaryHandler($translator))(new TranslateSummaryQuery('A pirate story.'));

        $this->assertSame(['translated' => 'Une histoire de pirates.'], $result);
    }
}
