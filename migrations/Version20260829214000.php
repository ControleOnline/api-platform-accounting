<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reaplica file_id/status_id em invoice_tax com versão nova.
 * A 20260829180000 pode ter sido marcada como executada sem alterar o schema.
 * Idempotente: ADD COLUMN IF NOT EXISTS / CREATE INDEX se faltar.
 */
final class Version20260829214000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure invoice_tax.file_id and invoice_tax.status_id exist (retry of 20260829180000)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `invoice_tax` ADD COLUMN IF NOT EXISTS `file_id` INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `invoice_tax` ADD COLUMN IF NOT EXISTS `status_id` INT DEFAULT NULL');

        if (!$this->indexExists('invoice_tax', 'invoice_tax_file_id')) {
            $this->addSql('CREATE INDEX `invoice_tax_file_id` ON `invoice_tax` (`file_id`)');
        }
        if (!$this->indexExists('invoice_tax', 'invoice_tax_status_id')) {
            $this->addSql('CREATE INDEX `invoice_tax_status_id` ON `invoice_tax` (`status_id`)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->indexExists('invoice_tax', 'invoice_tax_file_id')) {
            $this->addSql('DROP INDEX `invoice_tax_file_id` ON `invoice_tax`');
        }
        if ($this->indexExists('invoice_tax', 'invoice_tax_status_id')) {
            $this->addSql('DROP INDEX `invoice_tax_status_id` ON `invoice_tax`');
        }
        $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN IF EXISTS `file_id`');
        $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN IF EXISTS `status_id`');
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tableName, $indexName]
        );
    }
}
