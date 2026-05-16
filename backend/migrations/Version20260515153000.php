<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria historico, versoes e agendamento para import_export_mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE import_export_execution (id SERIAL NOT NULL, mapping_id INT DEFAULT NULL, mapping_code VARCHAR(120) NOT NULL, mapping_name VARCHAR(160) NOT NULL, direction VARCHAR(20) NOT NULL, format VARCHAR(40) NOT NULL, mode VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, parameters JSON NOT NULL, counts JSON NOT NULL, diagnostics JSON NOT NULL, result_summary JSON NOT NULL, file_name VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(120) DEFAULT NULL, duration_ms INT DEFAULT NULL, schedule_code VARCHAR(120) DEFAULT NULL, created_by VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5D9CB8D4D39E7749 ON import_export_execution (mapping_id)');
        $this->addSql('COMMENT ON COLUMN import_export_execution.parameters IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN import_export_execution.counts IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN import_export_execution.diagnostics IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN import_export_execution.result_summary IS \'(DC2Type:json)\'');
        $this->addSql('CREATE TABLE import_export_mapping_version (id SERIAL NOT NULL, mapping_id INT NOT NULL, version_number INT NOT NULL, snapshot JSON NOT NULL, change_summary VARCHAR(255) DEFAULT NULL, created_by VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B1386FECD39E7749 ON import_export_mapping_version (mapping_id)');
        $this->addSql('COMMENT ON COLUMN import_export_mapping_version.snapshot IS \'(DC2Type:json)\'');
        $this->addSql('CREATE TABLE import_export_schedule (id SERIAL NOT NULL, code VARCHAR(120) NOT NULL, name VARCHAR(160) NOT NULL, mapping_code VARCHAR(120) NOT NULL, frequency VARCHAR(20) NOT NULL, enabled BOOLEAN NOT NULL, parameters JSON NOT NULL, interval_minutes INT DEFAULT NULL, daily_hour INT DEFAULT NULL, daily_minute INT DEFAULT NULL, next_run_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_run_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_status VARCHAR(20) DEFAULT NULL, updated_by VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_import_export_schedule_code ON import_export_schedule (code)');
        $this->addSql('COMMENT ON COLUMN import_export_schedule.parameters IS \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE import_export_execution ADD CONSTRAINT FK_5D9CB8D4D39E7749 FOREIGN KEY (mapping_id) REFERENCES import_export_mapping (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE import_export_mapping_version ADD CONSTRAINT FK_B1386FECD39E7749 FOREIGN KEY (mapping_id) REFERENCES import_export_mapping (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_export_execution DROP CONSTRAINT FK_5D9CB8D4D39E7749');
        $this->addSql('ALTER TABLE import_export_mapping_version DROP CONSTRAINT FK_B1386FECD39E7749');
        $this->addSql('DROP TABLE import_export_execution');
        $this->addSql('DROP TABLE import_export_mapping_version');
        $this->addSql('DROP TABLE import_export_schedule');
    }
}
