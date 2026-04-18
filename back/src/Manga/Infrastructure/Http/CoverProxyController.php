<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CoverProxyController
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    #[Route('/proxy/cover', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $url = $request->query->get('url', '');

        if (!$url || !preg_match('#^https://books\.google[a-z.]*/#', $url)) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Referer' => 'https://books.google.com/',
                'User-Agent' => 'Mozilla/5.0',
            ],
            'max_redirects' => 0,
        ]);

        $status = $response->getStatusCode();
        if ($status !== 200) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        if (!str_starts_with($contentType, 'image/')) {
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
}
