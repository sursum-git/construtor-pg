<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria sessoes persistentes e mensagens do assistente IA do Program Builder.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE runtime_ai_session (id SERIAL NOT NULL, session_id VARCHAR(160) NOT NULL, tenant_id VARCHAR(80) NOT NULL, user_id VARCHAR(120) NOT NULL, subscriber_code VARCHAR(120) DEFAULT NULL, purpose VARCHAR(80) NOT NULL, catalog_hash VARCHAR(120) NOT NULL, catalog_version VARCHAR(40) NOT NULL, current_draft JSON NOT NULL, current_diagnostics JSON NOT NULL, status VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE runtime_ai_message (id SERIAL NOT NULL, session_id VARCHAR(160) NOT NULL, role VARCHAR(20) NOT NULL, content TEXT NOT NULL, normalized_payload JSON NOT NULL, diagnostics JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_runtime_ai_session_session ON runtime_ai_session (session_id)');
        $this->addSql('CREATE INDEX idx_runtime_ai_session_owner ON runtime_ai_session (tenant_id, user_id, purpose, status)');
        $this->addSql('CREATE INDEX idx_runtime_ai_session_subscriber ON runtime_ai_session (tenant_id, subscriber_code, purpose, status)');
        $this->addSql('CREATE INDEX idx_runtime_ai_message_session ON runtime_ai_message (session_id, created_at)');
        $this->addSql('CREATE INDEX idx_runtime_ai_message_role ON runtime_ai_message (session_id, role)');
        $this->addSql("COMMENT ON COLUMN runtime_ai_session.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_ai_session.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_ai_session.last_seen_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_ai_message.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE runtime_ai_message');
        $this->addSql('DROP TABLE runtime_ai_session');
    }
}
