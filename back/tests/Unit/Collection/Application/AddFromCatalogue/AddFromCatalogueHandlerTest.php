<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\AddFromCatalogue;

use App\Collection\Application\AddFromCatalogue\AddFromCatalogueCommand;
use App\Collection\Application\AddFromCatalogue\AddFromCatalogueHandler;
use App\Collection\Shared\Event\AddFromCatalogueFailedEvent;
use App\Collection\Shared\Event\AddFromCatalogueStartedEvent;
use App\Collection\Shared\Event\AddFromCatalogueSucceededEvent;
use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use App\Tests\Unit\Collection\Application\CatalogueHandlerTestCase;
use RuntimeException;

final class AddFromCatalogueHandlerTest extends CatalogueHandlerTestCase
{
    private function handler(): AddFromCatalogueHandler
    {
        return new AddFromCatalogueHandler($this->registrar, $this->userRepository, $this->currentUserProvider, $this->eventBus);
    }

    private function edition(): CatalogueEdition
    {
        return new CatalogueEdition('Berserk', 'Glénat', 'Prestige', 'Kentaro Miura', null, 3, []);
    }

    public function testRegistersTheTomesAndPublishesTheLifecycle(): void
    {
        $registration = ($this->handler())(new AddFromCatalogueCommand($this->edition(), [1, 3]));

        $this->assertTrue($registration->seriesCreated);
        $this->assertSame([1, 3], $registration->addedNumbers);
        $this->assertSame(
            [AddFromCatalogueStartedEvent::class, AddFromCatalogueSucceededEvent::class],
            $this->publishedEventClasses(),
        );

        $started = $this->publishedEvents[0];
        $succeeded = $this->publishedEvents[1];
        $this->assertInstanceOf(AddFromCatalogueStartedEvent::class, $started);
        $this->assertInstanceOf(AddFromCatalogueSucceededEvent::class, $succeeded);
        $this->assertSame([1, 3], $started->volumeNumbers);
        $this->assertSame($started->correlationId, $succeeded->correlationId);
        $this->assertSame($registration->mangaId, $succeeded->mangaId);
        $this->assertTrue($succeeded->seriesCreated);
    }

    public function testPublishesAFailedEventAndRethrows(): void
    {
        $this->currentUserProvider = $this->createStub(CurrentUserProviderInterface::class);
        $this->currentUserProvider->method('currentUserId')->willThrowException(new RuntimeException('no user'));

        try {
            ($this->handler())(new AddFromCatalogueCommand($this->edition(), [1]));
            $this->fail('The handler must rethrow.');
        } catch (RuntimeException $exception) {
            $this->assertSame('no user', $exception->getMessage());
        }

        $this->assertSame(
            [AddFromCatalogueStartedEvent::class, AddFromCatalogueFailedEvent::class],
            $this->publishedEventClasses(),
        );
        $failed = $this->publishedEvents[1];
        $this->assertInstanceOf(AddFromCatalogueFailedEvent::class, $failed);
        $this->assertSame(RuntimeException::class, $failed->exceptionClass);
        $this->assertSame('Berserk', $failed->workTitle);
    }
}
