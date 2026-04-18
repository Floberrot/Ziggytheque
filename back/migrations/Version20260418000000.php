<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notifications following: articles, activity_logs tables; notificationsEnabled + lastNotifiedAt on collection_entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE collection_entries
                ADD notifications_enabled BOOLEAN NOT NULL DEFAULT FALSE,
                ADD last_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE articles (
                id                  VARCHAR(36)  NOT NULL,
                collection_entry_id VARCHAR(36)  NOT NULL,
                title               VARCHAR(500) NOT NULL,
                url                 TEXT         NOT NULL,
                source_name         VARCHAR(100) NOT NULL,
                author              VARCHAR(255) DEFAULT NULL,
                image_url           TEXT         DEFAULT NULL,
                published_at        TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_articles_collection_entry
                    FOREIGN KEY (collection_entry_id) REFERENCES collection_entries(id) ON DELETE CASCADE,
                CONSTRAINT uniq_article_entry_url UNIQUE (collection_entry_id, url)
            )
        SQL);

        $this->addSql('CREATE INDEX IDX_articles_collection_entry ON articles (collection_entry_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE activity_logs (
                id                  VARCHAR(36)  NOT NULL,
                collection_entry_id VARCHAR(36)  NOT NULL,
                source_type         VARCHAR(20)  NOT NULL,
                source_name         VARCHAR(100) NOT NULL,
                status              VARCHAR(20)  NOT NULL,
                error_message       TEXT         DEFAULT NULL,
                new_articles_count  INT          DEFAULT NULL,
                started_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                finished_at         TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_activity_logs_collection_entry
                    FOREIGN KEY (collection_entry_id) REFERENCES collection_entries(id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX IDX_activity_logs_collection_entry ON activity_logs (collection_entry_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_logs');
        $this->addSql('DROP TABLE articles');
        $this->addSql(<<<'SQL'
            ALTER TABLE collection_entries
                DROP COLUMN notifications_enabled,
                DROP COLUMN last_notified_at
        SQL);
    }
}
