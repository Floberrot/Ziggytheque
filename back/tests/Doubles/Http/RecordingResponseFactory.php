<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Http;

use Generator;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Response factory for a MockHttpClient that records, per source, when its request is
 * sent and when its answer is first waited on — a MockResponse only reads its body
 * once the caller waits for it. Proves a caller starts every request before it reads
 * any: the sources then answer concurrently.
 *
 * Responses are picked by the first registered URL fragment the request URL contains.
 */
final class RecordingResponseFactory
{
    /** @var list<string> "sent <source>" / "read <source>", in the order they happened */
    public array $events = [];

    /** @var array<string, array{source: string, body: string, info: array<string, mixed>}> */
    private array $responses = [];

    /** @var array<string, string> URL fragment → source whose request cannot even be sent */
    private array $refusedRequests = [];

    /** @param array<string, mixed> $info MockResponse info (http_code, response_headers, error…) */
    public function answer(string $urlFragment, string $source, string $body, array $info = []): self
    {
        $this->responses[$urlFragment] = ['source' => $source, 'body' => $body, 'info' => $info];

        return $this;
    }

    /** request() itself throws for this URL, as for an invalid one. */
    public function refuse(string $urlFragment, string $source): self
    {
        $this->refusedRequests[$urlFragment] = $source;

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        foreach ($this->refusedRequests as $urlFragment => $source) {
            if (str_contains($url, $urlFragment)) {
                $this->events[] = 'refused ' . $source;

                throw new TransportException('Unsupported URL for ' . $source);
            }
        }

        foreach ($this->responses as $urlFragment => $response) {
            if (str_contains($url, $urlFragment)) {
                $this->events[] = 'sent ' . $response['source'];

                return new MockResponse($this->recordRead($response['source'], $response['body']), $response['info']);
            }
        }

        $this->events[] = 'sent unexpected ' . $method . ' ' . $url;

        return new MockResponse('', ['http_code' => 404]);
    }

    /** @return list<string> the sources, in the order their requests were sent */
    public function sentSources(): array
    {
        return $this->sourcesOf('sent ');
    }

    /** @return list<string> the sources, in the order their answers were read */
    public function readSources(): array
    {
        return $this->sourcesOf('read ');
    }

    /** True when every request listed was sent before any answer was read. */
    public function sentBeforeAnyRead(string ...$sources): bool
    {
        $firstRead = null;
        foreach ($this->events as $position => $event) {
            if (str_starts_with($event, 'read ')) {
                $firstRead = $position;
                break;
            }
        }

        foreach ($sources as $source) {
            $sentAt = array_search('sent ' . $source, $this->events, true);
            if ($sentAt === false || ($firstRead !== null && $sentAt > $firstRead)) {
                return false;
            }
        }

        return true;
    }

    /** @return Generator<string> */
    private function recordRead(string $source, string $body): Generator
    {
        $this->events[] = 'read ' . $source;

        if ($body !== '') {
            yield $body;
        }
    }

    /** @return list<string> */
    private function sourcesOf(string $prefix): array
    {
        $sources = [];
        foreach ($this->events as $event) {
            if (str_starts_with($event, $prefix)) {
                $sources[] = substr($event, strlen($prefix));
            }
        }

        return $sources;
    }
}
