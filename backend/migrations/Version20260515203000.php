<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona colunas de monitoria da ultima verificacao em system_record_integrity.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE system_record_integrity ADD last_check_status VARCHAR(20) DEFAULT 'pending' NOT NULL");
        $this->addSql('ALTER TABLE system_record_integrity ADD last_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE system_record_integrity ADD last_error_message VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_record_integrity DROP last_check_status');
        $this->addSql('ALTER TABLE system_record_integrity DROP last_checked_at');
        $this->addSql('ALTER TABLE system_record_integrity DROP last_error_message');
    }
}
