<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain;

use App\Collection\Domain\ReadingStatusEnum;
use PHPUnit\Framework\TestCase;

class ReadingStatusEnumTest extends TestCase
{
    public function testAllCasesHaveStringValues(): void
    {
        foreach (ReadingStatusEnum::cases() as $case) {
            $this->assertIsString($case->value);
            $this->assertNotEmpty($case->value);
        }
    }

    public function testFromStringReturnsCorrectCase(): void
    {
        $this->assertSame(ReadingStatusEnum::NotStarted, ReadingStatusEnum::from('not_started'));
        $this->assertSame(ReadingStatusEnum::InProgress, ReadingStatusEnum::from('in_progress'));
        $this->assertSame(ReadingStatusEnum::Completed, ReadingStatusEnum::from('completed'));
        $this->assertSame(ReadingStatusEnum::OnHold, ReadingStatusEnum::from('on_hold'));
        $this->assertSame(ReadingStatusEnum::Dropped, ReadingStatusEnum::from('dropped'));
    }
}
