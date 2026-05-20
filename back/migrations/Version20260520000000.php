<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate crawl_runs and crawl_jobs tables erroneously dropped by Version20260510164355';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE crawl_runs (
                id          VARCHAR(36)                 NOT NULL,
                status      VARCHAR(16)                 NOT NULL DEFAULT 'running',
                started_at  TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                finished_at TIMESTAMP WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE crawl_jobs (
                id          VARCHAR(36)                 NOT NULL,
                run_id      VARCHAR(36)                 NOT NULL,
                status      VARCHAR(16)                 NOT NULL DEFAULT 'pending',
                finished_at TIMESTAMP WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_crawl_jobs_run FOREIGN KEY (run_id) REFERENCES crawl_runs(id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_crawl_jobs_run_status ON crawl_jobs (run_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE crawl_jobs');
        $this->addSql('DROP TABLE crawl_runs');
    }
}
