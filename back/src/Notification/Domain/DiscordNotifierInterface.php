<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface DiscordNotifierInterface
{
    public function isConfigured(): bool;

    /** @param array<int, array<string, mixed>> $articles */
    public function sendNewArticles(string $mangaTitle, ?string $mangaCoverUrl, int $count, array $articles): void;

    /**
     * @param array<int, array{
     *     mangaTitle: string,
     *     mangaCoverUrl: string|null,
     *     articles: array<int, array{title: string, url: string}>
     * }> $entries
     */
    public function sendSchedulerSummary(array $entries): void;

    public function sendAlert(string $title, string $description, bool $critical = false): void;
}
