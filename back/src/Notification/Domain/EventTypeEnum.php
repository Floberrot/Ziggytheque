<?php

declare(strict_types=1);

namespace App\Notification\Domain;

enum EventTypeEnum: string
{
    case RssFetch      = 'rss_fetch';
    case JikanFetch    = 'jikan_fetch';
    case DiscordSent   = 'discord_sent';
    case SchedulerFire = 'scheduler_fire';
    case HttpError     = 'http_error';
    case WorkerFailure = 'worker_failure';
    case UserAction    = 'user_action';
}
