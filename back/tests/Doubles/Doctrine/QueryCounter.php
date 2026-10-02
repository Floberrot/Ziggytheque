<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Doctrine;

/** Counts the SQL statements sent to the database (test env only). */
final class QueryCounter
{
    private int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function reset(): void
    {
        $this->count = 0;
    }

    public function count(): int
    {
        return $this->count;
    }
}
