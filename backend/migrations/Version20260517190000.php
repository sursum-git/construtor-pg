<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona alvo por assinante ao historico e anuencia das atualizacoes do sistema.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_update_execution ADD target_subscriber_code VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE system_update_execution ADD target_subscriber_name VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE system_update_execution ADD target_database_environment VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE system_update_execution ADD target_database_identity VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_system_update_execution_subscriber ON system_update_execution (target_subscriber_code, created_at)');

        $this->addSql('ALTER TABLE system_update_consent ADD target_subscriber_code VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE system_update_consent ADD target_subscriber_name VARCHAR(160) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_system_update_consent_subscriber ON system_update_consent (target_subscriber_code, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_system_update_execution_subscriber');
        $this->addSql('ALTER TABLE system_update_execution DROP target_subscriber_code');
        $this->addSql('ALTER TABLE system_update_execution DROP target_subscriber_name');
        $this->addSql('ALTER TABLE system_update_execution DROP target_database_environment');
        $this->addSql('ALTER TABLE system_update_execution DROP target_database_identity');

        $this->addSql('DROP INDEX idx_system_update_consent_subscriber');
        $this->addSql('ALTER TABLE system_update_consent DROP target_subscriber_code');
        $this->addSql('ALTER TABLE system_update_consent DROP target_subscriber_name');
    }
}
