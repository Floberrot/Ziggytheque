<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\RetailerEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RetailerEnumTest extends TestCase
{
    public function testTargetsListsTheThreeShopsInOrder(): void
    {
        $this->assertSame(
            [RetailerEnum::Amazon, RetailerEnum::Fnac, RetailerEnum::Ebay],
            RetailerEnum::targets(),
        );
    }

    /** @return iterable<string, array{string, RetailerEnum}> */
    public static function merchantMappingProvider(): iterable
    {
        yield 'exact lowercase'          => ['amazon', RetailerEnum::Amazon];
        yield 'capitalized'              => ['Amazon', RetailerEnum::Amazon];
        yield 'domain suffix'            => ['Amazon.fr', RetailerEnum::Amazon];
        yield 'marketplace suffix'       => ['Amazon Marketplace', RetailerEnum::Amazon];
        yield 'uppercase with accent'    => ['AMAZÔN', RetailerEnum::Amazon];
        yield 'fnac exact'               => ['Fnac', RetailerEnum::Fnac];
        yield 'fnac dot com'             => ['Fnac.com', RetailerEnum::Fnac];
        yield 'fnac with article'        => ['La Fnac', RetailerEnum::Fnac];
        yield 'ebay camel case'          => ['eBay', RetailerEnum::Ebay];
        yield 'ebay with country'        => ['eBay France', RetailerEnum::Ebay];
        yield 'ebay uppercase'           => ['EBAY', RetailerEnum::Ebay];
        yield 'surrounding whitespace'   => ['  amazon  ', RetailerEnum::Amazon];
    }

    #[DataProvider('merchantMappingProvider')]
    public function testFromMerchantMapsKnownNames(string $merchant, RetailerEnum $expected): void
    {
        $this->assertSame($expected, RetailerEnum::fromMerchant($merchant));
    }

    /** @return iterable<string, array{string}> */
    public static function unknownMerchantProvider(): iterable
    {
        yield 'other merchant'      => ['Rakuten'];
        yield 'second-hand shop'    => ['momox'];
        yield 'publisher reference' => ['Google Play'];
        yield 'contains not prefix' => ['Librairie Amazonie'];
        yield 'empty'               => [''];
        yield 'whitespace only'     => ['   '];
    }

    #[DataProvider('unknownMerchantProvider')]
    public function testFromMerchantReturnsNullForUnknownNames(string $merchant): void
    {
        $this->assertNull(RetailerEnum::fromMerchant($merchant));
    }

    public function testLabels(): void
    {
        $this->assertSame('Amazon', RetailerEnum::Amazon->label());
        $this->assertSame('Fnac', RetailerEnum::Fnac->label());
        $this->assertSame('eBay', RetailerEnum::Ebay->label());
    }
}
