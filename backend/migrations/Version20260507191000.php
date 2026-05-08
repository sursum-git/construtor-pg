<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove defaults tecnicos usados apenas para backfill da sessao runtime.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runtime_user_session ALTER entered_at DROP DEFAULT');
        $this->addSql('ALTER TABLE runtime_user_session ALTER is_mobile DROP DEFAULT');
        $this->addSql('ALTER TABLE runtime_user_session ALTER session_properties DROP DEFAULT');
        $this->addSql('ALTER TABLE runtime_user_session ALTER permission_snapshot DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runtime_user_session ALTER entered_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE runtime_user_session ALTER is_mobile SET DEFAULT false');
        $this->addSql("ALTER TABLE runtime_user_session ALTER session_properties SET DEFAULT '{}'::json");
        $this->addSql("ALTER TABLE runtime_user_session ALTER permission_snapshot SET DEFAULT '{}'::json");
    }
}
