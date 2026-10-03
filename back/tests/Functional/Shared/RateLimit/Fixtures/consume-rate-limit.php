<?php

/**
 * Child process of RateLimiterConcurrencyTest: boots the test kernel (no DAMA here, so
 * every call commits) and spends one shared key as fast as it can.
 *
 * Usage: consume-rate-limit.php consume <key> <limit> <attempts> <start-at-unix-time>
 *        consume-rate-limit.php reset <key>
 * Prints how many calls the limiter let through.
 */

declare(strict_types=1);

use App\Kernel;
use App\Shared\Domain\Exception\RateLimitExceededException;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use Symfony\Component\Dotenv\Dotenv;

$projectDir = dirname(__DIR__, 5);

require $projectDir . '/vendor/autoload.php';

(new Dotenv())->bootEnv($projectDir . '/.env');

$kernel = new Kernel('test', (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

/** @var CacheRateLimiter $limiter */
$limiter = $kernel->getContainer()->get('test.service_container')->get(CacheRateLimiter::class);

$mode = $argv[1] ?? '';
$key  = $argv[2] ?? '';

if ($mode === 'reset') {
    $limiter->reset($key);
    exit(0);
}

$limit    = (int) ($argv[3] ?? 0);
$attempts = (int) ($argv[4] ?? 0);
$startAt  = (float) ($argv[5] ?? 0);

// Every child starts at the same instant, so their calls really overlap.
if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

$allowed = 0;
for ($attempt = 0; $attempt < $attempts; $attempt++) {
    try {
        $limiter->consume($key, $limit, 60);
        $allowed++;
    } catch (RateLimitExceededException) {
        // Refused: exactly what the test counts on past the limit.
    }
}

echo $allowed;
