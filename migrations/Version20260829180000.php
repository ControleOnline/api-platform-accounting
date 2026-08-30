<?php
declare(strict_types=1);

namespace DoctrineMigrations\Accounting;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mantida para não quebrar doctrine_migration_versions.
 * Schema real agora é garantido por Version20260829214000.
 */
final class Version20260829180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op. invoice_tax.file_id/status_id moved to Version20260829214000';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}
