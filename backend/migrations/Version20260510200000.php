<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona abreviacao ao modulo do construtor para formar codigo de programa por modulo.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE builder_module ADD abbreviation VARCHAR(12) DEFAULT '' NOT NULL");
        $this->addSql("UPDATE builder_module SET abbreviation = CASE code WHEN 'cadastros' THEN 'cd' WHEN 'operacional' THEN 'op' WHEN 'administracao' THEN 'ad' ELSE lower(left(code, 2)) END");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE builder_module DROP abbreviation');
    }
}
