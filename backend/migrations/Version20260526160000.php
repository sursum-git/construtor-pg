<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria cadastro central de licencas de ativacao dos instaladores.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE installer_activation_license (id SERIAL NOT NULL, subscriber_code VARCHAR(120) NOT NULL, subscriber_name VARCHAR(180) NOT NULL, activation_email VARCHAR(180) NOT NULL, status VARCHAR(20) NOT NULL, allowed_profiles JSON NOT NULL, allowed_modes JSON NOT NULL, max_activations INT NOT NULL, activation_count INT NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_activated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, notes TEXT DEFAULT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_installer_activation_license_subscriber ON installer_activation_license (subscriber_code)');
        $this->addSql('CREATE INDEX idx_installer_activation_license_status ON installer_activation_license (status, expires_at)');
        $this->addSql("COMMENT ON COLUMN installer_activation_license.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN installer_activation_license.last_activated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN installer_activation_license.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN installer_activation_license.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE installer_activation_license');
    }
}
