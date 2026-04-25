<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Rss;

use App\Collection\Domain\CollectionEntry;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Domain\Service\RssFeedParserException;
use App\Notification\Domain\Service\RssFeedParserInterface;
use App\Notification\Domain\Service\RssFetchResult;
use DateTimeImmutable;
use DateTimeInterface;
use SimpleXMLElement;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RssFeedParser implements RssFeedParserInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ArticleRepositoryInterface $articleRepository,
    ) {
    }

    public function parse(string $feedUrl, string $mangaTitle, CollectionEntry $entry): RssFetchResult
    {
        $response   = $this->httpClient->request('GET', $feedUrl, [
            'timeout' => 10,
            'headers' => ['User-Agent' => 'Ziggytheque/1.0 (manga tracker)'],
        ]);
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw RssFeedParserException::httpError($statusCode);
        }

        $content = $response->getContent(false);

        // Strip UTF-8 BOM if present — some feeds include it and simplexml rejects it
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Reject non-XML payloads early (HTML error pages, Cloudflare challenges, etc.)
        $trimmed = ltrim($content);
        $lower   = strtolower($trimmed);
        $isHtml = str_starts_with($lower, '<!doctype') || str_starts_with($lower, '<html');
        if (!str_starts_with($trimmed, '<') || $isHtml) {
            throw RssFeedParserException::invalidXml($feedUrl);
        }

        libxml_use_internal_errors(true);
        $flags = LIBXML_RECOVER | LIBXML_NOERROR | LIBXML_NOWARNING;
        $xml   = simplexml_load_string($content, SimpleXMLElement::class, $flags);
        libxml_clear_errors();

        if ($xml === false) {
            throw RssFeedParserException::invalidXml($feedUrl);
        }

        return $this->processItems($xml, $mangaTitle, $entry);
    }

    private function processItems(SimpleXMLElement $xml, string $mangaTitle, CollectionEntry $entry): RssFetchResult
    {
        $newCount        = 0;
        $itemsScanned    = 0;
        $normalizedTitle = mb_strtolower($mangaTitle);
        $keywords        = array_filter(
            array_map('trim', explode(' ', $normalizedTitle)),
            static fn (string $k) => mb_strlen($k) >= 3,
        );
        $channel = $xml->channel ?? $xml;

        foreach ($channel->item ?? [] as $item) {
            ++$itemsScanned;

            $itemTitle = html_entity_decode((string) ($item->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $itemDesc  = html_entity_decode(
                strip_tags((string) ($item->description ?? '')),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
            $itemUrl  = (string) ($item->link ?? $item->guid ?? '');
            $itemDate = (string) ($item->pubDate ?? $item->children('dc', true)->date ?? '');

            $haystack   = mb_strtolower($itemTitle . ' ' . $itemDesc);
            $titleMatch = str_contains($haystack, $normalizedTitle);
            $matches    = $titleMatch
                ? [$normalizedTitle]
                : array_filter($keywords, static fn (string $k) => str_contains($haystack, $k));

            if (empty($matches) || $itemUrl === '') {
                continue;
            }

            $publishedAt = $itemDate !== ''
                ? DateTimeImmutable::createFromFormat(DateTimeInterface::RSS, $itemDate) ?: null
                : null;

            if ($publishedAt !== null && $publishedAt < new DateTimeImmutable('2026-04-01')) {
                continue;
            }

            if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $itemUrl)) {
                continue;
            }

            $article = new Article(
                id: Uuid::v4()->toRfc4122(),
                collectionEntry: $entry,
                title: mb_substr($itemTitle, 0, 500),
                url: $itemUrl,
                sourceName: 'rss',
                author: null,
                imageUrl: $this->extractImage($item),
                publishedAt: $publishedAt ?: null,
                snippet: $this->extractSnippet($itemDesc, array_values($matches)),
            );
            $this->articleRepository->save($article);
            ++$newCount;
        }

        return new RssFetchResult($newCount, $itemsScanned);
    }

    /** @param string[] $keywords */
    private function extractSnippet(string $text, array $keywords): ?string
    {
        if ($text === '' || $keywords === []) {
            return null;
        }
        $lower   = mb_strtolower($text);
        $bestPos = PHP_INT_MAX;
        $best    = null;
        foreach ($keywords as $kw) {
            $pos = mb_strpos($lower, $kw);
            if ($pos !== false && $pos < $bestPos) {
                $bestPos = $pos;
                $best    = $kw;
            }
        }
        if ($best === null) {
            return mb_substr($text, 0, 200);
        }
        $start   = max(0, $bestPos - 80);
        $excerpt = mb_substr($text, $start, 220);
        if ($start > 0) {
            $excerpt = '…' . ltrim($excerpt);
        }

        return rtrim($excerpt) . (mb_strlen($text) > $start + 220 ? '…' : '');
    }

    private function extractImage(SimpleXMLElement $item): ?string
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
