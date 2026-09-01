<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901131500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add filterable fiscal document fields to invoice_tax';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax')) {
            return;
        }

        $this->ensureColumn('invoice_tax', 'fiscal_type', 'VARCHAR(10) DEFAULT NULL', 'invoice_tax_fiscal_type');
        $this->ensureColumn('invoice_tax', 'fiscal_series', 'VARCHAR(20) DEFAULT NULL', 'invoice_tax_fiscal_series');
        $this->ensureColumn('invoice_tax', 'fiscal_number', 'VARCHAR(30) DEFAULT NULL', 'invoice_tax_fiscal_number');
        $this->ensureColumn('invoice_tax', 'fiscal_protocol', 'VARCHAR(60) DEFAULT NULL', 'invoice_tax_fiscal_protocol');
        $this->ensureColumn('invoice_tax', 'fiscal_authorization_status', 'VARCHAR(10) DEFAULT NULL', 'invoice_tax_fiscal_authorization_status');

        if ($this->columnExists('invoice_tax', 'invoice_key') && $this->columnExists('invoice_tax', 'invoice_model')) {
            $this->addSql(
                "UPDATE `invoice_tax`
                 SET
                    `fiscal_type` = CASE
                        WHEN `invoice_model` = 57 THEN 'CTE'
                        WHEN `invoice_model` IN (55, 65) THEN 'NFE'
                        ELSE `fiscal_type`
                    END,
                    `fiscal_series` = TRIM(LEADING '0' FROM SUBSTRING(`invoice_key`, 23, 3)),
                    `fiscal_number` = TRIM(LEADING '0' FROM SUBSTRING(`invoice_key`, 26, 9))
                 WHERE `invoice_key` REGEXP '^[0-9]{44}$'
                   AND (`fiscal_series` IS NULL OR `fiscal_number` IS NULL)"
            );

            $this->addSql("UPDATE `invoice_tax` SET `fiscal_series` = '0' WHERE `fiscal_series` = ''");
            $this->addSql("UPDATE `invoice_tax` SET `fiscal_number` = '0' WHERE `fiscal_number` = ''");
        }

        if ($this->columnExists('invoice_tax', 'invoice')) {
            $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `invoice`');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax')) {
            return;
        }

        foreach ([
            'invoice_tax_fiscal_authorization_status',
            'invoice_tax_fiscal_protocol',
            'invoice_tax_fiscal_number',
            'invoice_tax_fiscal_series',
            'invoice_tax_fiscal_type',
        ] as $index) {
            if ($this->indexExists('invoice_tax', $index)) {
                $this->addSql(sprintf('DROP INDEX `%s` ON `invoice_tax`', $index));
            }
        }

        foreach ([
            'fiscal_authorization_status',
            'fiscal_protocol',
            'fiscal_number',
            'fiscal_series',
            'fiscal_type',
        ] as $column) {
            if ($this->columnExists('invoice_tax', $column)) {
                $this->addSql(sprintf('ALTER TABLE `invoice_tax` DROP COLUMN `%s`', $column));
            }
        }
    }

    private function ensureColumn(string $table, string $column, string $definition, string $indexName): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->addSql(sprintf('ALTER TABLE `%s` ADD `%s` %s', $table, $column, $definition));
        }

        if (!$this->indexExists($table, $indexName)) {
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
