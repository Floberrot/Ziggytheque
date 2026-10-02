<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Pagination;

use App\Shared\Application\Pagination\AbstractPaginatedQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AbstractPaginatedQueryTest extends TestCase
{
    /** @return iterable<string, array{int, int, int, int}> */
    public static function pages(): iterable
    {
        yield 'defaults kept' => [1, 20, 1, 20];
        yield 'page zero' => [0, 20, 1, 20];
        yield 'negative page' => [-4, 20, 1, 20];
        yield 'limit zero' => [2, 0, 2, 1];
        yield 'huge limit' => [1, 100000, 1, AbstractPaginatedQuery::MAX_LIMIT];
    }

    #[DataProvider('pages')]
    public function testKeepsPageAndLimitInRange(int $page, int $limit, int $expectedPage, int $expectedLimit): void
    {
        $query = new readonly class ($page, $limit) extends AbstractPaginatedQuery {
        };

        $this->assertSame($expectedPage, $query->page);
        $this->assertSame($expectedLimit, $query->limit);
    }
}
