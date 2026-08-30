<?php

declare(strict_types=1);

namespace ControleOnline\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829231700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop invoice_tax.invoice; XML lives in file_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_tax DROP COLUMN invoice');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_tax ADD invoice LONGTEXT DEFAULT NULL');
    }
}
