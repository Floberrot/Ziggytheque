<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_wishlisted column to volume_entries for per-volume wishlist tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volume_entries ADD COLUMN is_wishlisted BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volume_entries DROP COLUMN is_wishlisted');
    }
}
