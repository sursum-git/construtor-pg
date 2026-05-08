<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normaliza escopo global vazio de valores de parametros para NULL.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE system_parameter_value SET establishment_code = NULL WHERE establishment_code = ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE system_parameter_value SET establishment_code = '' WHERE establishment_code IS NULL");
    }
}
