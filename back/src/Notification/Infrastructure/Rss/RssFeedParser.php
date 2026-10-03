<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Rss;

use App\Notification\Domain\Service\RssFeedItem;
use App\Notification\Domain\Service\RssFeedParserException;
use App\Notification\Domain\Service\RssFeedParserInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Error;
use SimpleXMLElement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads an RSS feed and reads its items; matching them against the followed series
 * is {@see \App\Notification\Domain\Service\RssArticleCollector}'s job, once per series.
 */
final readonly class RssFeedParser implements RssFeedParserInterface
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function parse(string $feedUrl): array
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
        $isHtml  = str_starts_with($lower, '<!doctype') || str_starts_with($lower, '<html');
        if (!str_starts_with($trimmed, '<') || $isHtml) {
            throw RssFeedParserException::invalidXml($feedUrl);
        }

        libxml_use_internal_errors(true);
        $flags = LIBXML_RECOVER | LIBXML_NOERROR | LIBXML_NOWARNING;
        $xml   = simplexml_load_string($content, SimpleXMLElement::class, $flags);
        libxml_clear_errors();

        if ($xml === false || !$this->hasRootElement($xml)) {
            throw RssFeedParserException::invalidXml($feedUrl);
        }

        return $this->readItems($xml);
    }

    /** LIBXML_RECOVER can hand back an element with nothing behind it (a bare "<"). */
    private function hasRootElement(SimpleXMLElement $xml): bool
    {
        try {
            return $xml->getName() !== '';
        } catch (Error) {
            return false;
        }
    }

    /** @return list<RssFeedItem> */
    private function readItems(SimpleXMLElement $xml): array
    {
        $feedItems = [];
        $channel   = $xml->channel ?? $xml;

        foreach ($channel->item ?? [] as $item) {
            $itemDate = (string) ($item->pubDate ?? $item->children('dc', true)->date ?? '');

            $feedItems[] = new RssFeedItem(
                title: html_entity_decode((string) ($item->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                description: html_entity_decode(
                    strip_tags((string) ($item->description ?? '')),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                ),
                url: (string) ($item->link ?? $item->guid ?? ''),
                publishedAt: $itemDate !== ''
                    ? (DateTimeImmutable::createFromFormat(DateTimeInterface::RSS, $itemDate) ?: null)
                    : null,
                imageUrl: $this->extractImage($item),
            );
        }

        return $feedItems;
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
        $description = (string) ($item->description ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $description, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
