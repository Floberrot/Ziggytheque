<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Translation;

use App\Manga\Domain\SummaryTranslatorInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Translates summaries via Google's keyless "gtx" web endpoint, mirroring the
 * project's other keyless providers (BnF, OpenLibrary). No API key required.
 *
 * The endpoint answers with a nested JSON array where the first element holds
 * the translated segments: [[["traduit","source",...], ...], ...].
 */
final readonly class GoogleTranslateSummaryTranslator implements SummaryTranslatorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
    ) {
    }

    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        if (trim($text) === '') {
            return $text;
        }

        $this->logger->info('GoogleTranslate: translating summary', [
            'source' => $sourceLanguage,
            'target' => $targetLanguage,
            'length' => mb_strlen($text),
        ]);

        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/translate_a/single', [
                'query' => [
                    'client' => 'gtx',
                    'sl'     => $sourceLanguage,
                    'tl'     => $targetLanguage,
                    'dt'     => 't',
                    'q'      => $text,
                ],
            ]);

            $data     = $response->toArray();
            $segments = $data[0] ?? [];

            $translated = '';
            foreach ($segments as $segment) {
                $translated .= $segment[0] ?? '';
            }

            // Fall back to the original text if the response carried no translation.
            return $translated !== '' ? $translated : $text;
        } catch (Throwable $exception) {
            $this->logger->error('GoogleTranslate: translation failed', ['error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
