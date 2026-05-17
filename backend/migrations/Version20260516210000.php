<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria historico persistido das execucoes de retencao da governanca.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE program_governance_retention_run (id SERIAL NOT NULL, mode VARCHAR(20) NOT NULL, source VARCHAR(20) NOT NULL, executed_by VARCHAR(120) NOT NULL, database_environment VARCHAR(40) NOT NULL, database_identity VARCHAR(120) NOT NULL, total_records INT NOT NULL, report JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql("COMMENT ON COLUMN program_governance_retention_run.report IS '(DC2Type:json)'");
        $this->addSql('CREATE INDEX idx_program_governance_retention_run_created ON program_governance_retention_run (created_at, mode)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE program_governance_retention_run');
    }
}
