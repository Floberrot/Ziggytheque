<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the share_snapshots table: permanent, public, read-only snapshots of a
 * user's library stats frozen at creation time.
 */
final class Version20260604195729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create share_snapshots table for public library stat shares';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE share_snapshots (created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id VARCHAR(36) NOT NULL, token VARCHAR(32) NOT NULL, owner_name VARCHAR(100) NOT NULL, payload JSON NOT NULL, owner_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_SHARE_SNAPSHOT_OWNER ON share_snapshots (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SHARE_SNAPSHOT_TOKEN ON share_snapshots (token)');
        $this->addSql('ALTER TABLE share_snapshots ADD CONSTRAINT FK_A6668BB17E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE share_snapshots DROP CONSTRAINT FK_A6668BB17E3C61F9');
        $this->addSql('DROP TABLE share_snapshots');
    }
}
