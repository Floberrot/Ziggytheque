<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cache_items table required by cache.adapter.doctrine_dbal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS cache_items (
                item_id       VARCHAR(255) NOT NULL,
                item_data     BYTEA        NOT NULL,
                item_lifetime INT          DEFAULT NULL,
                item_time     INT          NOT NULL,
                PRIMARY KEY (item_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS cache_items');
    }
}
