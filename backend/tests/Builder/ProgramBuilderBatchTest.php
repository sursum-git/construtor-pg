<?php
namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderApiSource;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Entity\BuilderModule;
use App\Entity\BuilderProgramVersion;
use App\Entity\BuilderEntityVersion;
use App\Entity\RuntimeEndpoint;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderModuleRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;

final class ProgramBuilderBatchTest extends TestCase
{
    private function service(array $overrides = []): ProgramBuilderService
    {
        $reflection = new \ReflectionClass(ProgramBuilderService::class);
        $arguments = [];
        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $type = $parameter->getType()->getName();
            $arguments[] = $overrides[$type] ?? $this->createStub($type);
        }
        return $reflection->newInstanceArgs($arguments);
    }

    private function entity(): BuilderEntity
    {
        return (new BuilderEntity())->setCode('tipos')->setName('Tipos')->setEntityType('api')->setMetadata(['apiSource' => [
            'batchEndpoint' => ['url' => '/tipos', 'method' => 'POST'],
        ]])->addField((new BuilderField())->setCode('tipo_anexo_id')->setPrimaryKey(true)->setDataType('integer'))
            ->addField((new BuilderField())->setCode('descricao')->setDataType('string')->setRequired(true)->setLength(100));
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new \ReflectionClass(ProgramBuilderService::class);
        return $reflection->getMethod($method)->invoke($reflection->newInstanceWithoutConstructor(), ...$arguments);
    }

    public function testBatchModePersistsExportsAndGeneratesDefinitionUsingExistingEngine(): void
    {
        $module = (new BuilderModule())->setCode('cadastros')->setName('Cadastros')->setAbbreviation('cd')->setNumberStart(1)->setNumberEnd(9999);
        $modules = $this->createStub(BuilderModuleRepository::class);
        $modules->method('findOneBy')->willReturn($module);
        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturn($this->entity());
        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn(true);
        $service = $this->service([BuilderModuleRepository::class => $modules, BuilderEntityRepository::class => $entities, PermissionResolver::class => $permissions]);
        $payload = ['programCode' => 'cd0091', 'programTitle' => 'Tipos', 'module' => 'cadastros', 'screenId' => 'ciw.tipos', 'builderEntityCode' => 'tipos', 'gridEditMode' => 'batch', 'allowDelete' => false];
        $draft = $service->saveDraft($payload);
        self::assertSame('batch', $draft['gridEditMode']);
        self::assertSame('batch', $draft['builderConfig']['gridEditMode']);
        self::assertSame('batch', $draft['generatedDefinition']['crud']['grid']['editing']['mode']);
        self::assertTrue($draft['generatedDefinition']['dataModel']['fields']['descricao']['editable']);
        self::assertTrue($draft['generatedDefinition']['dataModel']['fields']['descricao']['validation']['required']);
        self::assertSame(100, $draft['generatedDefinition']['dataModel']['fields']['descricao']['validation']['maxLength']);
        self::assertFalse($draft['generatedDefinition']['dataModel']['fields']['tipo_anexo_id']['editable']);
        self::assertSame(['endpointId' => 'batch', 'method' => 'POST'], $draft['generatedDefinition']['dataSource']['api']['batch']);
        self::assertSame('cd0091.create', $draft['generatedDefinition']['permissions']['create']);
        self::assertSame('cd0091.edit', $draft['generatedDefinition']['permissions']['edit']);
        self::assertFalse($draft['generatedDefinition']['permissions']['delete']);
        self::assertArrayNotHasKey('create', $draft['generatedDefinition']['dataSource']['api']);
        unset($payload['gridEditMode']);
        $form = $service->saveDraft($payload);
        self::assertSame('form', $form['gridEditMode']);
        self::assertSame('form', $form['builderConfig']['gridEditMode']);
        self::assertArrayNotHasKey('batch', $form['generatedDefinition']['dataSource']['api']);
        self::assertFalse($form['generatedDefinition']['permissions']['create']);
    }

    public function testPublicationGeneratesSignedBatchEndpointWithScreenGatesAndReadPermission(): void
    {
        $entity = $this->entity();
        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturn($entity);
        $entityVersions = $this->createStub(BuilderEntityVersionRepository::class);
        $entityVersions->method('findByEntityCodeOrdered')->willReturn([new BuilderEntityVersion()]);
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(function ($item) use (&$persisted): void { $persisted[] = $item; });
        $integrity = $this->createMock(StructuralIntegrityService::class);
        $signed = [];
        $integrity->expects(self::atLeastOnce())->method('signEndpoint')->willReturnCallback(function ($endpoint) use (&$signed): void { $signed[] = $endpoint->getEndpointId(); });
        $service = $this->service([BuilderEntityRepository::class => $entities, BuilderEntityVersionRepository::class => $entityVersions, EntityManagerInterface::class => $em, StructuralIntegrityService::class => $integrity]);
        $version = (new BuilderProgramVersion())->setProgramCode('cd0091')->setBuilderEntityCode('tipos')->setScreenId('ciw.tipos')->setPermissionPrefix('tipos')->setAllowCreate(true)->setAllowUpdate(true)->setAllowDelete(false)->setBuilderConfig(['gridEditMode' => 'batch']);
        (new \ReflectionMethod($service, 'upsertCrudRuntimeEndpoints'))->invoke($service, $version);
        $byId = [];
        foreach ($persisted as $item) { if ($item instanceof RuntimeEndpoint) { $byId[$item->getEndpointId()] = $item; } }
        self::assertArrayHasKey('batch', $byId);
        self::assertSame('entity.api.crud', $byId['batch']->getHandler());
        self::assertSame('tipos.read', $byId['batch']->getPermission());
        self::assertSame('batch', $byId['batch']->getConfig()['operation']);
        self::assertTrue($byId['batch']->getConfig()['allowCreate']);
        self::assertTrue($byId['batch']->getConfig()['allowUpdate']);
        self::assertFalse($byId['batch']->getConfig()['allowDelete']);
        self::assertArrayNotHasKey('create', $byId);
        self::assertContains('batch', $signed);
    }

    public static function invalidModes(): array
    {
        return [['inline'], [''], [[]], [true]];
    }

    #[DataProvider('invalidModes')]
    public function testModeRejectsUnknownValues(mixed $value): void
    {
        $this->expectException(RuntimeHttpException::class);
        $this->invoke('normalizeGridEditMode', $value);
    }

    public function testRegistryBindingSnapshotsBatchAndMasksSecrets(): void
    {
        $metadata = $this->invoke('normalizeApiSourceRegistryPayload', [
            'code' => 'motor', 'name' => 'Motor', 'baseUrl' => 'https://progress.example', 'responseContract' => 'sursum-programs',
            'operations' => [
                ['code' => 'read', 'name' => 'Consultar', 'type' => 'list', 'path' => '/tipos', 'method' => 'POST', 'itemsPath' => 'data'],
                ['code' => 'save_batch', 'name' => 'Salvar lote', 'type' => 'batch', 'path' => '/tipos', 'method' => 'POST', 'itemsPath' => 'data', 'bodyTemplate' => ['operation' => 'batch', '_integrationToken' => 'test-only']],
            ],
        ]);
        self::assertSame('batch', $metadata['operations'][1]['type']);
        $source = (new BuilderApiSource())->setCode('motor')->setBaseUrl('https://progress.example')->setMetadata($metadata);
        $binding = $this->invoke('resolveApiSourceBinding', $source, 'read', '', '', '', '', 'save_batch');
        self::assertSame('https://progress.example/tipos', $binding['batchEndpoint']['url']);
        self::assertSame('data', $binding['batchResponse']['itemsPath']);
        self::assertSame('sursum-programs', $binding['responseContract']);
        $masked = $this->invoke('maskApiSourceSecrets', $binding);
        self::assertSame('********', $masked['batchEndpoint']['bodyTemplate']['_integrationToken']);
        $restored = $this->invoke('restoreMaskedApiSourceSecrets', $masked, $binding);
        self::assertSame('test-only', $restored['batchEndpoint']['bodyTemplate']['_integrationToken']);
    }

    private function sourceWithSecrets(): array
    {
        $operations = [];
        foreach (['list', 'detail', 'create', 'update', 'delete', 'batch'] as $type) {
            $operations[] = ['code' => $type, 'type' => $type, 'path' => 'https://progress.example/tipos', 'method' => 'POST', 'itemsPath' => 'data',
                'headers' => ['Authorization' => 'test-secret-' . $type, 'X-Token' => 'test-token-' . $type, 'Accept' => 'application/json'],
                'bodyTemplate' => ['operation' => $type, '_integrationToken' => 'test-body-' . $type, 'take' => 20, 'enabled' => false,
                    'nested' => ['X-Token' => 'test-nested-' . $type, 'enabled' => true]],
            ];
        }
        $source = (new BuilderApiSource())->setCode('motor')->setBaseUrl('https://progress.example')->setMetadata(['responseContract' => 'sursum-programs', 'authHeaders' => ['Authorization' => 'test-secret-root'], 'operations' => $operations]);
        return $this->invoke('resolveApiSourceBinding', $source, 'list', 'detail', 'create', 'update', 'delete', 'batch');
    }

    public function testAllEndpointAndOperationSecretsAreMaskedAndRestoredByCode(): void
    {
        $binding = $this->sourceWithSecrets();
        $masked = $this->invoke('maskApiSourceSecrets', $binding);
        foreach (['list', 'detail', 'create', 'update', 'delete', 'batch'] as $index => $type) {
            self::assertSame('********', $masked[$type . 'Endpoint']['headers']['Authorization'], $type);
            self::assertSame('********', $masked[$type . 'Endpoint']['headers']['X-Token'], $type);
            self::assertSame('application/json', $masked[$type . 'Endpoint']['headers']['Accept']);
            self::assertSame('********', $masked['operations'][$index]['headers']['Authorization']);
            self::assertSame('********', $masked['operations'][$index]['headers']['X-Token']);
            self::assertSame('********', $masked['operations'][$index]['bodyTemplate']['_integrationToken']);
            self::assertSame('********', $masked['operations'][$index]['bodyTemplate']['nested']['X-Token']);
            self::assertSame(20, $masked['operations'][$index]['bodyTemplate']['take']);
            self::assertFalse($masked['operations'][$index]['bodyTemplate']['enabled']);
        }
        $masked['operations'] = array_reverse($masked['operations']);
        $restored = $this->invoke('restoreMaskedApiSourceSecrets', $masked, $binding);
        $byCode = array_column($restored['operations'], null, 'code');
        foreach (['list', 'detail', 'create', 'update', 'delete', 'batch'] as $type) {
            self::assertSame($binding[$type . 'Endpoint'], $restored[$type . 'Endpoint']);
            self::assertSame('test-secret-' . $type, $byCode[$type]['headers']['Authorization']);
            self::assertSame('test-token-' . $type, $byCode[$type]['headers']['X-Token']);
            self::assertSame('test-body-' . $type, $byCode[$type]['bodyTemplate']['_integrationToken']);
            self::assertSame('test-nested-' . $type, $byCode[$type]['bodyTemplate']['nested']['X-Token']);
        }
    }

    public function testEntitySnapshotAndExternalDraftNeverExposeEndpointOperationSecrets(): void
    {
        $binding = $this->sourceWithSecrets();
        $entity = $this->entity()->setMetadata(['apiSource' => $binding]);
        $service = $this->service();
        $payload = (new \ReflectionMethod($service, 'entityPayload'))->invoke($service, $entity);
        $snapshot = (new \ReflectionMethod($service, 'createEntityVersionSnapshot'))->invoke($service, $entity, 'test');
        $draftConfig = ['code' => 'tipos', 'name' => 'Tipos', 'entityType' => 'api', 'tableName' => '', 'situationEnabled' => false, 'situationFieldCode' => null,
            'fields' => [], 'structure' => [], 'subscriberIsolation' => [], 'uniqueKeys' => [], 'rules' => [], 'softDelete' => [], 'apiSource' => $binding,
            'versioningEnabled' => false, 'versioningDeduplicate' => true];
        $external = (new \ReflectionMethod($service, 'externalEntityPayload'))->invoke($service, $draftConfig);
        foreach ([$payload, $snapshot->getSnapshot(), $external] as $public) {
            $json = json_encode($public, JSON_THROW_ON_ERROR);
            foreach (['test-secret-', 'test-token-', 'test-body-', 'test-nested-'] as $secretPrefix) {
                self::assertStringNotContainsString($secretPrefix, $json);
            }
            self::assertStringContainsString('********', $json);
        }
    }

    public function testHistoricalSnapshotResponseMasksSecretsWithoutChangingStoredSignatureData(): void
    {
        $binding = $this->sourceWithSecrets();
        $stored = ['apiSource' => $binding, 'metadata' => ['apiSource' => $binding]];
        $version = (new BuilderEntityVersion())->setSnapshot($stored);
        $public = $this->invoke('entityVersionPayload', $version);
        self::assertStringNotContainsString('test-secret-', json_encode($public, JSON_THROW_ON_ERROR));
        self::assertSame($stored, $version->getSnapshot());
    }

    public function testExplicitUnknownBatchBindingIsRejected(): void
    {
        $source = (new BuilderApiSource())->setMetadata(['operations' => [['code' => 'read', 'type' => 'list', 'path' => '/tipos', 'itemsPath' => 'data']]]);
        $this->expectException(RuntimeHttpException::class);
        $this->invoke('resolveApiSourceBinding', $source, 'read', '', '', '', '', 'missing');
    }

    public function testApiMapKeepsFormDefaultAndAddsClosedBatchEndpointOnlyWhenSelected(): void
    {
        $entity = (new BuilderEntity())->setEntityType('api');
        $config = ['_entity' => $entity, 'allowCreate' => true, 'allowUpdate' => true, 'allowDelete' => false];
        self::assertArrayNotHasKey('batch', $this->invoke('crudApiMap', $config));
        $map = $this->invoke('crudApiMap', $config + ['gridEditMode' => 'batch']);
        self::assertSame(['endpointId' => 'batch', 'method' => 'POST'], $map['batch']);
    }
}
