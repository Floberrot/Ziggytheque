<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns constraint and index names to match Doctrine's generated hash names.
 *
 * All renames are guarded with IF EXISTS so the migration is idempotent:
 * it succeeds whether the DB has the old human-readable names or already
 * carries the Doctrine-generated names (e.g. after a fresh `migrate`).
 *
 * Double-quoted RENAME TO targets preserve exact uppercase case in pg_constraint /
 * pg_class, matching what DBAL 4 reads back during schema:validate.
 *
 * Doctrine naming: strtoupper(PREFIX . '_' . implode('', array_map('dechex', array_map('crc32', $cols))))
 */
final class Version20260427100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align FK/index names to Doctrine hash names and add missing volume_entries(volume_id) index';
    }

    public function up(Schema $schema): void
    {
        // --- volumes ---
        // FK: fk_volumes_manga → "FK_7ADCAA157B6461"
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'fk_volumes_manga'
                      AND conrelid = 'volumes'::regclass
                ) THEN
                    ALTER TABLE volumes RENAME CONSTRAINT fk_volumes_manga TO "FK_7ADCAA157B6461";
                END IF;
            END $$
        SQL);

        // UNIQ: uniq_7adcaa157b6461901f54 → "UNIQ_7ADCAA157B646196901F54"
        // (original missing the '96' prefix of crc32('number') = 96901f54)
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_class WHERE relname = 'uniq_7adcaa157b6461901f54'
                ) THEN
                    ALTER INDEX uniq_7adcaa157b6461901f54 RENAME TO "UNIQ_7ADCAA157B646196901F54";
                END IF;
            END $$
        SQL);

        // --- collection_entries ---
        // FK: fk_collection_manga → "FK_79D4C1147B6461"
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'fk_collection_manga'
                      AND conrelid = 'collection_entries'::regclass
                ) THEN
                    ALTER TABLE collection_entries RENAME CONSTRAINT fk_collection_manga TO "FK_79D4C1147B6461";
                END IF;
            END $$
        SQL);

        // --- volume_entries ---
        // FK: fk_volume_entry_collection → "FK_46C7979D4BB68772"
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'fk_volume_entry_collection'
                      AND conrelid = 'volume_entries'::regclass
                ) THEN
                    ALTER TABLE volume_entries RENAME CONSTRAINT fk_volume_entry_collection TO "FK_46C7979D4BB68772";
                END IF;
            END $$
        SQL);

        // FK: fk_volume_entry_volume → "FK_46C7979D8FD80EEA"
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'fk_volume_entry_volume'
                      AND conrelid = 'volume_entries'::regclass
                ) THEN
                    ALTER TABLE volume_entries RENAME CONSTRAINT fk_volume_entry_volume TO "FK_46C7979D8FD80EEA";
                END IF;
            END $$
        SQL);

        // IDX: missing index on volume_entries(volume_id) — create if absent
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_class WHERE relname = 'IDX_46C7979D8FD80EEA'
                ) THEN
                    CREATE INDEX "IDX_46C7979D8FD80EEA" ON volume_entries (volume_id);
                END IF;
            END $$
        SQL);

        // --- wishlist_items ---
        // FK: fk_wishlist_manga → "FK_B5BB81B57B6461"
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'fk_wishlist_manga'
                      AND conrelid = 'wishlist_items'::regclass
                ) THEN
                    ALTER TABLE wishlist_items RENAME CONSTRAINT fk_wishlist_manga TO "FK_B5BB81B57B6461";
                END IF;
            END $$
        SQL);

        // --- activity_logs ---
        // FK: fk_activity_logs_collection_entry → "FK_F34B1DCE4BB68772"
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'fk_activity_logs_collection_entry'
                      AND conrelid = 'activity_logs'::regclass
                ) THEN
                    ALTER TABLE activity_logs RENAME CONSTRAINT fk_activity_logs_collection_entry TO "FK_F34B1DCE4BB68772";
                END IF;
            END $$
        SQL);

        // IDX: idx_activity_logs_collection_entry → "IDX_F34B1DCE4BB68772"
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_class WHERE relname = 'idx_activity_logs_collection_entry'
                ) THEN
                    ALTER INDEX idx_activity_logs_collection_entry RENAME TO "IDX_F34B1DCE4BB68772";
                END IF;
            END $$
        SQL);

        // --- articles ---
        // FK: fk_articles_collection_entry → "FK_BFDD31684BB68772"
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'fk_articles_collection_entry'
                      AND conrelid = 'articles'::regclass
                ) THEN
                    ALTER TABLE articles RENAME CONSTRAINT fk_articles_collection_entry TO "FK_BFDD31684BB68772";
                END IF;
            END $$
        SQL);

        // IDX: idx_articles_collection_entry → "IDX_BFDD31684BB68772"
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_class WHERE relname = 'idx_articles_collection_entry'
                ) THEN
                    ALTER INDEX idx_articles_collection_entry RENAME TO "IDX_BFDD31684BB68772";
                END IF;
            END $$
        SQL);
    }

    public function down(Schema $schema): void
    {
        // After up(), quoted renames store names with exact uppercase case in pg_constraint/pg_class.
        // IF EXISTS checks below must match that exact case.

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_7ADCAA157B6461' AND conrelid = 'volumes'::regclass) THEN
                    ALTER TABLE volumes RENAME CONSTRAINT "FK_7ADCAA157B6461" TO fk_volumes_manga;
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'UNIQ_7ADCAA157B646196901F54') THEN
                    ALTER INDEX "UNIQ_7ADCAA157B646196901F54" RENAME TO uniq_7adcaa157b6461901f54;
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_79D4C1147B6461' AND conrelid = 'collection_entries'::regclass) THEN
                    ALTER TABLE collection_entries RENAME CONSTRAINT "FK_79D4C1147B6461" TO fk_collection_manga;
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_46C7979D4BB68772' AND conrelid = 'volume_entries'::regclass) THEN
                    ALTER TABLE volume_entries RENAME CONSTRAINT "FK_46C7979D4BB68772" TO fk_volume_entry_collection;
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_46C7979D8FD80EEA' AND conrelid = 'volume_entries'::regclass) THEN
                    ALTER TABLE volume_entries RENAME CONSTRAINT "FK_46C7979D8FD80EEA" TO fk_volume_entry_volume;
                END IF;
            END $$
        SQL);

        $this->addSql('DROP INDEX IF EXISTS "IDX_46C7979D8FD80EEA"');

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_B5BB81B57B6461' AND conrelid = 'wishlist_items'::regclass) THEN
                    ALTER TABLE wishlist_items RENAME CONSTRAINT "FK_B5BB81B57B6461" TO fk_wishlist_manga;
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_F34B1DCE4BB68772' AND conrelid = 'activity_logs'::regclass) THEN
                    ALTER TABLE activity_logs RENAME CONSTRAINT "FK_F34B1DCE4BB68772" TO fk_activity_logs_collection_entry;
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'IDX_F34B1DCE4BB68772') THEN
                    ALTER INDEX "IDX_F34B1DCE4BB68772" RENAME TO idx_activity_logs_collection_entry;
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_BFDD31684BB68772' AND conrelid = 'articles'::regclass) THEN
                    ALTER TABLE articles RENAME CONSTRAINT "FK_BFDD31684BB68772" TO fk_articles_collection_entry;
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'IDX_BFDD31684BB68772') THEN
                    ALTER INDEX "IDX_BFDD31684BB68772" RENAME TO idx_articles_collection_entry;
                END IF;
            END $$
        SQL);
    }
}
