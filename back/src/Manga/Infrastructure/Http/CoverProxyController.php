<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class CoverProxyController
{
    /**
     * Allowlisted image hosts → the Referer that makes them serve the real image
     * instead of an anti-hotlink placeholder. A host absent from this map may
     * never be fetched, so the map doubles as the SSRF allowlist.
     *
     * Matching is on the exact, lowercased host of the parsed URL — never on a
     * prefix of the URL string, which lookalike hosts such as
     * `books.google.com.attacker.example` would satisfy.
     */
    private const array ALLOWED_HOSTS = [
        'books.google.com'            => 'https://books.google.com/',
        'books.google.fr'             => 'https://books.google.com/',
        'books.google.de'             => 'https://books.google.com/',
        'books.google.es'             => 'https://books.google.com/',
        'books.google.it'             => 'https://books.google.com/',
        'books.google.co.uk'          => 'https://books.google.com/',
        'books.google.co.jp'          => 'https://books.google.com/',
        'books.googleusercontent.com' => 'https://books.google.com/',
        'uploads.mangadex.org'        => 'https://mangadex.org/',
        'catalogue.bnf.fr'            => 'https://catalogue.bnf.fr/',
    ];

    /**
     * Hosts that answer "no cover" with a small placeholder image instead of a 404:
     * below this many bytes the image is that placeholder, and is reported as missing.
     */
    private const array MIN_BYTES_BY_HOST = [
        'catalogue.bnf.fr' => 2000,
    ];

    /**
     * Raster formats only: an SVG served from our own origin could run script next to
     * the SPA (and its token).
     *
     * @var list<string>
     */
    private const array ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

    /** Sent with every proxied image: never rendered as a document, never sniffed. */
    private const array IMAGE_SECURITY_HEADERS = [
        'Content-Security-Policy' => "default-src 'none'; sandbox",
        'X-Content-Type-Options'  => 'nosniff',
    ];

    /** A missing cover stays missing for a while: no point asking upstream on every render. */
    private const string MISSING_COVER_CACHE = 'public, max-age=86400';

    /** Upstream may redirect, but every hop is re-validated against the allowlist. */
    private const int MAX_REDIRECTS = 3;

    /** Covers are small; anything larger is not an image we want to buffer. */
    private const int MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Public endpoint, and every call fetches from a third party: capped per client IP.
     * Generous on purpose — a collection grid loads a hundred covers or more at once, the
     * browser keeps them a week, and a refused cover only falls back to an icon.
     */
    public const int REQUESTS_PER_IP = 600;
    public const int REQUESTS_WINDOW_SECONDS = 60;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private CacheRateLimiter $rateLimiter,
    ) {
    }

    #[Route('/proxy/cover', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $this->rateLimiter->consume(
            'cover_proxy_ip:' . ($request->getClientIp() ?? 'unknown'),
            self::REQUESTS_PER_IP,
            self::REQUESTS_WINDOW_SECONDS,
        );

        $url = (string) $request->query->get('url', '');

        if ($this->refererFor($url) === null) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        // Redirects are followed by hand so each Location is checked against the
        // allowlist too — Symfony's own follower would happily land on any host.
        $currentUrl = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $referer = $this->refererFor($currentUrl);

            if ($referer === null) {
                $this->logger->warning('CoverProxy: redirect left the allowlist', [
                    'url'      => $url,
                    'redirect' => $currentUrl,
                ]);

                return new Response('', Response::HTTP_BAD_REQUEST);
            }

            $response = $this->httpClient->request('GET', $currentUrl, [
                'headers' => [
                    'Referer' => $referer,
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko)'
                        . ' Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                ],
                'max_redirects' => 0,
            ]);

            $status  = $response->getStatusCode();
            $headers = $response->getHeaders(false);

            if ($status >= 300 && $status < 400) {
                $location = $headers['location'][0] ?? null;

                if ($location === null) {
                    break;
                }

                $currentUrl = $this->resolveLocation($currentUrl, $location);
                continue;
            }

            $contentType = $headers['content-type'][0] ?? '';
            $mediaType   = strtolower(trim(explode(';', $contentType)[0]));

            if ($status !== 200 || !in_array($mediaType, self::ALLOWED_IMAGE_TYPES, true)) {
                $this->logger->warning('CoverProxy: upstream failed', [
                    'url'          => $url,
                    'status'       => $status,
                    'content_type' => $contentType,
                ]);

                return $this->missingCover();
            }

            $body = $this->readCapped($response);

            if ($body === null) {
                $this->logger->warning('CoverProxy: upstream body exceeded the size cap', [
                    'url'   => $url,
                    'limit' => self::MAX_BYTES,
                ]);

                return new Response('', Response::HTTP_NOT_FOUND);
            }

            if (strlen($body) < $this->minimumBytesFor($currentUrl)) {
                return $this->missingCover();
            }

            return new Response(
                $body,
                Response::HTTP_OK,
                [
                    'Content-Type' => $mediaType,
                    'Cache-Control' => 'public, max-age=604800',
                    ...self::IMAGE_SECURITY_HEADERS,
                ],
            );
        }

        $this->logger->warning('CoverProxy: redirect chain ended without an image', [
            'url'           => $url,
            'max_redirects' => self::MAX_REDIRECTS,
        ]);

        return new Response('', Response::HTTP_NOT_FOUND);
    }

    /**
     * Buffers the body but stops as soon as it grows past the cap, so a hostile
     * or misbehaving upstream cannot exhaust PHP's memory limit.
     *
     * @return string|null null when the cap was exceeded.
     */
    private function readCapped(ResponseInterface $response): ?string
    {
        $body = '';

        foreach ($this->httpClient->stream($response) as $chunk) {
            $body .= $chunk->getContent();

            if (strlen($body) > self::MAX_BYTES) {
                $response->cancel();

                return null;
            }
        }

        return $body;
    }

    /**
     * Resolves a possibly-relative Location header against the URL it came from.
     * A relative hop keeps the current host, which is allowlisted by construction;
     * an absolute one is re-checked by the caller before it is fetched.
     */
    private function resolveLocation(string $currentUrl, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts  = parse_url($currentUrl);
        $host   = $parts['host'] ?? '';
        $origin = 'https://' . $host;

        if (str_starts_with($location, '//')) {
            return 'https:' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $basePath = $parts['path'] ?? '/';
        $basePath = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);

        return $origin . $basePath . $location;
    }

    private function missingCover(): Response
    {
        return new Response('', Response::HTTP_NOT_FOUND, ['Cache-Control' => self::MISSING_COVER_CACHE]);
    }

    private function minimumBytesFor(string $url): int
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return self::MIN_BYTES_BY_HOST[$host] ?? 1;
    }

    /** The Referer to send for $url, or null when the URL is not allowlisted. */
    private function refererFor(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) {
            return null;
        }

        // A userinfo component lets `https://books.google.com@attacker.example/`
        // read as allowlisted to a human while resolving elsewhere.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return self::ALLOWED_HOSTS[strtolower($parts['host'])] ?? null;
    }
}
