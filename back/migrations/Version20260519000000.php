<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate crawl_runs and crawl_jobs tables dropped by Version20260510164355; add DC2Type comment for volumes.isbn';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE crawl_runs (
                id          VARCHAR(36)                 NOT NULL,
                status      VARCHAR(16)                 NOT NULL DEFAULT 'running',
                started_at  TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                finished_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE crawl_jobs (
                id          VARCHAR(36)                 NOT NULL,
                run_id      VARCHAR(36)                 NOT NULL,
                status      VARCHAR(16)                 NOT NULL DEFAULT 'pending',
                finished_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX IDX_7E23573484E3FEC4 ON crawl_jobs (run_id)');
        $this->addSql('CREATE INDEX idx_crawl_jobs_run_status ON crawl_jobs (run_id, status)');
        $this->addSql('ALTER TABLE crawl_jobs ADD CONSTRAINT fk_crawl_jobs_run FOREIGN KEY (run_id) REFERENCES crawl_runs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("COMMENT ON COLUMN volumes.isbn IS '(DC2Type:isbn)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crawl_jobs DROP CONSTRAINT fk_crawl_jobs_run');
        $this->addSql('DROP TABLE crawl_jobs');
        $this->addSql('DROP TABLE crawl_runs');
        $this->addSql("COMMENT ON COLUMN volumes.isbn IS NULL");
    }
}
