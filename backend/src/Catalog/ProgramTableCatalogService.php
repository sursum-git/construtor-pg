<?php

namespace App\Catalog;

use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Entity\BuilderProgramVersion;
use App\Entity\Program;
use App\Entity\ScreenDefinition;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ProgramRepository;
use App\Repository\ScreenDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProgramTableCatalogService
{
    public function __construct(
        private readonly ProgramRepository $programs,
        private readonly BuilderProgramVersionRepository $versions,
        private readonly BuilderEntityRepository $entities,
        private readonly ScreenDefinitionRepository $screens,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function buildCatalog(): array
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $tableNames = $schemaManager->listTableNames();
        sort($tableNames);

        $tableStats = [];
        foreach ($tableNames as $tableName) {
            try {
                $columnCount = count($schemaManager->listTableColumns($tableName));
            } catch (\Throwable) {
                $columnCount = 0;
            }
            $tableStats[$tableName] = [
                'tableName' => $tableName,
                'columnCount' => $columnCount,
            ];
        }

        $entitiesByCode = [];
        $entitiesByTable = [];
        foreach ($this->entities->findBy([], ['code' => 'ASC']) as $entity) {
            $entitiesByCode[$entity->getCode()] = $entity;
            $tableName = $this->normalizeTableName((string) ($entity->getTableName() ?? ''));
            if ($tableName !== '') {
                $entitiesByTable[$tableName] = $entity;
            }
        }

        $programRows = [];
        $tableRows = [];
        $linkRows = [];

        foreach ($tableStats as $tableName => $stats) {
            $entity = $entitiesByTable[$tableName] ?? null;
            $tableRows[$tableName] = $this->buildBaseTableRow($tableName, $stats['columnCount'], $entity);
        }

        foreach ($this->programs->findBy([], ['code' => 'ASC']) as $program) {
            $version = $this->versions->findPublishedByProgramCode($program->getCode()) ?? ($this->versions->findByProgramCodeOrdered($program->getCode())[0] ?? null);
            $screen = $program->getScreenId() ? $this->screens->findPublishedByScreenId((string) $program->getScreenId()) : null;
            $resolved = $this->resolveProgramTables($program, $version, $screen, $entitiesByCode, $tableStats);
            $programRows[] = $resolved['program'];

            foreach ($resolved['links'] as $link) {
                $linkKey = $resolved['program']['programCode'] . '|' . $link['tableName'] . '|' . $link['relationType'] . '|' . $link['fieldCode'];
                $linkRows[$linkKey] = $link;
                if (!isset($tableRows[$link['tableName']])) {
                    $tableRows[$link['tableName']] = $this->buildBaseTableRow($link['tableName'], $tableStats[$link['tableName']]['columnCount'] ?? 0, $entitiesByTable[$link['tableName']] ?? null);
                }
                $tableRows[$link['tableName']]['relatedPrograms'][$resolved['program']['programCode']] = [
                    'programCode' => $resolved['program']['programCode'],
                    'title' => $resolved['program']['title'],
                    'screenId' => $resolved['program']['screenId'],
                    'relationType' => $link['relationType'],
                ];
            }
        }

        foreach ($tableRows as $tableName => $row) {
            $tableRows[$tableName]['programCount'] = count($row['relatedPrograms']);
            $tableRows[$tableName]['relatedPrograms'] = array_values($row['relatedPrograms']);
        }

        usort($programRows, static fn (array $left, array $right): int => strcmp($left['programCode'], $right['programCode']));
        $tableRows = array_values($tableRows);
        usort($tableRows, static fn (array $left, array $right): int => strcmp($left['tableName'], $right['tableName']));
        $linkRows = array_values($linkRows);
        usort($linkRows, static function (array $left, array $right): int {
            $programCompare = strcmp($left['programCode'], $right['programCode']);
            if ($programCompare !== 0) {
                return $programCompare;
            }
            $tableCompare = strcmp($left['tableName'], $right['tableName']);
            if ($tableCompare !== 0) {
                return $tableCompare;
            }

            return strcmp($left['relationType'], $right['relationType']);
        });

        return [
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'stats' => [
                'programCount' => count($programRows),
                'tableCount' => count($tableRows),
                'relationCount' => count($linkRows),
            ],
            'programs' => $programRows,
            'tables' => $tableRows,
            'relations' => $linkRows,
        ];
    }

    public function writeArtifacts(string $projectRoot, array $catalog): array
    {
        $catalogDir = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'catalog';
        if (!is_dir($catalogDir)) {
            mkdir($catalogDir, 0777, true);
        }

        $jsonPath = $catalogDir . DIRECTORY_SEPARATOR . 'program-table-catalog.json';
        $jsPath = $catalogDir . DIRECTORY_SEPARATOR . 'program-table-catalog-data.js';
        $sqlitePath = $catalogDir . DIRECTORY_SEPARATOR . 'program-table-catalog.sqlite';

        file_put_contents($jsonPath, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        file_put_contents($jsPath, "(function(global){\n  global.ProgramTableCatalogData = " . json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n})(window);\n");

        $this->writeSqlite($sqlitePath, $catalog);

        return [
            'jsonPath' => $jsonPath,
            'jsPath' => $jsPath,
            'sqlitePath' => $sqlitePath,
        ];
    }

    /**
     * @param array<string, BuilderEntity> $entitiesByCode
     * @param array<string, array{tableName: string, columnCount: int}> $tableStats
     * @return array{program: array<string, mixed>, links: list<array<string, mixed>>}
     */
    private function resolveProgramTables(Program $program, ?BuilderProgramVersion $version, ?ScreenDefinition $screen, array $entitiesByCode, array $tableStats): array
    {
        $definition = is_array($version?->getGeneratedDefinition()) && $version?->getGeneratedDefinition() !== []
            ? $version->getGeneratedDefinition()
            : ($screen?->getDefinition() ?? []);

        $pageType = (string) ($definition['pageType'] ?? $version?->getPageType() ?? $program->getProgramType());
        $entityCode = trim((string) ($version?->getBuilderEntityCode() ?? $definition['runtime']['entityCode'] ?? ''));
        $links = [];
        $primaryTableName = null;
        $resolvedPrimaryEntity = $entityCode !== '' ? ($entitiesByCode[$entityCode] ?? null) : null;
        if ($resolvedPrimaryEntity instanceof BuilderEntity) {
            $tableName = $this->normalizeTableName((string) ($resolvedPrimaryEntity->getTableName() ?? ''));
            if ($tableName !== '') {
                $primaryTableName = $tableName;
                $links = array_merge($links, $this->buildEntityLinks($program, $resolvedPrimaryEntity, 'primary'));
            }
        } elseif ($entityCode !== '') {
            $tableName = $this->resolveRuntimeEntityTableName($entityCode, $tableStats);
            if ($tableName !== null) {
                $primaryTableName = $tableName;
                $links[] = $this->buildLinkRow(
                    $program,
                    $tableName,
                    'primary',
                    '',
                    '',
                    'Tabela principal resolvida pelo entityCode runtime.'
                );
            }
        }

        foreach ($this->extractDefinitionEntityCodes($definition) as $extraEntityCode) {
            if ($extraEntityCode === $entityCode) {
                continue;
            }
            $extraEntity = $entitiesByCode[$extraEntityCode] ?? null;
            if ($extraEntity instanceof BuilderEntity) {
                $links = array_merge($links, $this->buildEntityLinks($program, $extraEntity, 'source'));
                continue;
            }
            $tableName = $this->resolveRuntimeEntityTableName($extraEntityCode, $tableStats);
            if ($tableName !== null) {
                $links[] = $this->buildLinkRow(
                    $program,
                    $tableName,
                    'source',
                    '',
                    '',
                    'Tabela lida da definicao do programa como fonte auxiliar.'
                );
            }
        }

        $uniqueTables = [];
        foreach ($links as $link) {
            $uniqueTables[$link['tableName']] = $link['tableName'];
        }
        $tableList = array_values($uniqueTables);
        sort($tableList);

        return [
            'program' => [
                'programCode' => $program->getCode(),
                'title' => $program->getTitle(),
                'screenId' => (string) ($program->getScreenId() ?? ''),
                'module' => (string) ($program->getModule() ?? ''),
                'programType' => $program->getProgramType(),
                'pageType' => $pageType,
                'status' => $program->getStatus(),
                'programOrigin' => $program->getProgramOrigin(),
                'ownerScope' => $program->getOwnerScope(),
                'customizationPolicy' => $program->getCustomizationPolicy(),
                'entityCode' => $entityCode,
                'primaryTableName' => $primaryTableName,
                'tableNames' => $tableList,
                'summary' => $this->programSummary($program, $pageType, $primaryTableName, $tableList),
                'explanation' => $this->programExplanation($program, $pageType, $primaryTableName, $tableList),
            ],
            'links' => $links,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildEntityLinks(Program $program, BuilderEntity $entity, string $relationType): array
    {
        $links = [];
        $tableName = $this->normalizeTableName((string) ($entity->getTableName() ?? ''));
        if ($tableName === '') {
            return [];
        }

        $links[] = $this->buildLinkRow(
            $program,
            $tableName,
            $relationType,
            '',
            '',
            $relationType === 'primary'
                ? 'Tabela principal da entidade publicada para o programa.'
                : 'Tabela auxiliar relacionada pela propria definicao do programa.'
        );

        foreach ($entity->getFields() as $field) {
            if (!$field instanceof BuilderField) {
                continue;
            }
            $foreignKey = is_array($field->getOptions()['foreignKey'] ?? null) ? $field->getOptions()['foreignKey'] : null;
            $foreignTable = $this->normalizeTableName((string) ($foreignKey['table'] ?? ''));
            if ($foreignTable === '') {
                continue;
            }
            $links[] = $this->buildLinkRow(
                $program,
                $foreignTable,
                'related',
                $field->getCode(),
                $field->getLabel(),
                'Relacionamento por chave estrangeira do campo `' . $field->getCode() . '`.'
            );
        }

        return $links;
    }

    /**
     * @return list<string>
     */
    private function extractDefinitionEntityCodes(array $definition): array
    {
        $values = [];
        $walker = function (mixed $node) use (&$values, &$walker): void {
            if (is_array($node)) {
                foreach ($node as $key => $value) {
                    if (($key === 'entityCode' || $key === 'sourceEntityCode') && is_string($value) && trim($value) !== '') {
                        $values[trim($value)] = trim($value);
                    }
                    $walker($value);
                }
            }
        };
        $walker($definition);

        return array_values($values);
    }

    private function buildBaseTableRow(string $tableName, int $columnCount, ?BuilderEntity $entity): array
    {
        return [
            'tableName' => $tableName,
            'entityCode' => $entity?->getCode(),
            'entityName' => $entity?->getName(),
            'entityType' => $entity?->getEntityType(),
            'scope' => $this->tableScope($tableName, $entity),
            'category' => $this->tableCategory($tableName, $entity),
            'columnCount' => $columnCount,
            'explanation' => $this->tableExplanation($tableName, $entity, $columnCount),
            'relatedPrograms' => [],
            'programCount' => 0,
        ];
    }

    private function buildLinkRow(Program $program, string $tableName, string $relationType, string $fieldCode, string $fieldLabel, string $notes): array
    {
        return [
            'programCode' => $program->getCode(),
            'title' => $program->getTitle(),
            'screenId' => (string) ($program->getScreenId() ?? ''),
            'tableName' => $tableName,
            'relationType' => $relationType,
            'fieldCode' => $fieldCode,
            'fieldLabel' => $fieldLabel,
            'notes' => $notes,
        ];
    }

    private function resolveRuntimeEntityTableName(string $entityCode, array $tableStats): ?string
    {
        $normalized = $this->normalizeTableName($entityCode);
        if ($normalized === '') {
            return null;
        }

        return isset($tableStats[$normalized]) ? $normalized : null;
    }

    private function normalizeTableName(string $tableName): string
    {
        $value = strtolower(trim($tableName));
        return preg_match('/^[a-z_][a-z0-9_]*$/', $value) === 1 ? $value : '';
    }

    private function tableScope(string $tableName, ?BuilderEntity $entity): string
    {
        $metadata = is_array($entity?->getMetadata()) ? $entity->getMetadata() : [];
        $subscriberIsolation = is_array($metadata['subscriberIsolation'] ?? null) ? $metadata['subscriberIsolation'] : [];
        $mode = (string) ($subscriberIsolation['mode'] ?? '');
        if ($mode === 'subscriber_column') {
            return 'tenant';
        }
        if ($mode === 'none') {
            return 'global';
        }

        if (str_starts_with($tableName, 'runtime_') || str_starts_with($tableName, 'system_') || str_starts_with($tableName, 'auth_') || str_starts_with($tableName, 'builder_')) {
            return 'global';
        }

        return 'mixed';
    }

    private function tableCategory(string $tableName, ?BuilderEntity $entity): string
    {
        if ($entity instanceof BuilderEntity) {
            return match ($entity->getEntityType()) {
                'persistence' => 'cadastro',
                'query' => 'consulta',
                'io' => 'integracao',
                'api' => 'api',
                default => 'cadastro',
            };
        }
        if (str_starts_with($tableName, 'runtime_analytics_')) {
            return 'analytics';
        }
        if (str_starts_with($tableName, 'runtime_')) {
            return 'runtime';
        }
        if (str_starts_with($tableName, 'system_')) {
            return 'sistema';
        }
        if (str_starts_with($tableName, 'auth_')) {
            return 'autenticacao';
        }
        if (str_starts_with($tableName, 'builder_') || str_starts_with($tableName, 'program_')) {
            return 'governanca';
        }
        if (str_starts_with($tableName, 'import_export_')) {
            return 'integracao';
        }
        if (str_starts_with($tableName, 'privacy_')) {
            return 'lgpd';
        }
        if (str_starts_with($tableName, 'installer_')) {
            return 'instalacao';
        }

        return 'geral';
    }

    private function tableExplanation(string $tableName, ?BuilderEntity $entity, int $columnCount): string
    {
        if ($entity instanceof BuilderEntity) {
            $relationCount = 0;
            foreach ($entity->getFields() as $field) {
                if (is_array($field->getOptions()['foreignKey'] ?? null)) {
                    $relationCount++;
                }
            }

            return 'Tabela da entidade `' . $entity->getCode() . '` (' . $entity->getName() . '). Tipo `' . $entity->getEntityType() . '`, ' . $columnCount . ' colunas catalogadas e ' . $relationCount . ' relacoes declaradas.';
        }

        return match ($this->tableCategory($tableName, $entity)) {
            'analytics' => 'Tabela operacional de BI, pipelines semanticos, datasets publicados ou materializacao de analytics.',
            'runtime' => 'Tabela operacional do runtime para transacoes, jobs, mensagens, locks, eventos ou apoio de execucao.',
            'sistema' => 'Tabela global de configuracao, parametros, literais, opcoes ou integridade do sistema.',
            'autenticacao' => 'Tabela administrativa de autenticacao, usuarios, sessoes, provedores ou assinantes.',
            'governanca' => 'Tabela de modelagem, versionamento, publicacao e governanca de programas e entidades.',
            'integracao' => 'Tabela de mappings, execucoes, versoes ou agendamentos de importacao e exportacao.',
            'lgpd' => 'Tabela da trilha LGPD para solicitacoes do titular, evidencias e politicas de retencao.',
            'instalacao' => 'Tabela de ativacao, licenciamento ou distribuicao da frente de instalacao.',
            default => 'Tabela catalogada automaticamente a partir do banco atual, sem classificacao especifica adicional.',
        };
    }

    private function programSummary(Program $program, string $pageType, ?string $primaryTableName, array $tableList): string
    {
        if ($primaryTableName !== null) {
            return 'Programa `' . $program->getCode() . '` do tipo `' . $pageType . '` com tabela principal `' . $primaryTableName . '` e ' . count($tableList) . ' tabela(s) relacionadas no catalogo.';
        }

        return 'Programa `' . $program->getCode() . '` do tipo `' . $pageType . '` sem tabela primaria resolvida automaticamente.';
    }

    private function programExplanation(Program $program, string $pageType, ?string $primaryTableName, array $tableList): string
    {
        $parts = [
            'Programa `' . $program->getCode() . '` (' . $program->getTitle() . ')',
            'tipo `' . $pageType . '`',
        ];
        if ($primaryTableName !== null) {
            $parts[] = 'usa `' . $primaryTableName . '` como tabela principal';
        } else {
            $parts[] = 'nao possui tabela primaria identificada pelo catalogo automatico';
        }
        if ($tableList) {
            $parts[] = 'e referencia ' . count($tableList) . ' tabela(s): ' . implode(', ', $tableList);
        }

        return implode('; ', $parts) . '.';
    }

    private function writeSqlite(string $sqlitePath, array $catalog): void
    {
        if (!class_exists(\PDO::class)) {
            throw new \RuntimeException('PDO nao esta disponivel para gerar o catalogo SQLite.');
        }

        if (is_file($sqlitePath)) {
            unlink($sqlitePath);
        }

        $pdo = new \PDO('sqlite:' . $sqlitePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalog_program (program_code TEXT PRIMARY KEY, title TEXT NOT NULL, screen_id TEXT, module TEXT, program_type TEXT, page_type TEXT, status TEXT, program_origin TEXT, owner_scope TEXT, customization_policy TEXT, entity_code TEXT, primary_table_name TEXT, summary TEXT, explanation TEXT)');
        $pdo->exec('CREATE TABLE catalog_table (table_name TEXT PRIMARY KEY, entity_code TEXT, entity_name TEXT, entity_type TEXT, scope TEXT, category TEXT, column_count INTEGER NOT NULL, program_count INTEGER NOT NULL, explanation TEXT)');
        $pdo->exec('CREATE TABLE catalog_program_table (id INTEGER PRIMARY KEY AUTOINCREMENT, program_code TEXT NOT NULL, title TEXT NOT NULL, screen_id TEXT, table_name TEXT NOT NULL, relation_type TEXT NOT NULL, field_code TEXT, field_label TEXT, notes TEXT)');
        $pdo->exec('CREATE TABLE catalog_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');

        $programStmt = $pdo->prepare('INSERT INTO catalog_program (program_code, title, screen_id, module, program_type, page_type, status, program_origin, owner_scope, customization_policy, entity_code, primary_table_name, summary, explanation) VALUES (:program_code, :title, :screen_id, :module, :program_type, :page_type, :status, :program_origin, :owner_scope, :customization_policy, :entity_code, :primary_table_name, :summary, :explanation)');
        foreach ($catalog['programs'] as $program) {
            $programStmt->execute([
                ':program_code' => $program['programCode'],
                ':title' => $program['title'],
                ':screen_id' => $program['screenId'],
                ':module' => $program['module'],
                ':program_type' => $program['programType'],
                ':page_type' => $program['pageType'],
                ':status' => $program['status'],
                ':program_origin' => $program['programOrigin'],
                ':owner_scope' => $program['ownerScope'],
                ':customization_policy' => $program['customizationPolicy'],
                ':entity_code' => $program['entityCode'],
                ':primary_table_name' => $program['primaryTableName'],
                ':summary' => $program['summary'],
                ':explanation' => $program['explanation'],
            ]);
        }

        $tableStmt = $pdo->prepare('INSERT INTO catalog_table (table_name, entity_code, entity_name, entity_type, scope, category, column_count, program_count, explanation) VALUES (:table_name, :entity_code, :entity_name, :entity_type, :scope, :category, :column_count, :program_count, :explanation)');
        foreach ($catalog['tables'] as $table) {
            $tableStmt->execute([
                ':table_name' => $table['tableName'],
                ':entity_code' => $table['entityCode'],
                ':entity_name' => $table['entityName'],
                ':entity_type' => $table['entityType'],
                ':scope' => $table['scope'],
                ':category' => $table['category'],
                ':column_count' => (int) $table['columnCount'],
                ':program_count' => (int) $table['programCount'],
                ':explanation' => $table['explanation'],
            ]);
        }

        $linkStmt = $pdo->prepare('INSERT INTO catalog_program_table (program_code, title, screen_id, table_name, relation_type, field_code, field_label, notes) VALUES (:program_code, :title, :screen_id, :table_name, :relation_type, :field_code, :field_label, :notes)');
        foreach ($catalog['relations'] as $relation) {
            $linkStmt->execute([
                ':program_code' => $relation['programCode'],
                ':title' => $relation['title'],
                ':screen_id' => $relation['screenId'],
                ':table_name' => $relation['tableName'],
                ':relation_type' => $relation['relationType'],
                ':field_code' => $relation['fieldCode'],
                ':field_label' => $relation['fieldLabel'],
                ':notes' => $relation['notes'],
            ]);
        }

        $metaStmt = $pdo->prepare('INSERT INTO catalog_meta (key, value) VALUES (:key, :value)');
        foreach ([
            'generatedAt' => (string) $catalog['generatedAt'],
            'programCount' => (string) ($catalog['stats']['programCount'] ?? 0),
            'tableCount' => (string) ($catalog['stats']['tableCount'] ?? 0),
            'relationCount' => (string) ($catalog['stats']['relationCount'] ?? 0),
        ] as $key => $value) {
            $metaStmt->execute([':key' => $key, ':value' => $value]);
        }
    }
}
