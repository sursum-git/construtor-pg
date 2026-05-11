<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria controle de sequencia para codificacao customizada.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE runtime_custom_code_sequence (
            id SERIAL NOT NULL,
            entity_code VARCHAR(80) NOT NULL,
            field_code VARCHAR(80) NOT NULL,
            scope_key VARCHAR(120) NOT NULL,
            next_value INT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_runtime_custom_code_sequence ON runtime_custom_code_sequence (entity_code, field_code, scope_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE runtime_custom_code_sequence');
    }
}
