<?php

declare(strict_types=1);

namespace App\Notification\Application\Discord;

use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Domain\DiscordNotifierInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendSchedulerDiscordSummaryHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private DiscordNotifierInterface $discord,
    ) {
    }

    public function __invoke(SendSchedulerDiscordSummaryMessage $message): void
    {
        $articles = $this->articleRepository->findCreatedSince($message->scheduledAt);

        if ($articles === []) {
            return;
        }

        $byManga = [];
        foreach ($articles as $article) {
            $title = $article->collectionEntry->manga->title;
            if (!isset($byManga[$title])) {
                $byManga[$title] = [
                    'mangaTitle'    => $title,
                    'mangaCoverUrl' => $article->collectionEntry->manga->coverUrl,
                    'articles'      => [],
                ];
            }
            $byManga[$title]['articles'][] = ['title' => $article->title, 'url' => $article->url];
        }

        $this->discord->sendSchedulerSummary(array_values($byManga));
    }
}
