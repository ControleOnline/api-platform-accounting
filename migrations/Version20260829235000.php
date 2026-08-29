<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829235000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store NF-e/CT-e access key as VARCHAR(44) and rebuild overflowed keys from XML';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax') || !$this->columnExists('invoice_tax', 'invoice_key')) {
            return;
        }

        $this->addSql('ALTER TABLE `invoice_tax` MODIFY `invoice_key` VARCHAR(44) DEFAULT NULL');
    }

    public function postUp(Schema $schema): void
    {
        if (!$this->tableExists('invoice_tax') || !$this->columnExists('invoice_tax', 'invoice')) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative('SELECT `id`, `invoice`, `invoice_key` FROM `invoice_tax`');
        foreach ($rows as $row) {
            $xml = (string) ($row['invoice'] ?? '');
            $key = $this->extractAccessKey($xml);
            if ($key === null) {
                continue;
            }

            $current = preg_replace('/\D+/', '', (string) ($row['invoice_key'] ?? '')) ?: '';
            if ($current === $key) {
                continue;
            }

            $this->connection->update('invoice_tax', ['invoice_key' => $key], ['id' => $row['id']]);
        }
    }

    public function down(Schema $schema): void
    {
    }

    private function extractAccessKey(string $xml): ?string
    {
        if ($xml === '') {
            return null;
        }

        if (preg_match('/Id="(?:NFe|CTe|NFCe|CTeOS)(\d{44})"/', $xml, $match)) {
            return $match[1];
        }
        if (preg_match('/<chNFe>(\d{44})<\/chNFe>/', $xml, $match)) {
            return $match[1];
        }
        if (preg_match('/<chCTe>(\d{44})<\/chCTe>/', $xml, $match)) {
            return $match[1];
        }

        return null;
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
}
