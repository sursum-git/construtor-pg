<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Amplia o historico de retencao da governanca com agrupamento de execucao e relacao entre preview e aplicacao.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE program_governance_retention_run ADD execution_group VARCHAR(64) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE program_governance_retention_run ADD related_run_id INT DEFAULT NULL');
        $this->addSql("UPDATE program_governance_retention_run SET execution_group = CONCAT('ret-', id) WHERE execution_group = ''");
        $this->addSql('CREATE INDEX idx_program_governance_retention_run_group ON program_governance_retention_run (execution_group, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_program_governance_retention_run_group');
        $this->addSql('ALTER TABLE program_governance_retention_run DROP related_run_id');
        $this->addSql('ALTER TABLE program_governance_retention_run DROP execution_group');
    }
}
