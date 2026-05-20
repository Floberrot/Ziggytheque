<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * User management migration v1 (PR A).
 *
 * Creates:
 *   - users table
 *   - auth_tokens table
 *
 * Adds nullable owner_id FK to all user-scoped tables:
 *   - collection_entries (also replaces unique(manga_id) with unique(owner_id, manga_id))
 *   - notifications
 *   - articles
 *   - activity_logs (ON DELETE SET NULL — keep logs for audit after user deletion)
 *
 * owner_id stays nullable so the migration can run before app:bootstrap-admin.
 * Migration v2 (PR B, after bootstrap-admin ran) will set NOT NULL.
 */
final class Version20260519000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users + auth_tokens tables; add nullable owner_id FK to user-scoped tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id           VARCHAR(36)  NOT NULL,
                email        VARCHAR(180) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                role         VARCHAR(255) NOT NULL,
                status       VARCHAR(255) NOT NULL,
                notification_channel VARCHAR(255) NOT NULL,
                notification_email   VARCHAR(180)  DEFAULT NULL,
                discord_webhook_url  VARCHAR(500)  DEFAULT NULL,
                created_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX "UNIQ_1483A5E9E7927C74" ON users (email)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE auth_tokens (
                id          VARCHAR(36) NOT NULL,
                user_id     VARCHAR(36) NOT NULL,
                type        VARCHAR(255) NOT NULL,
                token_hash  VARCHAR(64) NOT NULL,
                expires_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id),
                CONSTRAINT "FK_8AF9B66CA76ED395" FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX "IDX_8AF9B66CA76ED395" ON auth_tokens (user_id)
        SQL);

        // collection_entries: replace unique(manga_id) with unique(owner_id, manga_id) + non-unique index, add FK
        $this->addSql(<<<'SQL'
            ALTER TABLE collection_entries ADD owner_id VARCHAR(36) DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE collection_entries DROP CONSTRAINT IF EXISTS uniq_79d4c1147b6461
        SQL);

        $this->addSql(<<<'SQL'
            DROP INDEX IF EXISTS uniq_79d4c1147b6461
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX "IDX_79D4C1147B6461" ON collection_entries (manga_id)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX "UNIQ_79D4C1147E3C61F97B6461" ON collection_entries (owner_id, manga_id)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE collection_entries
                ADD CONSTRAINT "FK_79D4C1147E3C61F9"
                FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX "IDX_79D4C1147E3C61F9" ON collection_entries (owner_id)
        SQL);

        // notifications: add nullable owner_id FK
        $this->addSql(<<<'SQL'
            ALTER TABLE notifications ADD owner_id VARCHAR(36) DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE notifications
                ADD CONSTRAINT "FK_6000B0D37E3C61F9"
                FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX "IDX_6000B0D37E3C61F9" ON notifications (owner_id)
        SQL);

        // articles: add nullable owner_id FK (ON DELETE CASCADE — articles belong to user)
        $this->addSql(<<<'SQL'
            ALTER TABLE articles ADD owner_id VARCHAR(36) DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE articles
                ADD CONSTRAINT "FK_BFDD31687E3C61F9"
                FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX "IDX_BFDD31687E3C61F9" ON articles (owner_id)
        SQL);

        // activity_logs: ON DELETE SET NULL — keep audit trail even after user deletion
        $this->addSql(<<<'SQL'
            ALTER TABLE activity_logs ADD owner_id VARCHAR(36) DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE activity_logs
                ADD CONSTRAINT "FK_F34B1DCE7E3C61F9"
                FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX "IDX_F34B1DCE7E3C61F9" ON activity_logs (owner_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_logs DROP CONSTRAINT IF EXISTS "FK_F34B1DCE7E3C61F9"');
        $this->addSql('DROP INDEX IF EXISTS "IDX_F34B1DCE7E3C61F9"');
        $this->addSql('ALTER TABLE activity_logs DROP COLUMN IF EXISTS owner_id');

        $this->addSql('ALTER TABLE articles DROP CONSTRAINT IF EXISTS "FK_BFDD31687E3C61F9"');
        $this->addSql('DROP INDEX IF EXISTS "IDX_BFDD31687E3C61F9"');
        $this->addSql('ALTER TABLE articles DROP COLUMN IF EXISTS owner_id');

        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS "FK_6000B0D37E3C61F9"');
        $this->addSql('DROP INDEX IF EXISTS "IDX_6000B0D37E3C61F9"');
        $this->addSql('ALTER TABLE notifications DROP COLUMN IF EXISTS owner_id');

        $this->addSql('DROP INDEX IF EXISTS "UNIQ_79D4C1147E3C61F97B6461"');
        $this->addSql('DROP INDEX IF EXISTS "IDX_79D4C1147B6461"');
        $this->addSql('ALTER TABLE collection_entries DROP CONSTRAINT IF EXISTS "FK_79D4C1147E3C61F9"');
        $this->addSql('DROP INDEX IF EXISTS "IDX_79D4C1147E3C61F9"');
        $this->addSql('ALTER TABLE collection_entries DROP COLUMN IF EXISTS owner_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_79D4C1147B6461 ON collection_entries (manga_id)');

        $this->addSql('DROP TABLE IF EXISTS auth_tokens');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
