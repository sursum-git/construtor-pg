<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria catalogo de metadados de APIs para entidades api e importacao OpenAPI.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE builder_api_source (id SERIAL NOT NULL, code VARCHAR(120) NOT NULL, name VARCHAR(180) NOT NULL, auth_mode VARCHAR(32) NOT NULL, base_url VARCHAR(255) DEFAULT NULL, openapi_url VARCHAR(255) DEFAULT NULL, status VARCHAR(32) NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_builder_api_source_code ON builder_api_source (code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE builder_api_source');
    }
}
