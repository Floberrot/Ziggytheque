<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\Service\PublisherNormalizer;
use PHPUnit\Framework\TestCase;

final class PublisherNormalizerTest extends TestCase
{
    private PublisherNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PublisherNormalizer();
    }

    public function testStripsTrailingCity(): void
    {
        $this->assertSame('Glénat', $this->normalizer->displayName('Glénat (Grenoble)'));
        $this->assertSame('Panorama', $this->normalizer->displayName('Panorama [éd.] (Saint-Denis-la-Plaine)'));
    }

    public function testStripsEditionsPrefix(): void
    {
        $this->assertSame('Atlas', $this->normalizer->displayName('Éd. Atlas (Évreux)'));
        $this->assertSame('Atlas', $this->normalizer->displayName('Ed. Atlas (Evreux)'));
    }

    public function testStripsLegalSuffix(): void
    {
        $this->assertSame('Tokyopop', $this->normalizer->displayName('Tokyopop Inc.'));
    }

    public function testVizAliasesCollapse(): void
    {
        $this->assertSame('Viz Media', $this->normalizer->displayName('Viz'));
        $this->assertSame('Viz Media', $this->normalizer->displayName('VIZ Media LLC'));
        $this->assertSame('Viz Media', $this->normalizer->displayName('Viz Communications'));
        $this->assertSame('Viz Media', $this->normalizer->displayName('Shonen Jump Graphic Novel/Viz'));
    }

    public function testComicsUsaVariantsCollapse(): void
    {
        $this->assertSame('Comics USA', $this->normalizer->displayName('Comics USA (Grenoble)'));
        $this->assertSame('Comics USA', $this->normalizer->displayName('Comics-USA (Grenoble)'));
    }

    public function testMapsJapaneseKanjiPublishersToRomaji(): void
    {
        $this->assertSame('Hakusensha', $this->normalizer->displayName('白泉社'));
        $this->assertSame('Shueisha', $this->normalizer->displayName('株式会社集英社'));
        $this->assertSame('Kodansha', $this->normalizer->displayName('講談社'));
        $this->assertSame('Shogakukan', $this->normalizer->displayName('小学館'));
    }

    public function testCanonicalKeyIsStableAcrossVariants(): void
    {
        $this->assertSame(
            $this->normalizer->canonicalKey('Glénat (Grenoble)'),
            $this->normalizer->canonicalKey('Glénat'),
        );
        $this->assertSame(
            $this->normalizer->canonicalKey('VIZ Media LLC'),
            $this->normalizer->canonicalKey('Viz Communications'),
        );
    }

    public function testNullAndBlankYieldNullDisplayAndEmptyKey(): void
    {
        $this->assertNull($this->normalizer->displayName(null));
        $this->assertNull($this->normalizer->displayName('   '));
        $this->assertSame('', $this->normalizer->canonicalKey(null));
    }
}
