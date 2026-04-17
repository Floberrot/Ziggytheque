<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Infrastructure\Http\ExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

class ExceptionListenerTest extends TestCase
{
    private ExceptionListener $listener;

    protected function setUp(): void
    {
        $this->listener = new ExceptionListener();
    }

    public function testSetsJsonResponseForDomainException(): void
    {
        $exception = new NotFoundException('Manga', 'abc');
        $event = $this->makeEvent($exception);

        ($this->listener)($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('Manga with id "abc" not found.', $body['error']);
    }

    public function testUnwrapsHandlerFailedExceptionAndSetsResponse(): void
    {
        $domain = new NotFoundException('Volume', 'v-1');
        $wrapped = new HandlerFailedException(new \Symfony\Component\Messenger\Envelope(new \stdClass()), [$domain]);
        $event = $this->makeEvent($wrapped);

        ($this->listener)($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testIgnoresNonDomainException(): void
    {
        $event = $this->makeEvent(new \RuntimeException('internal'));

        ($this->listener)($event);

        $this->assertNull($event->getResponse());
    }

    private function makeEvent(\Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
