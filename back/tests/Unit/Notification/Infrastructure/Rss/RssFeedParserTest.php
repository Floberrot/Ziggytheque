<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Rss;

use App\Notification\Domain\Service\RssFeedParserException;
use App\Notification\Infrastructure\Rss\RssFeedParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RssFeedParserTest extends TestCase
{
    private const string FEED_URL = 'https://news.example/feed';

    private const string FEED = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:dc="http://purl.org/dc/elements/1.1/">
          <channel>
            <title>News</title>
            <item>
              <title>One Piece &amp; Naruto : l&#039;actu</title>
              <link>https://news.example/one-piece</link>
              <description><![CDATA[<p>Le tome <b>110</b> arrive &eacute;t&eacute; 2026.</p>]]></description>
              <pubDate>Fri, 01 May 2026 10:00:00 +0200</pubDate>
              <media:content url="https://news.example/media.jpg" medium="image"/>
            </item>
            <item>
              <title>Berserk</title>
              <guid>https://news.example/berserk</guid>
              <dc:date>not an RSS date</dc:date>
              <enclosure url="https://news.example/enclosure.jpg" type="image/jpeg" length="1000"/>
            </item>
            <item>
              <title>Naruto</title>
              <link>https://news.example/naruto</link>
              <description><![CDATA[<img src="https://news.example/inline.png"> Boruto aussi.]]></description>
              <enclosure url="https://news.example/episode.mp3" type="audio/mpeg" length="1000"/>
            </item>
          </channel>
        </rss>
        XML;

    /** @var list<array{string, string}> */
    private array $requests = [];

    public function testReadsEveryItemOfTheFeed(): void
    {
        $feedItems = $this->parser(new MockResponse(self::FEED))->parse(self::FEED_URL);

        $this->assertCount(3, $feedItems);

        $this->assertSame("One Piece & Naruto : l'actu", $feedItems[0]->title);
        $this->assertSame('Le tome 110 arrive été 2026.', $feedItems[0]->description);
        $this->assertSame('https://news.example/one-piece', $feedItems[0]->url);
        $this->assertSame('2026-05-01T10:00:00+02:00', $feedItems[0]->publishedAt?->format(DATE_ATOM));
        $this->assertSame('https://news.example/media.jpg', $feedItems[0]->imageUrl);

        // No link: the guid. An unreadable date is no date. An image enclosure is the image.
        $this->assertSame('https://news.example/berserk', $feedItems[1]->url);
        $this->assertNull($feedItems[1]->publishedAt);
        $this->assertSame('https://news.example/enclosure.jpg', $feedItems[1]->imageUrl);
        $this->assertSame('', $feedItems[1]->description);

        // An audio enclosure is not an image: the description's first <img> is.
        $this->assertSame('https://news.example/inline.png', $feedItems[2]->imageUrl);
    }

    public function testDownloadsTheFeedOnceWithItsOwnUserAgent(): void
    {
        $this->parser(new MockResponse(self::FEED))->parse(self::FEED_URL);

        $this->assertSame([['GET', self::FEED_URL]], $this->requests);
    }

    public function testAcceptsAFeedStartingWithAByteOrderMark(): void
    {
        $feedItems = $this->parser(new MockResponse("\xEF\xBB\xBF" . self::FEED))->parse(self::FEED_URL);

        $this->assertCount(3, $feedItems);
    }

    public function testAnAtomStyleDocumentWithoutChannelStillReadsItsItems(): void
    {
        $document = '<rdf><item><title>One Piece</title><link>https://news.example/a</link></item></rdf>';

        $feedItems = $this->parser(new MockResponse($document))->parse(self::FEED_URL);

        $this->assertCount(1, $feedItems);
        $this->assertSame('https://news.example/a', $feedItems[0]->url);
        $this->assertNull($feedItems[0]->imageUrl);
    }

    public function testAnEmptyChannelHasNoItem(): void
    {
        $feedItems = $this->parser(new MockResponse('<rss><channel><title>Quiet</title></channel></rss>'))
            ->parse(self::FEED_URL);

        $this->assertSame([], $feedItems);
    }

    public function testAnErrorStatusFailsTheFeed(): void
    {
        $this->expectException(RssFeedParserException::class);
        $this->expectExceptionMessage('HTTP 503');

        $this->parser(new MockResponse('down', ['http_code' => 503]))->parse(self::FEED_URL);
    }

    #[DataProvider('notFeeds')]
    public function testSomethingThatIsNotAFeedFailsIt(string $body): void
    {
        $this->expectException(RssFeedParserException::class);
        $this->expectExceptionMessage('Invalid XML from ' . self::FEED_URL);

        $this->parser(new MockResponse($body))->parse(self::FEED_URL);
    }

    /** @return iterable<string, array{string}> */
    public static function notFeeds(): iterable
    {
        yield 'html page'          => ['<!DOCTYPE html><html><body>Just a moment…</body></html>'];
        yield 'html without type'  => ['<html><body>Error</body></html>'];
        yield 'json'               => ['{"error": "nope"}'];
        yield 'empty'              => [''];
        yield 'broken beyond repair' => ['<'];
    }

    private function parser(MockResponse $response): RssFeedParser
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->requests[] = [$method, $url];
            $this->assertContains('User-Agent: Ziggytheque/1.0 (manga tracker)', $options['headers']);

            return $response;
        });

        return new RssFeedParser($httpClient);
    }
}
