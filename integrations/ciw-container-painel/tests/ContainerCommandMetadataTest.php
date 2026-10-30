<?php
declare(strict_types=1);

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderApiSource;
use App\Entity\BuilderEntity;
use App\Entity\BuilderEntityVersion;
use App\Entity\BuilderField;
use App\Entity\BuilderModule;
use App\Repository\BuilderApiSourceRepository;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Repository\BuilderModuleRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\ApiRequestContext;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeExternalJsonClient;
use App\Runtime\RuntimeTransactionService;
use PHPUnit\Framework\TestCase;

/** Structural validation against the real Builder; no database, HTTP or publish. */
final class ContainerCommandMetadataTest extends TestCase
{
    private function json(string $file): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
    }

    private function invoke(object $service, string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod($service, $method))->invoke($service, ...$args);
    }

    private function service(array &$entities): ProgramBuilderService
    {
        $modules = $this->createStub(BuilderModuleRepository::class);
        $modules->method('findOneBy')->willReturn((new BuilderModule())->setCode('operacional')->setName('Operacional')
            ->setAbbreviation('op')->setNumberStart(1000)->setNumberEnd(1999));
        $repository = $this->createStub(BuilderEntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static function (array $query) use (&$entities) { return $entities[$query['code']] ?? null; });
        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn(true);
        $versions = $this->createStub(BuilderEntityVersionRepository::class);
        $versions->method('findByEntityCodeOrdered')->willReturn([new BuilderEntityVersion()]);
        $sources = [];
        $sourceRepository = $this->createStub(BuilderApiSourceRepository::class);
        $sourceRepository->method('findOneBy')->willReturnCallback(static function (array $query) use (&$sources) { return $sources[$query['code']] ?? null; });
        $overrides = [BuilderModuleRepository::class => $modules, BuilderEntityRepository::class => $repository,
            PermissionResolver::class => $permissions, BuilderEntityVersionRepository::class => $versions,
            BuilderApiSourceRepository::class => $sourceRepository];
        $reflection = new ReflectionClass(ProgramBuilderService::class);
        $arguments = [];
        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $type = $parameter->getType()->getName();
            $arguments[] = $overrides[$type] ?? $this->createStub($type);
        }
        $service = $reflection->newInstanceArgs($arguments);
        foreach (['cabecalho', 'despesas', 'processos'] as $domain) {
            $config = $this->invoke($service, 'normalizeApiSourceRegistryPayload', $this->json($domain . '-api-source-candidate.json'));
            $sources[$config['code']] = (new BuilderApiSource())->setCode($config['code'])->setBaseUrl($config['baseUrl'])->setMetadata($config);
            self::assertSame('sursum-programs', $config['responseContract']);
            foreach ($config['operations'] as $operation) {
                self::assertSame(['container_id'], $operation['requestContextFields']);
                self::assertSame('POST', $operation['method']);
            }
        }
        return $service;
    }

    private function entity(ProgramBuilderService $service, array $draft): BuilderEntity
    {
        $config = $this->invoke($service, 'normalizeEntityPayload', $draft);
        $entity = (new BuilderEntity())->setCode($config['code'])->setName($config['name'])->setEntityType('api')
            ->setMetadata(['primaryKey' => $config['primaryKey'], 'apiBinding' => $config['apiBinding'], 'apiSource' => $config['apiSource']]);
        foreach ($config['fields'] as $field) {
            $entity->addField((new BuilderField())->setCode($field['code'])->setLabel($field['label'])
                ->setDataType($field['dataType'])->setPrimaryKey($field['primaryKey'])->setRequired($field['required'])
                ->setLength($field['length'])->setScaleValue($field['scale'])->setOptions($field['options']));
        }
        return $entity;
    }

    public function testAllCandidateDraftsGenerateThroughCanonicalBuilder(): void
    {
        $entities = [];
        $service = $this->service($entities);
        foreach (['painel-1.0.4', 'despesas-1.0.1', 'processo-gerar-versao', 'processo-fechar-custos', 'processo-sincronizar-impostos'] as $name) {
            $input = $this->json($name . '-builder-draft-candidate.json');
            $entities[$input['entityDraft']['code']] = $this->entity($service, $input['entityDraft']);
            $result = $service->saveDraft($input['programDraft']);
            $definition = $result['generatedDefinition'];
            self::assertSame($input['programDraft']['screenId'], $definition['screenId']);
            self::assertSame($input['programDraft']['pageType'], $definition['pageType']);
            self::assertTrue($definition['contextFields'][0]['required']);
            self::assertTrue($definition['contextFields'][0]['readonly']);
            if ($definition['pageType'] === 'process') {
                self::assertSame(['endpointId' => 'run', 'method' => 'POST'], $definition['dataSource']['api']['process']);
                self::assertSame($input['programDraft']['permissionPrefix'] . '.process', $definition['permissions']['process']);
                self::assertSame('message', $definition['process']['result']['type']);
                self::assertSame($input['programDraft']['processOptions']['confirmation'], $definition['process']['actions']['process']['confirmation']);
                self::assertSame(array_merge($input['programDraft']['processOptions']['parameterFields'], ['container_id']), array_column($definition['process']['parameters']['fields'], 'field'));
                self::assertTrue(array_values(array_filter($definition['process']['parameters']['fields'], static fn (array $f): bool => $f['field'] === 'container_id'))[0]['readonly']);
                foreach ($definition['process']['parameters']['fields'] as $parameter) {
                    self::assertTrue($parameter['required'], $name . '/' . $parameter['field'] . ' is required in the entity contract');
                }
                self::assertArrayNotHasKey('create', $definition['permissions']);
                self::assertArrayNotHasKey('edit', $definition['permissions']);
                self::assertArrayNotHasKey('delete', $definition['permissions']);
            } else {
                self::assertSame($input['programDraft']['allowCreate'] ? $input['programDraft']['permissionPrefix'] . '.create' : false, $definition['permissions']['create']);
                self::assertSame($input['programDraft']['permissionPrefix'] . '.edit', $definition['permissions']['edit']);
                self::assertSame($input['programDraft']['permissionPrefix'] . '.delete', $definition['permissions']['delete']);
            }
        }
    }

    public function testHeaderWriteWhitelistAndOriginalTabsArePreserved(): void
    {
        $before = $this->json('painel-1.0.3-builder-draft.json');
        $after = $this->json('painel-1.0.4-builder-draft-candidate.json');
        self::assertSame($before['programDraft']['formOptions']['tabs'], array_slice($after['programDraft']['formOptions']['tabs'], 0, 11));
        self::assertCount(14, $after['programDraft']['formOptions']['tabs']);
        self::assertLessThanOrEqual(20, count($after['programDraft']['formOptions']['tabs']));
        self::assertFalse($after['programDraft']['allowCreate']);
        self::assertTrue($after['programDraft']['allowUpdate']);
        self::assertTrue($after['programDraft']['allowDelete']);
        $writable = array_column(array_filter($after['entityDraft']['fields'], static fn (array $f): bool => !$f['readonlyField']), 'code');
        self::assertCount(29, $writable);
        self::assertNotContains('container_id', $writable);
        $contractFile = dirname(__DIR__, 2) . '/ContainersCabecalhoCrudService.md';
        if (!is_file($contractFile)) { $contractFile = dirname(__DIR__) . '/ContainersCabecalhoCrudService.md'; }
        $contract = file_get_contents($contractFile);
        preg_match('/Campos editáveis de `values`:\s*```text\s*(.*?)\s*```/s', $contract, $match);
        $expected = explode(',', preg_replace('/\s+/', '', $match[1]));
        sort($writable); sort($expected);
        self::assertSame($expected, $writable);
        foreach (array_slice($after['programDraft']['formOptions']['tabs'], 11) as $tab) {
            self::assertSame('process', $tab['linkedPage']['engine']);
            self::assertSame([['field' => 'container_id', 'sourceField' => 'container_id', 'operator' => 'eq']], $tab['linkedPage']['filters']);
            self::assertTrue($tab['linkedPage']['requireRecord']);
        }
    }

    public function testExpenseUsesIndividualCrudWithoutEnablingIncompatibleBatch(): void
    {
        $before = $this->json('despesas-builder-draft.json');
        $after = $this->json('despesas-1.0.1-builder-draft-candidate.json');
        self::assertSame($before['programDraft']['crudOptions'], $after['programDraft']['crudOptions']);
        self::assertSame('form', $after['programDraft']['gridEditMode']);
        self::assertArrayNotHasKey('apiBatchOperationCode', $after['entityDraft']);
        self::assertSame('container_despesas_progress', $after['entityDraft']['code']);
        $fields = array_column(array_filter($after['entityDraft']['fields'], static fn (array $f): bool => !$f['readonlyField']), 'code');
        self::assertSame(['despesa_id', 'vl_prev', 'vl_base_imp', 'vl_negociado', 'log_manual', 'perc_calc'], $fields);
        $source = $this->json('despesas-api-source-candidate.json');
        foreach ($source['operations'] as $operation) {
            if (in_array($operation['type'], ['create', 'update', 'delete'], true)) {
                self::assertSame('/SursumPrograms/containers-despesas', $operation['path']);
                self::assertSame('container-despesas', $operation['bodyTemplate']['resource']);
            }
            self::assertNotSame('batch', $operation['type']);
        }
    }

    public function testProcessTemplatesAreFixedAndOptInIsLimitedToCommands(): void
    {
        $source = $this->json('processos-api-source-candidate.json');
        $expected = ['gerar_versao' => ['resource' => 'versoes', 'operation' => 'gerar'], 'fechar_custos' => ['resource' => 'custos', 'operation' => 'fechar'], 'sincronizar_impostos' => ['resource' => 'impostos', 'operation' => 'sincronizar']];
        foreach ($source['operations'] as $operation) {
            if (!isset($expected[$operation['code']])) {
                self::assertNotTrue($operation['allowProcess'] ?? false);
                self::assertSame('/SursumPrograms/containers-painel', $operation['path']);
                continue;
            }
            self::assertSame($expected[$operation['code']], $operation['bodyTemplate']);
            self::assertTrue($operation['allowProcess']);
            self::assertSame('custom', $operation['type']);
            self::assertSame('/SursumPrograms/containers-processos', $operation['path']);
        }
    }

    public function testOptionalDefaultReadonlyParameterRemainsOptional(): void
    {
        $entities = [];
        $service = $this->service($entities);
        $input = $this->json('processo-fechar-custos-builder-draft-candidate.json');
        foreach ($input['entityDraft']['fields'] as &$field) {
            if ($field['code'] === 'log_priorizar_custo_previsto') {
                $field['required'] = false;
                $field['readonlyField'] = true;
                $field['defaultValue'] = false;
            }
        }
        unset($field);
        $entities[$input['entityDraft']['code']] = $this->entity($service, $input['entityDraft']);
        $result = $service->saveDraft($input['programDraft']);
        $parameters = array_column($result['generatedDefinition']['process']['parameters']['fields'], null, 'field');
        self::assertTrue($parameters['versao_id']['required']);
        self::assertTrue($parameters['expected_latest_version_id']['required']);
        self::assertFalse($parameters['log_priorizar_custo_previsto']['required']);
        self::assertTrue($parameters['log_priorizar_custo_previsto']['readonly']);
        self::assertFalse($parameters['log_priorizar_custo_previsto']['defaultValue']);
    }

    private function assertRuntimeWriteEnvelope(string $file): void
    {
        $entities = [];
        $builder = $this->service($entities);
        $input = $this->json($file);
        $entity = $this->entity($builder, $input['entityDraft']);
        $repository = $this->createStub(BuilderEntityRepository::class);
        $repository->method('findOneBy')->willReturn($entity);
        $runtime = new RuntimeApiEntityActionService($repository, $this->createStub(RuntimeTransactionService::class),
            $this->createStub(RuntimeExternalJsonClient::class));
        $definition = $this->invoke($runtime, 'resolveDefinition', $entity->getCode());
        $record = [];
        $expectedValues = [];
        foreach ($input['entityDraft']['fields'] as $field) {
            $value = match ($field['dataType']) {
                'integer' => 2,
                'decimal' => 1.25,
                'boolean' => true,
                'date' => '2026-10-16',
                'datetime' => '2026-10-16T12:00:00',
                default => 'REVIEW',
            };
            $record[$field['code']] = $value;
            if (!$field['readonlyField']) { $expectedValues[$field['code']] = $value; }
            self::assertSame($field['code'], $definition['fields'][$field['code']]['jsonPath']);
        }
        $record['container_id'] = 331;
        $record[$definition['primaryKey']] = $definition['primaryKey'] === 'container_id' ? 331 : 77;
        $expectedKeys = ['operation', 'resource', 'container_id', 'values'];
        if ($definition['primaryKey'] !== 'container_id') { $expectedKeys[] = $definition['primaryKey']; }
        foreach (['update' => true, 'delete' => true, 'create' => false] as $operation => $includeReadonly) {
            $endpoint = $definition['apiSource'][$operation . 'Endpoint'];
            $context = ApiRequestContext::resolve($definition, $endpoint, ['context' => ['container_id' => 331]]);
            $write = $this->invoke($runtime, 'buildWritePayload', $definition, $record, $includeReadonly);
            $body = $this->invoke($runtime, 'mergeRequestPayload', $endpoint['bodyTemplate'], array_replace($write, $context));
            self::assertArrayHasKey('values', $body, $file . '/' . $operation . ' must send the service values envelope');
            self::assertSame($expectedValues, $body['values']);
            self::assertSame(331, $body['container_id']);
            self::assertArrayNotHasKey('container_id', $body['values']);
            self::assertArrayNotHasKey($definition['primaryKey'], $body['values']);
            $keys = array_keys($body);
            $expected = $expectedKeys;
            if (!$includeReadonly) { $expected = array_values(array_diff($expected, ['despesa_container_id'])); }
            sort($keys); sort($expected);
            self::assertSame($expected, $keys, 'Readonly presentation fields must not leak into the root body');
        }
    }

    public function testHeaderCrudUsesValuesEnvelopeWithFlatReadAndRootContext(): void
    {
        $this->assertRuntimeWriteEnvelope('painel-1.0.4-builder-draft-candidate.json');
    }

    public function testExpenseCrudUsesValuesEnvelopeWithFlatReadAndRootContext(): void
    {
        $this->assertRuntimeWriteEnvelope('despesas-1.0.1-builder-draft-candidate.json');
    }

    public function testProcessParametersKeepRootWritePaths(): void
    {
        foreach (['gerar-versao', 'fechar-custos', 'sincronizar-impostos'] as $process) {
            $input = $this->json('processo-' . $process . '-builder-draft-candidate.json');
            foreach ($input['entityDraft']['fields'] as $field) {
                self::assertSame($field['code'], $field['apiWritePath']);
                self::assertSame($field['code'], $field['apiJsonPath']);
            }
        }
    }
}
