<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260114000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_wished to volume_entries — volume-level wishlist replaces whole-manga WishlistItem';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            ALTER TABLE volume_entries
                ADD COLUMN is_wished BOOLEAN NOT NULL DEFAULT FALSE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volume_entries DROP COLUMN is_wished');
    }
}
