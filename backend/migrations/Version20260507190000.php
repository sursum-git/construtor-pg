<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sessao persistida como fonte de identidade da transacao runtime.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE runtime_user_session ADD entered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL");
        $this->addSql('ALTER TABLE runtime_user_session ADD php_session_id VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE runtime_user_session ADD device_name VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE runtime_user_session ADD user_agent TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE runtime_user_session ADD operating_system VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE runtime_user_session ADD browser VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE runtime_user_session ADD is_mobile BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("ALTER TABLE runtime_user_session ADD session_properties JSON DEFAULT '{}'::json NOT NULL");
        $this->addSql("ALTER TABLE runtime_user_session ADD permission_snapshot JSON DEFAULT '{}'::json NOT NULL");
        $this->addSql('UPDATE runtime_user_session SET entered_at = created_at');

        $this->addSql('DROP INDEX IF EXISTS idx_runtime_transaction_user');
        $this->addSql('CREATE INDEX idx_runtime_transaction_session ON runtime_transaction (tenant_id, session_id, started_at)');
        $this->addSql('ALTER TABLE runtime_transaction DROP COLUMN user_id');
        $this->addSql('ALTER TABLE runtime_transaction DROP COLUMN user_name');

        $this->addSql('ALTER TABLE runtime_transaction_log DROP COLUMN user_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE runtime_transaction_log ADD user_id VARCHAR(120) DEFAULT '' NOT NULL");

        $this->addSql("ALTER TABLE runtime_transaction ADD user_id VARCHAR(120) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE runtime_transaction ADD user_name VARCHAR(160) DEFAULT NULL');
        $this->addSql('DROP INDEX IF EXISTS idx_runtime_transaction_session');
        $this->addSql('CREATE INDEX idx_runtime_transaction_user ON runtime_transaction (tenant_id, user_id, started_at)');

        $this->addSql('ALTER TABLE runtime_user_session DROP COLUMN permission_snapshot');
        $this->addSql('ALTER TABLE runtime_user_session DROP COLUMN session_properties');
        $this->addSql('ALTER TABLE runtime_user_session DROP COLUMN is_mobile');
        $this->addSql('ALTER TABLE runtime_user_session DROP COLUMN browser');
        $this->addSql('ALTER TABLE runtime_user_session DROP COLUMN operating_system');
        $this->addSql('ALTER TABLE runtime_user_session DROP COLUMN user_agent');
        $this->addSql('ALTER TABLE runtime_user_session DROP COLUMN device_name');
        $this->addSql('ALTER TABLE runtime_user_session DROP COLUMN php_session_id');
        $this->addSql('ALTER TABLE runtime_user_session DROP COLUMN entered_at');
    }
}
