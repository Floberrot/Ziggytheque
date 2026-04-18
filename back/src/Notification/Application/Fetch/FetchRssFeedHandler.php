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
final readonly class FetchRssFeedHandler
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository,
        private ActivityLogRepositoryInterface $activityLogRepository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(FetchRssFeedMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            collectionEntry: $entry,
            sourceType: 'rss',
            sourceName: $message->feedName,
        );
        $this->activityLogRepository->save($log);

        try {
            $response = $this->httpClient->request('GET', $message->feedUrl, [
                'timeout' => 10,
                'headers' => ['User-Agent' => 'Ziggytheque/1.0 (manga tracker)'],
            ]);
            $xml = new \SimpleXMLElement($response->getContent());

            $newCount = 0;
            $keywords = array_filter(array_map('trim', explode(' ', mb_strtolower($message->mangaTitle))));
            $channel  = $xml->channel ?? $xml; // RSS 2.0 vs Atom

            foreach ($channel->item ?? [] as $item) {
                $itemTitle = (string) ($item->title ?? '');
                $itemDesc  = strip_tags((string) ($item->description ?? ''));
                $itemUrl   = (string) ($item->link ?? $item->guid ?? '');
                $itemDate  = (string) ($item->pubDate ?? $item->children('dc', true)->date ?? '');

                $haystack = mb_strtolower($itemTitle . ' ' . $itemDesc);
                $matches  = array_filter($keywords, static fn (string $k) => mb_strlen($k) > 3 && str_contains($haystack, $k));

                if (empty($matches) || empty($itemUrl)) {
                    continue;
                }

                if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $itemUrl)) {
                    continue;
                }

                $imageUrl    = $this->extractImage($item);
                $publishedAt = $itemDate !== '' ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::RSS, $itemDate) ?: null : null;

                $article = new Article(
                    id: Uuid::v4()->toRfc4122(),
                    collectionEntry: $entry,
                    title: mb_substr($itemTitle, 0, 500),
                    url: $itemUrl,
                    sourceName: $message->feedName,
                    author: null,
                    imageUrl: $imageUrl,
                    publishedAt: $publishedAt ?: null,
                );
                $this->articleRepository->save($article);
                ++$newCount;
            }

            $log->markSuccess($newCount);
            $this->activityLogRepository->save($log);
            $this->logger->info('RSS feed fetched', ['feed' => $message->feedName, 'manga' => $message->mangaTitle, 'new' => $newCount]);
        } catch (\Throwable $e) {
            $log->markError($e->getMessage());
            $this->activityLogRepository->save($log);
            $this->logger->error('RSS feed failed', ['feed' => $message->feedName, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function extractImage(\SimpleXMLElement $item): ?string
    {
        $media = $item->children('media', true);
        if (isset($media->content)) {
            $url = (string) $media->content->attributes()['url'];
            if ($url !== '') {
                return $url;
            }
        }

        if (isset($item->enclosure)) {
            $type = (string) $item->enclosure->attributes()['type'];
            if (str_starts_with($type, 'image/')) {
                return (string) $item->enclosure->attributes()['url'];
            }
        }

        $desc = (string) ($item->description ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $desc, $m)) {
            return $m[1];
        }

        return null;
    }
}
