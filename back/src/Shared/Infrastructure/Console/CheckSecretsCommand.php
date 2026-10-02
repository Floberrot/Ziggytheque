<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Domain\Security\SecretStrength;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists the secrets still empty, too short or left on a placeholder (CHANGEME, an old
 * committed default). Run at container start: the names land in the deploy logs —
 * never the values.
 */
#[AsCommand(name: 'app:security:check-secrets', description: 'Report weak or placeholder secrets')]
final class CheckSecretsCommand extends Command
{
    /** Secret name → minimum length. */
    private const array MINIMUM_LENGTHS = [
        'APP_SECRET'                 => SecretStrength::MIN_KEY_LENGTH,
        'JWT_PASSPHRASE'             => SecretStrength::MIN_PASSWORD_LENGTH,
        'GATE_PASSWORD'              => SecretStrength::MIN_PASSWORD_LENGTH,
        'MONITOR_PASSWORD'           => SecretStrength::MIN_PASSWORD_LENGTH,
        'MERCURE_PUBLISHER_JWT_KEY'  => SecretStrength::MIN_KEY_LENGTH,
        'MERCURE_SUBSCRIBER_JWT_KEY' => SecretStrength::MIN_KEY_LENGTH,
        'SCAN_TOKEN_SECRET'          => SecretStrength::MIN_KEY_LENGTH,
    ];

    /** @param array<string, string|null> $secrets secret name → value (null when unset) */
    public function __construct(private readonly array $secrets)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $weakSecrets = [];
        foreach (self::MINIMUM_LENGTHS as $name => $minimumLength) {
            if (SecretStrength::isWeak($this->secrets[$name] ?? '', $minimumLength)) {
                $weakSecrets[] = sprintf('%s (at least %d characters, not a placeholder)', $name, $minimumLength);
            }
        }

        if ($weakSecrets === []) {
            $style->success('Every secret is set.');

            return Command::SUCCESS;
        }

        $style->warning('Weak or placeholder secrets — set real values in the environment:');
        $style->listing($weakSecrets);

        return Command::FAILURE;
    }
}
