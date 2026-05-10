<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isbn and spine_url nullable columns to volumes table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volumes ADD isbn VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE volumes ADD spine_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volumes DROP COLUMN isbn');
        $this->addSql('ALTER TABLE volumes DROP COLUMN spine_url');
    }
}
