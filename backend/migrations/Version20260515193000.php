<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela de integridade estrutural assinada para registros governados do sistema.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE system_record_integrity (id SERIAL NOT NULL, table_name VARCHAR(120) NOT NULL, record_id INT NOT NULL, integrity_schema_version INT NOT NULL, payload_hash VARCHAR(64) NOT NULL, signature VARCHAR(128) NOT NULL, signed_by VARCHAR(120) DEFAULT NULL, metadata JSON NOT NULL, signed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_system_record_integrity_target ON system_record_integrity (table_name, record_id)');
        $this->addSql('CREATE INDEX idx_system_record_integrity_table ON system_record_integrity (table_name, signed_at)');
        $this->addSql("COMMENT ON COLUMN system_record_integrity.metadata IS '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE system_record_integrity');
    }
}
