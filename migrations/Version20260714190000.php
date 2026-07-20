<?php

declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Baseline schema for accounting module from s.controleonline.com";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('CREATE TABLE IF NOT EXISTS `invoice_tax` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_key` int(11) DEFAULT NULL,
  `invoice_number` int(11) DEFAULT NULL,
  `invoice` longtext CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_number` (`invoice_number`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `service_invoice_tax` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `invoice_tax_id` int(11) NOT NULL,
  `invoice_type` int(11) NOT NULL,
  `issuer_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_id` (`invoice_id`,`invoice_tax_id`) USING BTREE,
  UNIQUE KEY `invoice_type` (`issuer_id`,`invoice_type`,`invoice_id`) USING BTREE,
  KEY `invoice_tax_id` (`invoice_tax_id`) USING BTREE,
  CONSTRAINT `service_invoice_tax_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_invoice_tax_ibfk_2` FOREIGN KEY (`invoice_tax_id`) REFERENCES `invoice_tax` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `service_invoice_tax_ibfk_3` FOREIGN KEY (`issuer_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        return;
    }
}
