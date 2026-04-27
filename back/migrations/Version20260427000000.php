<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make manga edition nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mangas ALTER COLUMN edition DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE mangas SET edition = '' WHERE edition IS NULL");
        $this->addSql('ALTER TABLE mangas ALTER COLUMN edition SET NOT NULL');
    }
}
