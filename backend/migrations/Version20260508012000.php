<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508012000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Finaliza normalizacao do valor global subscriber.enabled.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE system_parameter_value SET establishment_code = NULL WHERE establishment_code = ''");
        $this->addSql(<<<'SQL'
DELETE FROM system_parameter_value v
USING system_parameter p
WHERE v.parameter_id = p.id
  AND p.code = 'subscriber.enabled'
  AND v.establishment_code IS NULL
  AND v.ends_at IS NULL
  AND v.id <> (
      SELECT MAX(v2.id)
      FROM system_parameter_value v2
      WHERE v2.parameter_id = v.parameter_id
        AND v2.establishment_code IS NULL
        AND v2.ends_at IS NULL
  )
SQL);
    }

    public function down(Schema $schema): void
    {
    }
}
