<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create invoice_task and invoice_tax.invoice_task_id (MySQL 5.7)';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_task')) {
            $this->addSql(
                'CREATE TABLE `invoice_task` (
                    `id` INT AUTO_INCREMENT NOT NULL,
                    `task_type` VARCHAR(50) NOT NULL,
                    `status_id` INT NOT NULL,
                    `company_id` INT DEFAULT NULL,
                    `address_id` INT DEFAULT NULL,
                    `cfop` VARCHAR(10) DEFAULT NULL,
                    `invoice_total` DECIMAL(12,2) DEFAULT NULL,
                    `payload` LONGTEXT DEFAULT NULL,
                    `created_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `invoice_task_status_id` (`status_id`),
                    INDEX `invoice_task_company_id` (`company_id`),
                    INDEX `invoice_task_address_id` (`address_id`)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB'
            );
        }

        if ($this->tableExists('invoice_tax')) {
            $this->ensureColumn('invoice_tax', 'invoice_task_id', 'INT DEFAULT NULL', 'invoice_tax_invoice_task_id');
        }
    }

    public function down(Schema $schema): void
    {
    }

    private function ensureColumn(string $table, string $column, string $definition, ?string $indexName = null): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->addSql(sprintf('ALTER TABLE `%s` ADD `%s` %s', $table, $column, $definition));
        }
        if ($indexName !== null && !$this->indexExists($table, $indexName)) {
            $this->addSql(sprintf('CREATE INDEX `%s` ON `%s` (`%s`)', $indexName, $table, $column));
        }
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tableName]
        );
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$tableName, $columnName]
        );
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tableName, $indexName]
        );
    }
}
