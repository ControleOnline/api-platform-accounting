<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829234000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invoice_tax foreign keys (MySQL 5.7)';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax')) {
            $this->write('Table invoice_tax not found; skip');
            return;
        }

        $this->ensureForeignKey(
            'fk_invoice_tax_file',
            'file_id',
            'files',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_status',
            'status_id',
            'status',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_cte',
            'cte_id',
            'invoice_tax',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_issuer',
            'issuer_id',
            'people',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_address',
            'address_id',
            'address',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_company',
            'company_id',
            'people',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_client',
            'client_id',
            'people',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_provider',
            'provider_id',
            'people',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_carrier',
            'carrier_id',
            'people',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_provider_addr',
            'provider_address_id',
            'address',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_client_addr',
            'client_address_id',
            'address',
            'id'
        );
        $this->ensureForeignKey(
            'fk_invoice_tax_carrier_addr',
            'carrier_address_id',
            'address',
            'id'
        );
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax')) {
            return;
        }

        foreach ([
            'fk_invoice_tax_file',
            'fk_invoice_tax_status',
            'fk_invoice_tax_cte',
            'fk_invoice_tax_issuer',
            'fk_invoice_tax_address',
            'fk_invoice_tax_company',
            'fk_invoice_tax_client',
            'fk_invoice_tax_provider',
            'fk_invoice_tax_carrier',
            'fk_invoice_tax_provider_addr',
            'fk_invoice_tax_client_addr',
            'fk_invoice_tax_carrier_addr',
        ] as $constraint) {
            if ($this->foreignKeyExists('invoice_tax', $constraint)) {
                $this->addSql(sprintf('ALTER TABLE `invoice_tax` DROP FOREIGN KEY `%s`', $constraint));
            }
        }
    }

    private function ensureForeignKey(string $constraint, string $column, string $refTable, string $refColumn): void
    {
        if (!$this->columnExists('invoice_tax', $column)) {
            $this->write(sprintf('Column invoice_tax.%s not found; skip FK %s', $column, $constraint));
            return;
        }

        if (!$this->tableExists($refTable)) {
            $this->write(sprintf('Table %s not found; skip FK %s', $refTable, $constraint));
            return;
        }

        if ($this->foreignKeyExists('invoice_tax', $constraint)) {
            return;
        }

        $this->addSql(sprintf(
            'UPDATE `invoice_tax` t LEFT JOIN `%s` r ON t.`%s` = r.`%s` SET t.`%s` = NULL WHERE t.`%s` IS NOT NULL AND r.`%s` IS NULL',
            $refTable,
            $column,
            $refColumn,
            $column,
            $column,
            $refColumn
        ));

        $this->addSql(sprintf(
            'ALTER TABLE `invoice_tax` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE SET NULL ON UPDATE CASCADE',
            $constraint,
            $column,
            $refTable,
            $refColumn
        ));
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

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            [$tableName, $constraintName, 'FOREIGN KEY']
        );
    }
}
