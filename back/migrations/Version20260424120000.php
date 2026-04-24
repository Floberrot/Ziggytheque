<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align activity_logs: source_type→event_type, nullable collection_entry_id (SET NULL), add metadata';
    }

    public function up(Schema $schema): void
    {
        // source_type → event_type
        $this->addSql('ALTER TABLE activity_logs RENAME COLUMN source_type TO event_type');

        // collection_entry_id: drop NOT NULL + update FK to SET NULL
        $this->addSql('ALTER TABLE activity_logs DROP CONSTRAINT fk_activity_logs_collection_entry');
        $this->addSql('ALTER TABLE activity_logs ALTER COLUMN collection_entry_id DROP NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE activity_logs
                ADD CONSTRAINT fk_activity_logs_collection_entry
                FOREIGN KEY (collection_entry_id) REFERENCES collection_entries(id) ON DELETE SET NULL
        SQL);

        // metadata JSON column
        $this->addSql('ALTER TABLE activity_logs ADD COLUMN metadata JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_logs DROP COLUMN metadata');

        $this->addSql('ALTER TABLE activity_logs DROP CONSTRAINT fk_activity_logs_collection_entry');
        $this->addSql('ALTER TABLE activity_logs ALTER COLUMN collection_entry_id SET NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE activity_logs
                ADD CONSTRAINT fk_activity_logs_collection_entry
                FOREIGN KEY (collection_entry_id) REFERENCES collection_entries(id) ON DELETE CASCADE
        SQL);

        $this->addSql('ALTER TABLE activity_logs RENAME COLUMN event_type TO source_type');
    }
}
