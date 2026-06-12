<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Infrastructure\Cache\PsrEditionDiscoveryCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class PsrEditionDiscoveryCacheTest extends TestCase
{
    private function makeCache(): PsrEditionDiscoveryCache
    {
        return new PsrEditionDiscoveryCache(new ArrayAdapter());
    }

    public function testReturnsNullOnMiss(): void
    {
        $this->assertNull($this->makeCache()->get('berserk', null, 'fr'));
    }

    public function testStoresAndReturnsResult(): void
    {
        $cache    = $this->makeCache();
        $editions = [['editionLabel' => 'Glénat', 'source' => 'bnf']];

        $cache->put('berserk', null, 'fr', $editions);

        $this->assertSame($editions, $cache->get('berserk', null, 'fr'));
    }

    public function testKeyDependsOnQueryAuthorAndLanguage(): void
    {
        $cache = $this->makeCache();
        $cache->put('berserk', null, 'fr', [['editionLabel' => 'Glénat']]);

        $this->assertNull($cache->get('berserk', null, 'en'));
        $this->assertNull($cache->get('berserk', 'Miura', 'fr'));
        $this->assertNull($cache->get('naruto', null, 'fr'));
    }

    public function testQueryIsTrimmedAndCaseInsensitive(): void
    {
        $cache = $this->makeCache();
        $cache->put('Berserk', null, 'fr', [['editionLabel' => 'Glénat']]);

        $this->assertNotNull($cache->get('  berserk  ', null, 'fr'));
    }

    public function testEmptyResultIsCachedToo(): void
    {
        $cache = $this->makeCache();
        $cache->put('unknown work', null, 'fr', []);

        $this->assertSame([], $cache->get('unknown work', null, 'fr'));
    }
}
