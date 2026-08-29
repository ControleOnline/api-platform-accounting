<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure InvoiceTax mapped columns exist on invoice_tax (MySQL 5.7)';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax')) {
            $this->write('Table invoice_tax not found; skip');
            return;
        }

        $this->ensureColumn('invoice_tax', 'invoice_model', 'INT DEFAULT NULL');
        $this->ensureColumn('invoice_tax', 'invoice_total', 'DECIMAL(12,2) DEFAULT NULL');
        $this->ensureColumn('invoice_tax', 'cte_id', 'INT DEFAULT NULL', 'invoice_tax_cte_id');
        $this->ensureColumn('invoice_tax', 'issuer_id', 'INT DEFAULT NULL', 'invoice_tax_issuer_id');
        $this->ensureColumn('invoice_tax', 'address_id', 'INT DEFAULT NULL', 'invoice_tax_address_id');
        $this->ensureColumn('invoice_tax', 'company_id', 'INT DEFAULT NULL', 'invoice_tax_company_id');
        $this->ensureColumn('invoice_tax', 'client_id', 'INT DEFAULT NULL', 'invoice_tax_client_id');
        $this->ensureColumn('invoice_tax', 'provider_id', 'INT DEFAULT NULL', 'invoice_tax_provider_id');
        $this->ensureColumn('invoice_tax', 'carrier_id', 'INT DEFAULT NULL', 'invoice_tax_carrier_id');
        $this->ensureColumn('invoice_tax', 'provider_address_id', 'INT DEFAULT NULL', 'invoice_tax_provider_address_id');
        $this->ensureColumn('invoice_tax', 'client_address_id', 'INT DEFAULT NULL', 'invoice_tax_client_address_id');
        $this->ensureColumn('invoice_tax', 'carrier_address_id', 'INT DEFAULT NULL', 'invoice_tax_carrier_address_id');
        $this->ensureColumn('invoice_tax', 'file_id', 'INT DEFAULT NULL', 'invoice_tax_file_id');
        $this->ensureColumn('invoice_tax', 'status_id', 'INT DEFAULT NULL', 'invoice_tax_status_id');
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
