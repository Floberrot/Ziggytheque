<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface DiscordNotifierInterface
{
    public function isConfigured(): bool;

    /** @param array<int, array<string, mixed>> $articles */
    public function sendNewArticles(string $mangaTitle, ?string $mangaCoverUrl, int $count, array $articles): void;

    public function sendAlert(string $title, string $description, bool $critical = false): void;
}
