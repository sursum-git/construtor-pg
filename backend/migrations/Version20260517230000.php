<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registra ativacao opcional de releases por assinante no sistema central.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE system_update_tenant_activation (id SERIAL NOT NULL, release_version VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, decided_by VARCHAR(120) NOT NULL, source VARCHAR(30) NOT NULL, deployment_mode VARCHAR(30) NOT NULL, database_identity VARCHAR(120) NOT NULL, target_subscriber_code VARCHAR(120) NOT NULL, target_subscriber_name VARCHAR(160) DEFAULT NULL, reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_system_update_tenant_activation_version ON system_update_tenant_activation (release_version, status, created_at)');
        $this->addSql('CREATE INDEX idx_system_update_tenant_activation_subscriber ON system_update_tenant_activation (target_subscriber_code, created_at)');
        $this->addSql("COMMENT ON COLUMN system_update_tenant_activation.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE system_update_tenant_activation');
    }
}
