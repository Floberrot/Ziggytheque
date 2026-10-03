<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Every account gets its own copy of each series it collects: a correction (title,
 * cover, ISBN, price, tomes) no longer changes the series for everyone.
 *
 * The first collector of a shared series (by date added) keeps the original row;
 * each other collector gets a copy of the series and of its tomes, and their entry
 * and tome entries are moved onto it — owned / read / wished flags, ratings and
 * reviews follow untouched. A series nobody collects keeps no owner: no account
 * sees it any more.
 */
final class Version20261003051255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'mangas.owner_id: one copy of each series per collecting account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mangas ADD owner_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE mangas ADD CONSTRAINT FK_8271C42F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_8271C42F7E3C61F9 ON mangas (owner_id)');

        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                collected RECORD;
                copy_id VARCHAR(36);
            BEGIN
                FOR collected IN
                    SELECT ranked.entry_id, ranked.manga_id, ranked.owner_id, ranked.position
                    FROM (
                        SELECT entry.id AS entry_id, entry.manga_id, entry.owner_id,
                               ROW_NUMBER() OVER (PARTITION BY entry.manga_id ORDER BY entry.added_at, entry.id) AS position
                        FROM collection_entries entry
                    ) ranked
                    ORDER BY ranked.manga_id, ranked.position
                LOOP
                    IF collected.position = 1 THEN
                        UPDATE mangas SET owner_id = collected.owner_id WHERE id = collected.manga_id;
                        CONTINUE;
                    END IF;

                    copy_id := gen_random_uuid()::text;

                    INSERT INTO mangas (id, title, edition, language, author, summary, cover_url, genre,
                                        external_id, created_at, special_edition, owner_id)
                    SELECT copy_id, title, edition, language, author, summary, cover_url, genre,
                           external_id, created_at, special_edition, collected.owner_id
                    FROM mangas
                    WHERE id = collected.manga_id;

                    INSERT INTO volumes (id, manga_id, number, cover_url, price, release_date, isbn)
                    SELECT gen_random_uuid()::text, copy_id, number, cover_url, price, release_date, isbn
                    FROM volumes
                    WHERE manga_id = collected.manga_id;

                    -- A tome is the same tome in the copy: same number in the same series.
                    UPDATE volume_entries tome_entry
                    SET volume_id = copied_volume.id
                    FROM volumes original_volume, volumes copied_volume
                    WHERE tome_entry.collection_entry_id = collected.entry_id
                      AND tome_entry.volume_id = original_volume.id
                      AND copied_volume.manga_id = copy_id
                      AND copied_volume.number = original_volume.number;

                    UPDATE collection_entries SET manga_id = copy_id WHERE id = collected.entry_id;
                END LOOP;
            END
            $$
            SQL);
    }

    public function down(Schema $schema): void
    {
        // The copies stay as separate series: they cannot be told apart from series
        // two users would have added independently.
        $this->addSql('ALTER TABLE mangas DROP CONSTRAINT FK_8271C42F7E3C61F9');
        $this->addSql('DROP INDEX IDX_8271C42F7E3C61F9');
        $this->addSql('ALTER TABLE mangas DROP owner_id');
    }
}
