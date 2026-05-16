<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona governanca, overlay/customizacao e metadados de ownership ao versionamento de programas.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE builder_program ADD program_origin VARCHAR(30) DEFAULT 'standard' NOT NULL");
        $this->addSql("ALTER TABLE builder_program ADD owner_scope VARCHAR(20) DEFAULT 'system' NOT NULL");
        $this->addSql("ALTER TABLE builder_program ADD customization_policy VARCHAR(30) DEFAULT 'overlay_only' NOT NULL");
        $this->addSql('ALTER TABLE builder_program ADD subscriber_id VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE builder_program ADD base_program_code VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE builder_program ADD base_program_version_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE builder_program ADD upgrade_frozen BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE builder_program ADD frozen_reason VARCHAR(160) DEFAULT NULL');

        $this->addSql("ALTER TABLE builder_program_version ADD program_origin VARCHAR(30) DEFAULT 'standard' NOT NULL");
        $this->addSql("ALTER TABLE builder_program_version ADD owner_scope VARCHAR(20) DEFAULT 'system' NOT NULL");
        $this->addSql("ALTER TABLE builder_program_version ADD customization_policy VARCHAR(30) DEFAULT 'overlay_only' NOT NULL");
        $this->addSql('ALTER TABLE builder_program_version ADD subscriber_id VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE builder_program_version ADD base_program_code VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE builder_program_version ADD base_program_version_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE builder_program_version ADD upgrade_frozen BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE builder_program_version ADD frozen_reason VARCHAR(160) DEFAULT NULL');

        $this->addSql('ALTER TABLE builder_editor_lock ADD grant_id INT DEFAULT NULL');
        $this->addSql("ALTER TABLE builder_editor_lock ADD lock_category VARCHAR(30) DEFAULT 'general' NOT NULL");

        $this->addSql("CREATE TABLE program_change_request (id SERIAL NOT NULL, request_code VARCHAR(120) NOT NULL, program_code VARCHAR(120) NOT NULL, builder_entity_code VARCHAR(120) DEFAULT NULL, requested_by VARCHAR(120) NOT NULL, requested_actions JSON NOT NULL, reason TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, approved_by VARCHAR(120) DEFAULT NULL, approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_program_change_request_code ON program_change_request (request_code)');
        $this->addSql('CREATE INDEX idx_program_change_request_program ON program_change_request (program_code, status, updated_at)');
        $this->addSql("COMMENT ON COLUMN program_change_request.requested_actions IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN program_change_request.metadata IS '(DC2Type:json)'");

        $this->addSql("CREATE TABLE program_change_grant (id SERIAL NOT NULL, request_id INT NOT NULL, program_code VARCHAR(120) NOT NULL, builder_entity_code VARCHAR(120) DEFAULT NULL, granted_to_user_id VARCHAR(120) NOT NULL, allowed_actions JSON NOT NULL, status VARCHAR(20) NOT NULL, valid_until_publish BOOLEAN NOT NULL, consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX idx_program_change_grant_program ON program_change_grant (program_code, granted_to_user_id, status, updated_at)');
        $this->addSql("COMMENT ON COLUMN program_change_grant.allowed_actions IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN program_change_grant.metadata IS '(DC2Type:json)'");

        $this->addSql("CREATE TABLE program_test_execution (id SERIAL NOT NULL, program_code VARCHAR(120) NOT NULL, builder_program_version_id INT DEFAULT NULL, builder_entity_version_id INT DEFAULT NULL, bundle_id VARCHAR(120) NOT NULL, test_plan_id VARCHAR(160) NOT NULL, executed_by VARCHAR(120) NOT NULL, status VARCHAR(20) NOT NULL, checklist_snapshot JSON NOT NULL, evidences JSON NOT NULL, notes TEXT DEFAULT NULL, executed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX idx_program_test_execution_bundle ON program_test_execution (program_code, builder_program_version_id, bundle_id, status)');
        $this->addSql("COMMENT ON COLUMN program_test_execution.checklist_snapshot IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN program_test_execution.evidences IS '(DC2Type:json)'");

        $this->addSql("CREATE TABLE program_publication_approval (id SERIAL NOT NULL, program_code VARCHAR(120) NOT NULL, builder_program_version_id INT DEFAULT NULL, requested_by VARCHAR(120) NOT NULL, approved_by VARCHAR(120) DEFAULT NULL, status VARCHAR(20) NOT NULL, test_execution_bundle_id VARCHAR(120) DEFAULT NULL, approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX idx_program_publication_approval_program ON program_publication_approval (program_code, builder_program_version_id, status, updated_at)');
        $this->addSql("COMMENT ON COLUMN program_publication_approval.metadata IS '(DC2Type:json)'");

        $this->addSql("CREATE TABLE builder_program_overlay (id SERIAL NOT NULL, program_code VARCHAR(120) NOT NULL, subscriber_id VARCHAR(120) NOT NULL, customization_kind VARCHAR(30) NOT NULL, base_program_version_id INT DEFAULT NULL, status VARCHAR(20) NOT NULL, upgrade_frozen BOOLEAN NOT NULL, frozen_reason VARCHAR(160) DEFAULT NULL, overlay_config JSON NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_builder_program_overlay_identity ON builder_program_overlay (program_code, subscriber_id, customization_kind)');
        $this->addSql('CREATE INDEX idx_builder_program_overlay_status ON builder_program_overlay (program_code, subscriber_id, status, updated_at)');
        $this->addSql("COMMENT ON COLUMN builder_program_overlay.overlay_config IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN builder_program_overlay.metadata IS '(DC2Type:json)'");

        $this->addSql("CREATE TABLE builder_program_overlay_version (id SERIAL NOT NULL, overlay_id INT NOT NULL, version_number INT NOT NULL, status VARCHAR(20) NOT NULL, snapshot JSON NOT NULL, resolved_definition JSON NOT NULL, change_summary TEXT DEFAULT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX idx_builder_program_overlay_version_overlay ON builder_program_overlay_version (overlay_id, status, updated_at)');
        $this->addSql("COMMENT ON COLUMN builder_program_overlay_version.snapshot IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN builder_program_overlay_version.resolved_definition IS '(DC2Type:json)'");

        $this->addSql('ALTER TABLE program_change_grant ADD CONSTRAINT FK_4D697D1F427EB8A5 FOREIGN KEY (request_id) REFERENCES program_change_request (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE builder_program_overlay_version ADD CONSTRAINT FK_9A38C819989A0E54 FOREIGN KEY (overlay_id) REFERENCES builder_program_overlay (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE program_change_grant DROP CONSTRAINT FK_4D697D1F427EB8A5');
        $this->addSql('ALTER TABLE builder_program_overlay_version DROP CONSTRAINT FK_9A38C819989A0E54');
        $this->addSql('DROP TABLE program_change_request');
        $this->addSql('DROP TABLE program_change_grant');
        $this->addSql('DROP TABLE program_test_execution');
        $this->addSql('DROP TABLE program_publication_approval');
        $this->addSql('DROP TABLE builder_program_overlay');
        $this->addSql('DROP TABLE builder_program_overlay_version');
        $this->addSql('ALTER TABLE builder_editor_lock DROP grant_id');
        $this->addSql('ALTER TABLE builder_editor_lock DROP lock_category');
        $this->addSql('ALTER TABLE builder_program DROP program_origin');
        $this->addSql('ALTER TABLE builder_program DROP owner_scope');
        $this->addSql('ALTER TABLE builder_program DROP customization_policy');
        $this->addSql('ALTER TABLE builder_program DROP subscriber_id');
        $this->addSql('ALTER TABLE builder_program DROP base_program_code');
        $this->addSql('ALTER TABLE builder_program DROP base_program_version_id');
        $this->addSql('ALTER TABLE builder_program DROP upgrade_frozen');
        $this->addSql('ALTER TABLE builder_program DROP frozen_reason');
        $this->addSql('ALTER TABLE builder_program_version DROP program_origin');
        $this->addSql('ALTER TABLE builder_program_version DROP owner_scope');
        $this->addSql('ALTER TABLE builder_program_version DROP customization_policy');
        $this->addSql('ALTER TABLE builder_program_version DROP subscriber_id');
        $this->addSql('ALTER TABLE builder_program_version DROP base_program_code');
        $this->addSql('ALTER TABLE builder_program_version DROP base_program_version_id');
        $this->addSql('ALTER TABLE builder_program_version DROP upgrade_frozen');
        $this->addSql('ALTER TABLE builder_program_version DROP frozen_reason');
    }
}
