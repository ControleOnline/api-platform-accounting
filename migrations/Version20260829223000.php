<?php

declare(strict_types=1);

namespace ControleOnline\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create invoice_task and link invoice_tax.invoice_task_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE IF NOT EXISTS invoice_task (
            id INT AUTO_INCREMENT NOT NULL,
            task_type VARCHAR(50) NOT NULL,
            status_id INT NOT NULL,
            company_id INT DEFAULT NULL,
            address_id INT DEFAULT NULL,
            cfop VARCHAR(10) DEFAULT NULL,
            invoice_total NUMERIC(12, 2) DEFAULT NULL,
            payload LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            INDEX idx_invoice_task_status (status_id),
            INDEX idx_invoice_task_company (company_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE invoice_tax ADD COLUMN invoice_task_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_invoice_tax_task ON invoice_tax (invoice_task_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_tax DROP COLUMN invoice_task_id');
        $this->addSql('DROP TABLE invoice_task');
    }
}
