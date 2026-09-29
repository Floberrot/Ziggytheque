<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop volumes.spine_url: the book-spine image only fed the 3D library, which is
 * removed (kept on the DRAFT/bibliotheque-3d branch). No cover provider ever
 * returned a spine, so the column holds no data worth keeping.
 */
final class Version20260929120625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop volumes.spine_url (3D library removed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volumes DROP spine_url');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volumes ADD spine_url VARCHAR(255) DEFAULT NULL');
    }
}
