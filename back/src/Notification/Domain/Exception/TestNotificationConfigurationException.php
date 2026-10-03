<?php

declare(strict_types=1);

namespace App\Notification\Domain\Exception;

use RuntimeException;

/**
 * The user's notification channel cannot receive the test message (no address, no
 * webhook, a webhook Discord refuses). Never leaves the handler: its message is shown
 * to the user in a failure notification so they can fix their preferences.
 */
final class TestNotificationConfigurationException extends RuntimeException
{
}
