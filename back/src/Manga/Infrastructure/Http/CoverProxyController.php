<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CoverProxyController
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/proxy/cover', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $url = (string) $request->query->get('url', '');
        $referer = $this->refererFor($url);

        // Reject anything outside the allowlist (also acts as SSRF protection).
        if ($referer === null) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Referer' => $referer,
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko)'
                    . ' Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            ],
            'max_redirects' => 5,
        ]);

        $status = $response->getStatusCode();
        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';

        if ($status !== 200 || !str_starts_with($contentType, 'image/')) {
            $this->logger->warning('CoverProxy: upstream failed', [
                'url' => $url,
                'status' => $status,
                'content_type' => $contentType,
            ]);

            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response(
            $response->getContent(),
            Response::HTTP_OK,
            [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=604800',
            ],
        );
    }

    /**
     * Allowlisted image hosts → the Referer that makes them serve the real image
     * instead of an anti-hotlink placeholder. Returning null rejects the URL, so
     * this doubles as the SSRF allowlist (only these hosts may ever be fetched).
     */
    private function refererFor(string $url): ?string
    {
        return match (true) {
            $url === '' => null,
            preg_match('#^https://books\.google[a-z.]*/#', $url) === 1 => 'https://books.google.com/',
            preg_match('#^https://uploads\.mangadex\.org/#', $url) === 1 => 'https://mangadex.org/',
            default => null,
        };
    }
}
