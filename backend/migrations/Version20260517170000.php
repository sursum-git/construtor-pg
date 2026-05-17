<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona anuencia formal ao atualizador de ambiente para ambientes ja migrados.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS system_update_consent (id SERIAL NOT NULL, release_version VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, approved_by VARCHAR(120) NOT NULL, source VARCHAR(30) NOT NULL, deployment_mode VARCHAR(30) NOT NULL, database_identity VARCHAR(120) NOT NULL, reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_system_update_consent_version ON system_update_consent (release_version, status, created_at)');
        $this->addSql("COMMENT ON COLUMN system_update_consent.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS system_update_consent');
    }
}
