<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain;

use App\Collection\Domain\ReadingStatusEnum;
use PHPUnit\Framework\TestCase;

final class ReadingStatusEnumTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('not_started', ReadingStatusEnum::NotStarted->value);
        $this->assertSame('in_progress', ReadingStatusEnum::InProgress->value);
        $this->assertSame('completed', ReadingStatusEnum::Completed->value);
        $this->assertSame('on_hold', ReadingStatusEnum::OnHold->value);
        $this->assertSame('dropped', ReadingStatusEnum::Dropped->value);
    }

    public function testFromValue(): void
    {
        $this->assertSame(ReadingStatusEnum::Completed, ReadingStatusEnum::from('completed'));
    }
}
