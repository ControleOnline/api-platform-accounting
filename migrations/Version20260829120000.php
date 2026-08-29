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
        $this->addSql('ALTER TABLE `invoice_tax` ADD COLUMN IF NOT EXISTS `invoice_model` int(11) DEFAULT NULL');
        $this->addSql('ALTER TABLE `invoice_tax` ADD COLUMN IF NOT EXISTS `invoice_total` decimal(12,2) DEFAULT NULL');
        $this->addSql('ALTER TABLE `invoice_tax` ADD COLUMN IF NOT EXISTS `cte_id` int(11) DEFAULT NULL');
        $this->addSql('ALTER TABLE `invoice_tax` ADD COLUMN IF NOT EXISTS `issuer_id` int(11) DEFAULT NULL');
        $this->addSql('ALTER TABLE `invoice_tax` ADD COLUMN IF NOT EXISTS `address_id` int(11) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS `invoice_tax_cte_id` ON `invoice_tax` (`cte_id`)');
        $this->addSql('CREATE INDEX IF NOT EXISTS `invoice_tax_issuer_id` ON `invoice_tax` (`issuer_id`)');
        $this->addSql('CREATE INDEX IF NOT EXISTS `invoice_tax_address_id` ON `invoice_tax` (`address_id`)');
    }

    public function down(Schema $schema): void
    {
        return;
    }
}
