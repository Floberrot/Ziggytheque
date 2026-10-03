<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rate-limit counters move out of the cache into their own table, so each call is
 * counted by one atomic upsert: concurrent requests can no longer read the same count
 * and slip past a limit. Counters still in the cache are simply forgotten.
 */
final class Version20261003124222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'rate_limit_counters: atomic fixed-window rate-limit counters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rate_limit_counters (counter_key VARCHAR(40) NOT NULL, hits INT NOT NULL, window_ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (counter_key))');
        $this->addSql('CREATE INDEX IDX_900EE5CF7C1FAF0A ON rate_limit_counters (window_ends_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rate_limit_counters');
    }
}
