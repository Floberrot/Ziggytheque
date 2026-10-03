<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\Scan;

use App\Manga\Application\Scan\SubmitScanCommand;
use App\Manga\Application\Scan\SubmitScanHandler;
use App\Manga\Domain\Exception\InvalidIsbnException;
use App\Manga\Domain\Exception\InvalidScanTokenException;
use App\Manga\Domain\ScanTokenIssuerInterface;
use App\Tests\Doubles\Manga\InMemoryScanResultPublisher;
use PHPUnit\Framework\TestCase;

final class SubmitScanHandlerTest extends TestCase
{
    private InMemoryScanResultPublisher $publisher;

    protected function setUp(): void
    {
        $this->publisher = new InMemoryScanResultPublisher();
    }

    private function handlerTrustingTokensOf(string $sessionId): SubmitScanHandler
    {
        $scanTokenIssuer = $this->createStub(ScanTokenIssuerInterface::class);
        $scanTokenIssuer->method('verify')->willReturn($sessionId);

        return new SubmitScanHandler($scanTokenIssuer, $this->publisher);
    }

    public function testPublishesTheScannedIsbnInItsCanonicalFormToTheSession(): void
    {
        ($this->handlerTrustingTokensOf('session-1'))(new SubmitScanCommand('scan-token', '978-2-7234-2548-3'));

        $this->assertSame([['sessionId' => 'session-1', 'isbn' => '9782723425483']], $this->publisher->published);
    }

    public function testAnIsbn10IsSentAsIsbn13(): void
    {
        ($this->handlerTrustingTokensOf('session-1'))(new SubmitScanCommand('scan-token', '2723425487'));

        $this->assertSame('9782723425483', $this->publisher->published[0]['isbn']);
    }

    public function testAnExpiredOrForgedTokenPublishesNothing(): void
    {
        $scanTokenIssuer = $this->createStub(ScanTokenIssuerInterface::class);
        $scanTokenIssuer->method('verify')->willThrowException(new InvalidScanTokenException());

        try {
            (new SubmitScanHandler($scanTokenIssuer, $this->publisher))(new SubmitScanCommand('forged', '9782723425483'));
            $this->fail('A forged token must be refused.');
        } catch (InvalidScanTokenException) {
        }

        $this->assertSame([], $this->publisher->published);
    }

    public function testAnUnreadableIsbnPublishesNothing(): void
    {
        try {
            ($this->handlerTrustingTokensOf('session-1'))(new SubmitScanCommand('scan-token', 'not-an-isbn'));
            $this->fail('An invalid ISBN must be refused.');
        } catch (InvalidIsbnException) {
        }

        $this->assertSame([], $this->publisher->published);
    }
}
