<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Amplia o catalogo de releases com replaces e breaking_level.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE system_update_release ADD replaces JSON NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE system_update_release ADD breaking_level VARCHAR(30) NOT NULL DEFAULT 'non_breaking'");
        $this->addSql("COMMENT ON COLUMN system_update_release.replaces IS '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_update_release DROP replaces');
        $this->addSql('ALTER TABLE system_update_release DROP breaking_level');
    }
}
