<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria catalogo de traducoes/literais do runtime.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE system_literal_translation (id SERIAL NOT NULL, code VARCHAR(160) NOT NULL, locale VARCHAR(20) NOT NULL, text TEXT NOT NULL, context VARCHAR(120) DEFAULT NULL, description TEXT DEFAULT NULL, enabled BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_system_literal_translation_code_locale ON system_literal_translation (code, locale)');
        $this->addSql('CREATE INDEX idx_system_literal_translation_locale ON system_literal_translation (locale, enabled)');
        $this->addSql('CREATE INDEX idx_system_literal_translation_context ON system_literal_translation (context)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE system_literal_translation');
    }
}
