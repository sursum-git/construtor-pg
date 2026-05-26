<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tokens internos cadastraveis para ativacao SaaS e auditoria de uso.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE installer_activation_service_token (id SERIAL NOT NULL, code VARCHAR(120) NOT NULL, name VARCHAR(180) NOT NULL, status VARCHAR(20) NOT NULL, token_hash VARCHAR(255) NOT NULL, allowed_profiles JSON NOT NULL, allowed_modes JSON NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, usage_count INT NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_installer_activation_service_token_code ON installer_activation_service_token (code)');
        $this->addSql('CREATE INDEX idx_installer_activation_service_token_status ON installer_activation_service_token (status, expires_at)');
        $this->addSql("COMMENT ON COLUMN installer_activation_service_token.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN installer_activation_service_token.last_used_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN installer_activation_service_token.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN installer_activation_service_token.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE installer_activation_service_token');
    }
}
