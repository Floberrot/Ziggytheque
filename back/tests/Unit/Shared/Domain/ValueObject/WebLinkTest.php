<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\WebLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebLinkTest extends TestCase
{
    public function testAcceptsHttpAndHttpsLinks(): void
    {
        $this->assertTrue(WebLink::isWebLink('https://www.manga-news.com/index.php/actus/berserk'));
        $this->assertTrue(WebLink::isWebLink('HTTP://example.org/article'));
    }

    /** @return iterable<string, array{string|null}> */
    public static function unsafeLinks(): iterable
    {
        yield 'javascript' => ['javascript:alert(document.cookie)'];
        yield 'javascript with spaces' => ['  JavaScript:alert(1)'];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'relative' => ['/actus/berserk'];
        yield 'no host' => ['https:///path'];
        yield 'empty' => [''];
        yield 'null' => [null];
    }

    #[DataProvider('unsafeLinks')]
    public function testRefusesEverythingElse(?string $url): void
    {
        $this->assertFalse(WebLink::isWebLink($url));
    }
}
