<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria EventBus runtime com outbox, assinaturas e entregas idempotentes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE runtime_event (id SERIAL NOT NULL, transaction_id INT DEFAULT NULL, event_id VARCHAR(120) NOT NULL, event_code VARCHAR(160) NOT NULL, source VARCHAR(80) NOT NULL, tenant_id VARCHAR(80) NOT NULL, user_id VARCHAR(120) DEFAULT NULL, session_id VARCHAR(160) DEFAULT NULL, screen_id VARCHAR(160) DEFAULT NULL, program_code VARCHAR(160) DEFAULT NULL, entity_code VARCHAR(120) DEFAULT NULL, record_id VARCHAR(120) DEFAULT NULL, operation VARCHAR(80) DEFAULT NULL, status VARCHAR(30) NOT NULL, payload JSON NOT NULL, metadata JSON NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE runtime_event_subscription (id SERIAL NOT NULL, code VARCHAR(120) NOT NULL, tenant_id VARCHAR(80) NOT NULL, event_code VARCHAR(160) NOT NULL, title VARCHAR(160) NOT NULL, enabled BOOLEAN NOT NULL, handler_type VARCHAR(30) NOT NULL, condition JSON NOT NULL, handler_config JSON NOT NULL, max_attempts INT NOT NULL, priority INT NOT NULL, idempotency_key_template VARCHAR(240) NOT NULL, status VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE runtime_event_delivery (id SERIAL NOT NULL, event_id INT NOT NULL, subscription_id INT NOT NULL, transaction_id INT DEFAULT NULL, tenant_id VARCHAR(80) NOT NULL, status VARCHAR(30) NOT NULL, attempts INT NOT NULL, idempotency_key VARCHAR(240) NOT NULL, last_error TEXT DEFAULT NULL, result JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_runtime_event_event_id ON runtime_event (event_id)');
        $this->addSql('CREATE INDEX idx_runtime_event_status ON runtime_event (tenant_id, status, created_at)');
        $this->addSql('CREATE INDEX idx_runtime_event_code ON runtime_event (event_code, status)');
        $this->addSql('CREATE INDEX idx_runtime_event_record ON runtime_event (entity_code, record_id)');
        $this->addSql('CREATE INDEX IDX_77F492CB2FC0CB0F ON runtime_event (transaction_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_runtime_event_subscription_code ON runtime_event_subscription (code)');
        $this->addSql('CREATE INDEX idx_runtime_event_subscription_match ON runtime_event_subscription (tenant_id, event_code, enabled, priority)');
        $this->addSql('CREATE UNIQUE INDEX uniq_runtime_event_delivery_idempotency ON runtime_event_delivery (idempotency_key)');
        $this->addSql('CREATE INDEX idx_runtime_event_delivery_status ON runtime_event_delivery (tenant_id, status, created_at)');
        $this->addSql('CREATE INDEX idx_runtime_event_delivery_event ON runtime_event_delivery (event_id)');
        $this->addSql('CREATE INDEX IDX_65D3F37E71F7E88B ON runtime_event_delivery (subscription_id)');
        $this->addSql('CREATE INDEX IDX_65D3F37E2FC0CB0F ON runtime_event_delivery (transaction_id)');
        $this->addSql('ALTER TABLE runtime_event ADD CONSTRAINT FK_77F492CB2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES runtime_transaction (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE runtime_event_delivery ADD CONSTRAINT FK_65D3F37E71F7E88B FOREIGN KEY (subscription_id) REFERENCES runtime_event_subscription (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE runtime_event_delivery ADD CONSTRAINT FK_65D3F37EEA1A9B84 FOREIGN KEY (event_id) REFERENCES runtime_event (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE runtime_event_delivery ADD CONSTRAINT FK_65D3F37E2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES runtime_transaction (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("COMMENT ON COLUMN runtime_event.occurred_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_event.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_event.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_event.processed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_event_subscription.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_event_subscription.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_event_delivery.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_event_delivery.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_event_delivery.started_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN runtime_event_delivery.finished_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runtime_event_delivery DROP CONSTRAINT FK_65D3F37E71F7E88B');
        $this->addSql('ALTER TABLE runtime_event_delivery DROP CONSTRAINT FK_65D3F37EEA1A9B84');
        $this->addSql('ALTER TABLE runtime_event_delivery DROP CONSTRAINT FK_65D3F37E2FC0CB0F');
        $this->addSql('ALTER TABLE runtime_event DROP CONSTRAINT FK_77F492CB2FC0CB0F');
        $this->addSql('DROP TABLE runtime_event_delivery');
        $this->addSql('DROP TABLE runtime_event_subscription');
        $this->addSql('DROP TABLE runtime_event');
    }
}
