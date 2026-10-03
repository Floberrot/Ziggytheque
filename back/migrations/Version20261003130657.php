<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * collection_entries.last_notified_at was the cooldown of a "new articles" email that
 * was never sent (its message was never dispatched): the column is always null.
 */
final class Version20261003130657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop collection_entries.last_notified_at (never written)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_entries DROP last_notified_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_entries ADD last_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }
}
