<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add mangas.special_edition — the special edition's name as the catalogue records it
 * ("Prestige", "Perfect edition"…), null for the publisher's standard run.
 *
 * Existing series typed as "Publisher — Edition" (or "-", "·", ":") are split so the
 * publisher stays in `edition` and the rest moves to `special_edition`.
 */
final class Version20260929122739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mangas.special_edition and split existing "Publisher — Edition" values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mangas ADD special_edition VARCHAR(150) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE mangas
            SET special_edition = LEFT(TRIM(substring(edition from '^.+?\s+[—·:–-]\s+(.+)$')), 150),
                edition         = TRIM(substring(edition from '^(.+?)\s+[—·:–-]\s+.+$'))
            WHERE edition ~ '^.+?\s+[—·:–-]\s+.+$'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE mangas
            SET edition = LEFT(CONCAT_WS(' — ', edition, special_edition), 100)
            WHERE special_edition IS NOT NULL
            SQL);
        $this->addSql('ALTER TABLE mangas DROP special_edition');
    }
}
