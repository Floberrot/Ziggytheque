<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Indexes for the reads that scanned whole tables: the journal (activity_logs by
 * date, by status + date, purge by date) and the news feed (articles by date).
 */
final class Version20261002115800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index activity_logs(started_at), activity_logs(status, started_at), articles(created_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_F34B1DCED46F4E3 ON activity_logs (started_at)');
        $this->addSql('CREATE INDEX IDX_F34B1DCE7B00651CD46F4E3 ON activity_logs (status, started_at)');
        $this->addSql('CREATE INDEX IDX_BFDD31688B8E8428 ON articles (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_F34B1DCED46F4E3');
        $this->addSql('DROP INDEX IDX_F34B1DCE7B00651CD46F4E3');
        $this->addSql('DROP INDEX IDX_BFDD31688B8E8428');
    }
}
