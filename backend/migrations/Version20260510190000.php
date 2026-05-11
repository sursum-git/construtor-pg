<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria catalogo de modulos do construtor com faixa numerica por modulo.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE builder_module (id SERIAL NOT NULL, code VARCHAR(120) NOT NULL, name VARCHAR(160) NOT NULL, number_start INT NOT NULL, number_end INT NOT NULL, enabled BOOLEAN NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_builder_module_code ON builder_module (code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE builder_module');
    }
}
