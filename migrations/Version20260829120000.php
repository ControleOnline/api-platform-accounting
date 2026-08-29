<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link invoice_tax directly to a CT-e and persist issuer/address for NF listing without orders';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax')) {
            return;
        }

        // columns
        if (!$this->columnExists('invoice_tax', 'invoice_model')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `invoice_model` int(11) DEFAULT NULL');
        }
        if (!$this->columnExists('invoice_tax', 'invoice_total')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `invoice_total` decimal(12,2) DEFAULT NULL');
        }
        if (!$this->columnExists('invoice_tax', 'cte_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `cte_id` int(11) DEFAULT NULL');
            $this->addSql('CREATE INDEX `invoice_tax_cte_id` ON `invoice_tax` (`cte_id`)');
        }
        if (!$this->columnExists('invoice_tax', 'issuer_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `issuer_id` int(11) DEFAULT NULL');
            $this->addSql('CREATE INDEX `invoice_tax_issuer_id` ON `invoice_tax` (`issuer_id`)');
        }
        if (!$this->columnExists('invoice_tax', 'address_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `address_id` int(11) DEFAULT NULL');
            $this->addSql('CREATE INDEX `invoice_tax_address_id` ON `invoice_tax` (`address_id`)');
        }
        if (!$this->columnExists('invoice_tax', 'company_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `company_id` int(11) DEFAULT NULL');
            $this->addSql('CREATE INDEX `invoice_tax_company_id` ON `invoice_tax` (`company_id`)');
        }
        if (!$this->columnExists('invoice_tax', 'client_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `client_id` int(11) DEFAULT NULL');
            $this->addSql('CREATE INDEX `invoice_tax_client_id` ON `invoice_tax` (`client_id`)');
        }
        if (!$this->columnExists('invoice_tax', 'provider_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `provider_id` int(11) DEFAULT NULL');
            $this->addSql('CREATE INDEX `invoice_tax_provider_id` ON `invoice_tax` (`provider_id`)');
        }
        if (!$this->columnExists('invoice_tax', 'carrier_id')) {
            $this->addSql('ALTER TABLE `invoice_tax` ADD `carrier_id` int(11) DEFAULT NULL');
            $this->addSql('CREATE INDEX `invoice_tax_carrier_id` ON `invoice_tax` (`carrier_id`)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('invoice_tax')) {
            if ($this->indexExists('invoice_tax', 'invoice_tax_cte_id')) {
                $this->addSql('DROP INDEX `invoice_tax_cte_id` ON `invoice_tax`');
            }
            if ($this->indexExists('invoice_tax', 'invoice_tax_issuer_id')) {
                $this->addSql('DROP INDEX `invoice_tax_issuer_id` ON `invoice_tax`');
            }
            if ($this->indexExists('invoice_tax', 'invoice_tax_address_id')) {
                $this->addSql('DROP INDEX `invoice_tax_address_id` ON `invoice_tax`');
            }
            if ($this->indexExists('invoice_tax', 'invoice_tax_company_id')) {
                $this->addSql('DROP INDEX `invoice_tax_company_id` ON `invoice_tax`');
            }
            if ($this->indexExists('invoice_tax', 'invoice_tax_client_id')) {
                $this->addSql('DROP INDEX `invoice_tax_client_id` ON `invoice_tax`');
            }
            if ($this->indexExists('invoice_tax', 'invoice_tax_provider_id')) {
                $this->addSql('DROP INDEX `invoice_tax_provider_id` ON `invoice_tax`');
            }
            if ($this->indexExists('invoice_tax', 'invoice_tax_carrier_id')) {
                $this->addSql('DROP INDEX `invoice_tax_carrier_id` ON `invoice_tax`');
            }

            if ($this->columnExists('invoice_tax', 'invoice_model')) {
                $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `invoice_model`');
            }
            if ($this->columnExists('invoice_tax', 'invoice_total')) {
                $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `invoice_total`');
            }
            if ($this->columnExists('invoice_tax', 'cte_id')) {
                $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `cte_id`');
            }
            if ($this->columnExists('invoice_tax', 'issuer_id')) {
                $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `issuer_id`');
            }
            if ($this->columnExists('invoice_tax', 'address_id')) {
                $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `address_id`');
            }
            if ($this->columnExists('invoice_tax', 'company_id')) {
                $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `company_id`');
            }
            if ($this->columnExists('invoice_tax', 'client_id')) {
                $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `client_id`');
            }
            if ($this->columnExists('invoice_tax', 'provider_id')) {
                $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `provider_id`');
            }
            if ($this->columnExists('invoice_tax', 'carrier_id')) {
                $this->addSql('ALTER TABLE `invoice_tax` DROP COLUMN `carrier_id`');
            }
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
