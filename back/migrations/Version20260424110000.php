<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notifications_enabled_at to collection_entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_entries ADD COLUMN notifications_enabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // Back-fill: entries already following get now() as their cutoff
        $this->addSql("UPDATE collection_entries SET notifications_enabled_at = NOW() WHERE notifications_enabled = TRUE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_entries DROP COLUMN notifications_enabled_at');
    }
}
