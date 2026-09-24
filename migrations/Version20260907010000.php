<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure InvoiceTax integration relation uses the existing integration_id column';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax') || $this->columnExists('invoice_tax', 'integration_id')) {
            return;
        }

        if ($this->columnExists('invoice_tax', 'invoice_task_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` CHANGE `invoice_task_id` `integration_id` INT DEFAULT NULL');
            return;
        }

        $this->addSql('ALTER TABLE `invoice_tax` ADD `integration_id` INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );
    }
}
