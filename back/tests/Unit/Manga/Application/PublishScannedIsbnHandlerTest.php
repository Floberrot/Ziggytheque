<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application;

use App\Manga\Application\PublishScannedIsbn\PublishScannedIsbnCommand;
use App\Manga\Application\PublishScannedIsbn\PublishScannedIsbnHandler;
use App\Manga\Domain\Exception\InvalidIsbnException;
use App\Tests\Doubles\Manga\InMemoryScanSessionPublisher;
use PHPUnit\Framework\TestCase;

final class PublishScannedIsbnHandlerTest extends TestCase
{
    private InMemoryScanSessionPublisher $publisher;
    private PublishScannedIsbnHandler $handler;

    protected function setUp(): void
    {
        $this->publisher = new InMemoryScanSessionPublisher();
        $this->handler   = new PublishScannedIsbnHandler($this->publisher);
    }

    public function testPublishesCanonicalIsbnToSession(): void
    {
        ($this->handler)(new PublishScannedIsbnCommand(
            sessionId: 'session-abc',
            isbn: '9782344020814',
        ));

        $this->assertCount(1, $this->publisher->published);
        $this->assertSame('session-abc', $this->publisher->published[0]['sessionId']);
        $this->assertSame('9782344020814', $this->publisher->published[0]['isbn']);
    }

    public function testThrowsOnInvalidIsbn(): void
    {
        $this->expectException(InvalidIsbnException::class);

        ($this->handler)(new PublishScannedIsbnCommand(
            sessionId: 'session-abc',
            isbn: '123-invalid',
        ));
    }

    public function testNothingPublishedOnInvalidIsbn(): void
    {
        try {
            ($this->handler)(new PublishScannedIsbnCommand(
                sessionId: 'session-abc',
                isbn: '000',
            ));
        } catch (InvalidIsbnException) {
        }

        $this->assertCount(0, $this->publisher->published);
    }
}
