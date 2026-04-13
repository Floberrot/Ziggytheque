<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: price_codes, mangas, volumes, collection_entries, volume_entries, wishlist_items, notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE price_codes (
                code        VARCHAR(20)      NOT NULL,
                label       VARCHAR(100)     NOT NULL,
                value       NUMERIC(10,2)    NOT NULL,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (code)
            )
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE mangas (
                id          VARCHAR(36)      NOT NULL,
                title       VARCHAR(255)     NOT NULL,
                edition     VARCHAR(100)     NOT NULL,
                language    VARCHAR(10)      NOT NULL,
                author      VARCHAR(255)     DEFAULT NULL,
                summary     TEXT             DEFAULT NULL,
                cover_url   VARCHAR(255)     DEFAULT NULL,
                genre       VARCHAR(50)      DEFAULT NULL,
                external_id VARCHAR(255)     DEFAULT NULL,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE volumes (
                id           VARCHAR(36)   NOT NULL,
                manga_id     VARCHAR(36)   NOT NULL,
                price_code   VARCHAR(20)   DEFAULT NULL,
                number       INT           NOT NULL,
                cover_url    VARCHAR(255)  DEFAULT NULL,
                release_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_volumes_manga
                    FOREIGN KEY (manga_id) REFERENCES mangas(id) ON DELETE CASCADE,
                CONSTRAINT fk_volumes_price_code
                    FOREIGN KEY (price_code) REFERENCES price_codes(code) ON DELETE SET NULL,
                CONSTRAINT uq_volumes_manga_number UNIQUE (manga_id, number)
            )
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE collection_entries (
                id             VARCHAR(36)  NOT NULL,
                manga_id       VARCHAR(36)  NOT NULL,
                reading_status VARCHAR(50)  NOT NULL DEFAULT 'not_started',
                review         TEXT         DEFAULT NULL,
                rating         INT          DEFAULT NULL,
                added_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_collection_manga
                    FOREIGN KEY (manga_id) REFERENCES mangas(id) ON DELETE CASCADE,
                CONSTRAINT uq_collection_manga UNIQUE (manga_id)
            )
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE volume_entries (
                id                  VARCHAR(36)  NOT NULL,
                collection_entry_id VARCHAR(36)  NOT NULL,
                volume_id           VARCHAR(36)  NOT NULL,
                is_owned            BOOLEAN      NOT NULL DEFAULT FALSE,
                is_read             BOOLEAN      NOT NULL DEFAULT FALSE,
                review              TEXT         DEFAULT NULL,
                rating              INT          DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_volume_entry_collection
                    FOREIGN KEY (collection_entry_id) REFERENCES collection_entries(id) ON DELETE CASCADE,
                CONSTRAINT fk_volume_entry_volume
                    FOREIGN KEY (volume_id) REFERENCES volumes(id) ON DELETE CASCADE,
                CONSTRAINT uq_volume_entry UNIQUE (collection_entry_id, volume_id)
            )
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE wishlist_items (
                id           VARCHAR(36)  NOT NULL,
                manga_id     VARCHAR(36)  NOT NULL,
                is_purchased BOOLEAN      NOT NULL DEFAULT FALSE,
                added_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_wishlist_manga
                    FOREIGN KEY (manga_id) REFERENCES mangas(id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE notifications (
                id         VARCHAR(36)  NOT NULL,
                type       VARCHAR(50)  NOT NULL,
                message    TEXT         NOT NULL,
                is_read    BOOLEAN      NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_volumes_manga ON volumes (manga_id)');
        $this->addSql('CREATE INDEX idx_volume_entries_collection ON volume_entries (collection_entry_id)');
        $this->addSql('CREATE INDEX idx_wishlist_manga ON wishlist_items (manga_id)');
        $this->addSql('CREATE INDEX idx_notifications_read ON notifications (is_read)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE wishlist_items');
        $this->addSql('DROP TABLE volume_entries');
        $this->addSql('DROP TABLE collection_entries');
        $this->addSql('DROP TABLE volumes');
        $this->addSql('DROP TABLE mangas');
        $this->addSql('DROP TABLE price_codes');
    }
}
