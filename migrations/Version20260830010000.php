<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deduplicate invoice_tax by invoice_key and create UNIQUE INDEX uniq_invoice_tax_invoice_key';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax') || !$this->columnExists('invoice_tax', 'invoice_key')) {
            return;
        }

        $this->addSql("UPDATE `invoice_tax` SET `invoice_key` = NULL WHERE `invoice_key` = ''");

        $this->repointDuplicatesSql('order_invoice_tax', 'invoice_tax_id');
        $this->repointDuplicatesSql('service_invoice_tax', 'service_invoice_tax_id');
        $this->repointDuplicatesSql('service_invoice_tax', 'invoice_tax_id');
        $this->repointDuplicatesSql('invoice_tax', 'cte_id');

        $this->addSql(
            'DELETE `dup` FROM `invoice_tax` `dup`
             INNER JOIN (
                SELECT `invoice_key`, MIN(`id`) AS `keep_id`
                FROM `invoice_tax`
                WHERE `invoice_key` IS NOT NULL
                GROUP BY `invoice_key`
             ) `keep` ON `keep`.`invoice_key` = `dup`.`invoice_key`
             WHERE `dup`.`id` <> `keep`.`keep_id`'
        );

        if (!$this->indexExists('invoice_tax', 'uniq_invoice_tax_invoice_key')) {
            $this->addSql('CREATE UNIQUE INDEX `uniq_invoice_tax_invoice_key` ON `invoice_tax` (`invoice_key`)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->indexExists('invoice_tax', 'uniq_invoice_tax_invoice_key')) {
            $this->addSql('DROP INDEX `uniq_invoice_tax_invoice_key` ON `invoice_tax`');
        }
    }

    private function repointDuplicatesSql(string $table, string $column): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return;
        }

        $this->addSql(sprintf(
            'UPDATE `%s` `ref`
             INNER JOIN `invoice_tax` `dup` ON `dup`.`id` = `ref`.`%s`
             INNER JOIN (
                SELECT `invoice_key`, MIN(`id`) AS `keep_id`
                FROM `invoice_tax`
                WHERE `invoice_key` IS NOT NULL
                GROUP BY `invoice_key`
             ) `keep` ON `keep`.`invoice_key` = `dup`.`invoice_key`
             SET `ref`.`%s` = `keep`.`keep_id`
             WHERE `dup`.`id` <> `keep`.`keep_id`',
            $table,
            $column,
            $column
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

    private function indexExists(string $tableName, string $indexName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tableName, $indexName]
        );
    }
}
