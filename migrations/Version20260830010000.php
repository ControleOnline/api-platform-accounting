<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deduplicate invoice_tax by invoice_key and add UNIQUE constraint';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax') || !$this->columnExists('invoice_tax', 'invoice_key')) {
            return;
        }

        $this->addSql("UPDATE `invoice_tax` SET `invoice_key` = NULL WHERE `invoice_key` = ''");
    }

    public function postUp(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax') || !$this->columnExists('invoice_tax', 'invoice_key')) {
            return;
        }

        $duplicates = $this->connection->fetchAllAssociative(
            'SELECT `invoice_key`, MIN(`id`) AS keep_id
             FROM `invoice_tax`
             WHERE `invoice_key` IS NOT NULL
             GROUP BY `invoice_key`
             HAVING COUNT(*) > 1'
        );

        foreach ($duplicates as $row) {
            $keepId = (int) $row['keep_id'];
            $key = (string) $row['invoice_key'];
            $dropIds = $this->connection->fetchFirstColumn(
                'SELECT `id` FROM `invoice_tax` WHERE `invoice_key` = ? AND `id` <> ?',
                [$key, $keepId]
            );

            foreach ($dropIds as $dropId) {
                $this->repoint('order_invoice_tax', 'invoice_tax_id', (int) $dropId, $keepId);
                $this->repoint('service_invoice_tax', 'service_invoice_tax_id', (int) $dropId, $keepId);
                $this->repoint('service_invoice_tax', 'invoice_tax_id', (int) $dropId, $keepId);
                $this->repoint('invoice_tax', 'cte_id', (int) $dropId, $keepId);
                $this->connection->delete('invoice_tax', ['id' => (int) $dropId]);
            }
        }

        if (!$this->indexExists('invoice_tax', 'uniq_invoice_tax_invoice_key')) {
            $this->connection->executeStatement(
                'CREATE UNIQUE INDEX `uniq_invoice_tax_invoice_key` ON `invoice_tax` (`invoice_key`)'
            );
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->indexExists('invoice_tax', 'uniq_invoice_tax_invoice_key')) {
            $this->addSql('DROP INDEX `uniq_invoice_tax_invoice_key` ON `invoice_tax`');
        }
    }

    private function repoint(string $table, string $column, int $fromId, int $toId): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return;
        }

        $this->connection->executeStatement(
            sprintf('UPDATE `%s` SET `%s` = ? WHERE `%s` = ?', $table, $column, $column),
            [$toId, $fromId]
        );
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
