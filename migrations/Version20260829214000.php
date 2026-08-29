<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829214000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure invoice_tax.file_id and invoice_tax.status_id exist (MySQL 5.7 compatible)';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax')) {
            $this->write('Table invoice_tax not found; skip');
            return;
        }

        if (!$this->columnExists('invoice_tax', 'file_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `file_id` INT DEFAULT NULL');
        }
        if (!$this->indexExists('invoice_tax', 'invoice_tax_file_id')) {
            $this->addSql('CREATE INDEX `invoice_tax_file_id` ON `invoice_tax` (`file_id`)');
        }

        if (!$this->columnExists('invoice_tax', 'status_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `status_id` INT DEFAULT NULL');
        }
        if (!$this->indexExists('invoice_tax', 'invoice_tax_status_id')) {
            $this->addSql('CREATE INDEX `invoice_tax_status_id` ON `invoice_tax` (`status_id`)');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax')) {
            return;
        }

        if ($this->indexExists('invoice_tax', 'invoice_tax_status_id')) {
            $this->addSql('DROP INDEX `invoice_tax_status_id` ON `invoice_tax`');
        }
        if ($this->columnExists('invoice_tax', 'status_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `status_id`');
        }
        if ($this->indexExists('invoice_tax', 'invoice_tax_file_id')) {
            $this->addSql('DROP INDEX `invoice_tax_file_id` ON `invoice_tax`');
        }
        if ($this->columnExists('invoice_tax', 'file_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `file_id`');
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
