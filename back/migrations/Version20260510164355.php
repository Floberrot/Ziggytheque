<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260510164355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crawl_jobs DROP CONSTRAINT fk_crawl_jobs_run');
        $this->addSql('ALTER TABLE wishlist_items DROP CONSTRAINT "FK_B5BB81B57B6461"');
        $this->addSql('DROP TABLE crawl_jobs');
        $this->addSql('DROP TABLE crawl_runs');
        $this->addSql('DROP TABLE wishlist_items');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE crawl_jobs (id VARCHAR(36) NOT NULL, run_id VARCHAR(36) NOT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_crawl_jobs_run_status ON crawl_jobs (run_id, status)');
        $this->addSql('CREATE INDEX IDX_7E23573484E3FEC4 ON crawl_jobs (run_id)');
        $this->addSql('CREATE TABLE crawl_runs (id VARCHAR(36) NOT NULL, status VARCHAR(16) DEFAULT \'running\' NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE wishlist_items (id VARCHAR(36) NOT NULL, manga_id VARCHAR(36) NOT NULL, is_purchased BOOLEAN NOT NULL, added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_b5bb81b57b6461 ON wishlist_items (manga_id)');
        $this->addSql('ALTER TABLE crawl_jobs ADD CONSTRAINT fk_crawl_jobs_run FOREIGN KEY (run_id) REFERENCES crawl_runs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE wishlist_items ADD CONSTRAINT "FK_B5BB81B57B6461" FOREIGN KEY (manga_id) REFERENCES mangas (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
