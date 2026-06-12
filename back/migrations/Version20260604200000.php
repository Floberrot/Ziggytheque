<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add back_cover_url nullable column to volumes table (4th-cover texture for 3D render)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volumes ADD back_cover_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volumes DROP COLUMN back_cover_url');
    }
}
