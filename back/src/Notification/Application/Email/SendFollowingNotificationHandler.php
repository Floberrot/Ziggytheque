<?php

declare(strict_types=1);

namespace App\Notification\Application\Email;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final readonly class SendFollowingNotificationHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $notificationEmail,
    ) {}

    public function __invoke(SendFollowingNotificationMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        if (
            $entry->lastNotifiedAt !== null
            && $entry->lastNotifiedAt > new \DateTimeImmutable('-12 hours')
        ) {
            $this->logger->info('Email skipped (cooldown)', ['manga' => $entry->manga->title]);
            return;
        }

        $result   = $this->articleRepository->findPaginated(1, 10, $message->collectionEntryId);
        $articles = array_map(static fn ($a) => $a->toArray(), $result['items']);

        if (empty($articles)) {
            return;
        }

        $html = $this->twig->render('emails/new_articles_notification.html.twig', [
            'manga'    => $entry->manga,
            'articles' => $articles,
        ]);

        $email = (new Email())
            ->from('ziggytheque@noreply.local')
            ->to($this->notificationEmail)
            ->subject('📰 Nouveautés : ' . $entry->manga->title)
            ->html($html);

        $this->mailer->send($email);

        $entry->lastNotifiedAt = new \DateTimeImmutable();
        $this->collectionRepository->save($entry);

        $this->logger->info('Notification email sent', ['manga' => $entry->manga->title]);
    }
}
