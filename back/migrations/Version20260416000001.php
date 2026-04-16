<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change price_codes.value from NUMERIC to DOUBLE PRECISION';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE price_codes ALTER value TYPE DOUBLE PRECISION');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE price_codes ALTER value TYPE NUMERIC(10,2)');
    }
}
