<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add publisher, edition_year, external_work_id to mangas; backfill publisher from edition';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mangas ADD COLUMN publisher VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE mangas ADD COLUMN edition_year INT NULL');
        $this->addSql('ALTER TABLE mangas ADD COLUMN external_work_id VARCHAR(255) NULL');

        // Backfill: existing edition values are publisher names; move them, clear edition
        $this->addSql("UPDATE mangas SET publisher = edition, edition = NULL WHERE publisher IS NULL AND edition IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mangas DROP COLUMN publisher');
        $this->addSql('ALTER TABLE mangas DROP COLUMN edition_year');
        $this->addSql('ALTER TABLE mangas DROP COLUMN external_work_id');
    }
}
