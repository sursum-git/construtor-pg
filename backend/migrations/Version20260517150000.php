<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria catalogo e historico de execucao do atualizador de ambiente.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE system_update_release (id SERIAL NOT NULL, version VARCHAR(40) NOT NULL, title VARCHAR(160) NOT NULL, category VARCHAR(40) NOT NULL, severity VARCHAR(30) NOT NULL, description TEXT DEFAULT NULL, auto_apply_saas BOOLEAN NOT NULL, auto_apply_on_prem BOOLEAN NOT NULL, requires_subscriber_consent BOOLEAN NOT NULL, blocks_next_updates BOOLEAN NOT NULL, internet_required BOOLEAN NOT NULL, requires_version_min VARCHAR(40) DEFAULT NULL, requires_applied_updates JSON NOT NULL, steps JSON NOT NULL, program_updates JSON NOT NULL, metadata JSON NOT NULL, manifest_source VARCHAR(255) DEFAULT NULL, manifest_hash VARCHAR(64) DEFAULT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, checked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_system_update_release_version ON system_update_release (version)');
        $this->addSql('CREATE INDEX idx_system_update_release_status ON system_update_release (category, severity, published_at)');
        $this->addSql("COMMENT ON COLUMN system_update_release.requires_applied_updates IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN system_update_release.steps IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN system_update_release.program_updates IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN system_update_release.metadata IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN system_update_release.published_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN system_update_release.checked_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE system_update_execution (id SERIAL NOT NULL, release_version VARCHAR(40) NOT NULL, release_title VARCHAR(160) NOT NULL, category VARCHAR(40) NOT NULL, severity VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, mode VARCHAR(20) NOT NULL, deployment_mode VARCHAR(30) NOT NULL, database_environment VARCHAR(40) NOT NULL, database_identity VARCHAR(120) NOT NULL, initiated_by VARCHAR(120) NOT NULL, initiated_source VARCHAR(30) NOT NULL, runtime_job_id INT DEFAULT NULL, summary JSON NOT NULL, impact_report JSON NOT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_system_update_execution_version ON system_update_execution (release_version, status, created_at)');
        $this->addSql('CREATE INDEX idx_system_update_execution_env ON system_update_execution (deployment_mode, database_identity, created_at)');
        $this->addSql("COMMENT ON COLUMN system_update_execution.summary IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN system_update_execution.impact_report IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN system_update_execution.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN system_update_execution.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN system_update_execution.finished_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE system_update_consent (id SERIAL NOT NULL, release_version VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, approved_by VARCHAR(120) NOT NULL, source VARCHAR(30) NOT NULL, deployment_mode VARCHAR(30) NOT NULL, database_identity VARCHAR(120) NOT NULL, reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_system_update_consent_version ON system_update_consent (release_version, status, created_at)');
        $this->addSql("COMMENT ON COLUMN system_update_consent.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE system_update_consent');
        $this->addSql('DROP TABLE system_update_execution');
        $this->addSql('DROP TABLE system_update_release');
    }
}
