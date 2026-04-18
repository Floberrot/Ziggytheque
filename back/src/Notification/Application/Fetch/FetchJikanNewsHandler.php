<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class FetchJikanNewsHandler
{
    private const BASE_URL = 'https://api.jikan.moe/v4';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository,
        private ActivityLogRepositoryInterface $activityLogRepository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(FetchJikanNewsMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            collectionEntry: $entry,
            sourceType: 'jikan',
            sourceName: 'jikan-news',
        );
        $this->activityLogRepository->save($log);

        try {
            $response = $this->httpClient->request(
                'GET',
                self::BASE_URL . '/manga/' . $message->malId . '/news',
                ['timeout' => 10],
            );
            $data  = $response->toArray();
            $items = $data['data'] ?? [];

            $newCount = 0;
            foreach ($items as $item) {
                $url = $item['url'] ?? null;
                if ($url === null) {
                    continue;
                }

                if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $url)) {
                    continue;
                }

                $publishedAt = isset($item['date'])
                    ? new \DateTimeImmutable($item['date'])
                    : null;

                $article = new Article(
                    id: Uuid::v4()->toRfc4122(),
                    collectionEntry: $entry,
                    title: mb_substr((string) ($item['title'] ?? 'Jikan News'), 0, 500),
                    url: $url,
                    sourceName: 'jikan-news',
                    author: $item['author_username'] ?? null,
                    imageUrl: $item['images']['jpg']['image_url'] ?? null,
                    publishedAt: $publishedAt,
                );
                $this->articleRepository->save($article);
                ++$newCount;
            }

            $log->markSuccess($newCount);
            $this->activityLogRepository->save($log);
            $this->logger->info('Jikan news fetched', ['malId' => $message->malId, 'manga' => $message->mangaTitle, 'new' => $newCount]);
        } catch (\Throwable $e) {
            $log->markError($e->getMessage());
            $this->activityLogRepository->save($log);
            $this->logger->error('Jikan news failed', ['malId' => $message->malId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
