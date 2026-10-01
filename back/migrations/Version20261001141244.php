<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clean the special editions split out of legacy "Publisher — 5 (Éd. prestige)" values:
 * drop the stray tome number and the wrapping parentheses, spell "Éd." out — so the
 * series reads "Édition prestige", as the catalogue parser now writes it.
 */
final class Version20261001141244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean special editions read as "5 (Éd. prestige)"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE mangas
            SET special_edition = NULLIF(TRIM(regexp_replace(regexp_replace(regexp_replace(
                    special_edition,
                    '^\d{1,4}\s*\((.*)\)$', '\1'),
                    '^\((.*)\)$', '\1'),
                    '^[ÉéEe]d\.\s*', 'Édition ')), '')
            WHERE special_edition ~ '^(\d{1,4}\s*)?\(.*\)$' OR special_edition ~ '^[ÉéEe]d\.'
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE mangas
            SET special_edition = UPPER(LEFT(special_edition, 1)) || SUBSTRING(special_edition FROM 2)
            WHERE special_edition IS NOT NULL AND LEFT(special_edition, 1) <> UPPER(LEFT(special_edition, 1))
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Data clean-up only: the original spelling is not kept.
    }
}
