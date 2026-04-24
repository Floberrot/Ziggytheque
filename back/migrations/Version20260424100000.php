<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add snippet column to articles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE articles ADD COLUMN snippet TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE articles DROP COLUMN snippet');
    }
}
