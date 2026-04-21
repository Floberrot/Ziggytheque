<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isAnnounced field to Volume entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volumes ADD is_announced BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volumes DROP is_announced');
    }
}
