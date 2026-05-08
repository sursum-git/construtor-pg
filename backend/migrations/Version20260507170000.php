<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Campo telefone no cliente para acoes manuais por WhatsApp.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cliente ADD telefone VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cliente DROP telefone');
    }
}
