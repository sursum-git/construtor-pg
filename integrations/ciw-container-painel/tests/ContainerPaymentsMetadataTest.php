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
final class ContainerPaymentsMetadataTest extends TestCase
{
    private function json(string $file): array
    {
        $candidate = dirname(__DIR__) . '/' . $file;
        return json_decode(file_get_contents($candidate), true, 512, JSON_THROW_ON_ERROR);
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
        foreach (['cabecalho', 'pagamentos'] as $domain) {
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


    public function testRegisteredSourceHasFiveClosedContextualOperations(): void {
        self::assertFileExists(dirname(__DIR__).'/pagamentos-api-source-candidate.json');
        $entities=[];$s=$this->service($entities);$source=$this->invoke($s,'normalizeApiSourceRegistryPayload',$this->json('pagamentos-api-source-candidate.json'));
        self::assertSame('motor_progress_container_pagamentos',$source['code']);self::assertCount(5,$source['operations']);
        foreach($source['operations'] as $op){self::assertSame('POST',$op['method']);self::assertSame(['container_id'],$op['requestContextFields']);self::assertNotSame('update',$op['type']);self::assertNotSame('batch',$op['type']);}
        $ops=array_column($source['operations'],null,'code');self::assertSame('/med/web/SursumPrograms/containers-financeiro',parse_url($ops['pagamentos_read']['path'],PHP_URL_PATH));self::assertSame('pagamentos',$ops['pagamentos_get']['bodyTemplate']['resource']);
        foreach(['pagamentos_create'=>'create','pagamentos_delete'=>'delete','pagamentos_disponiveis'=>'read'] as $code=>$action){self::assertSame('/med/web/SursumPrograms/containers-pagamentos',parse_url($ops[$code]['path'],PHP_URL_PATH));self::assertSame($action,$ops[$code]['bodyTemplate']['operation']);}
    }
    public function testPreservesPaymentKeyAndEveryExistingField(): void {
        $before=$this->json('pagamentos-builder-draft.json');$after=$this->json('pagamentos-1.0.2-builder-draft-candidate.json');
        self::assertSame(array_column($before['entityDraft']['fields'],'code'),array_column($after['entityDraft']['fields'],'code'));
        foreach($after['entityDraft']['fields'] as $i=>$field){if($field['code']!=='num_id_tit_ap')self::assertSame($before['entityDraft']['fields'][$i],$field);}
        $pk=array_values(array_filter($after['entityDraft']['fields'],fn($f)=>$f['primaryKey']));self::assertCount(1,$pk);self::assertSame('pagamento_key',$pk[0]['code']);self::assertSame('string',$pk[0]['dataType']);
    }
    public function testRealBuilderGeneratesCreateDeleteWithoutUpdate(): void {
        $entities=[];$s=$this->service($entities);$d=$this->json('pagamentos-1.0.2-builder-draft-candidate.json');$entities[$d['entityDraft']['code']]=$this->entity($s,$d['entityDraft']);$def=$s->saveDraft($d['programDraft'])['generatedDefinition'];
        self::assertSame('container_pagamentos_progress.create',$def['permissions']['create']);self::assertFalse($def['permissions']['edit']);self::assertSame('container_pagamentos_progress.delete',$def['permissions']['delete']);
        self::assertArrayHasKey('create',$def['dataSource']['api']);self::assertArrayHasKey('delete',$def['dataSource']['api']);self::assertArrayNotHasKey('update',$def['dataSource']['api']);self::assertSame('pagamento_key',$def['dataModel']['primaryKey']);
    }
    public function testOnlyTitleIdIsWritableAndUsesFixedRemoteOptions(): void {
        $d=$this->json('pagamentos-1.0.2-builder-draft-candidate.json');$fields=array_column($d['entityDraft']['fields'],null,'code');$w=array_values(array_filter($fields,fn($f)=>($f['options']['writable']??false)===true));
        self::assertSame(['num_id_tit_ap'],array_column($w,'code'));$f=$fields['num_id_tit_ap'];self::assertTrue($f['required']);self::assertSame('integer',$f['dataType']);self::assertSame('values.num_id_tit_ap',$f['apiWritePath']);self::assertTrue($f['options']['readonlyOnEdit']);
        self::assertSame(['operationCode'=>'pagamentos_disponiveis','valueField'=>'num_id_tit_ap','textField'=>'label'],$f['options']['api']['optionsSource']);
    }
    public function testExistingIdentityAndRequiredContextArePreserved(): void {
        $b=$this->json('pagamentos-builder-draft.json');$d=$this->json('pagamentos-1.0.2-builder-draft-candidate.json');
        foreach(['programCode','builderEntityCode','screenId','permissionPrefix','contextFields','crudOptions'] as $key)self::assertSame($b['programDraft'][$key],$d['programDraft'][$key]);
        self::assertSame('op1006',$d['programDraft']['programCode']);self::assertSame('1.0.2',$d['programDraft']['version']);self::assertTrue($d['programDraft']['contextFields'][0]['required']);
        self::assertTrue($d['programDraft']['allowCreate']);self::assertFalse($d['programDraft']['allowUpdate']);self::assertTrue($d['programDraft']['allowDelete']);
    }
    public function testGeneratedFormUsesDropdownAndKeepsOtherFieldsReadonly(): void {
        $entities=[];$s=$this->service($entities);$d=$this->json('pagamentos-1.0.2-builder-draft-candidate.json');$entities[$d['entityDraft']['code']]=$this->entity($s,$d['entityDraft']);$def=$s->saveDraft($d['programDraft'])['generatedDefinition'];$f=$def['dataModel']['fields']['num_id_tit_ap'];
        self::assertSame('dropdown',$f['editor']);self::assertSame('options.num_id_tit_ap',$f['optionsSource']['endpointId']);
        foreach($def['dataModel']['fields'] as $name=>$field)if($name!=='num_id_tit_ap')self::assertTrue($field['readonly']);
    }
    public function testPanel107ChangesOnlyPaymentHeaderAndVersion(): void {
        $b=$this->json('painel-1.0.6-builder-draft-candidate.json');$d=$this->json('painel-1.0.7-builder-draft-candidate.json');self::assertSame('1.0.7',$d['programDraft']['version']);self::assertCount(14,$d['programDraft']['formOptions']['tabs']);$d['programDraft']['version']='1.0.6';
        foreach($d['programDraft']['formOptions']['tabs'] as &$tab)if($tab['id']==='pagamentos'){self::assertSame('ciw.container-pagamentos',$tab['linkedPage']['screenId']);self::assertFalse($tab['linkedPage']['hideHeader']);$tab['linkedPage']['hideHeader']=true;}unset($tab);self::assertSame($b,$d);
    }
    public function testOptionsCannotAcceptFreeParametersOrUrl(): void {
        $entities=[];$s=$this->service($entities);$this->expectException(\App\Runtime\RuntimeHttpException::class);$this->invoke($s,'normalizeApiOptionsSource',['operationCode'=>'pagamentos_disponiveis','valueField'=>'num_id_tit_ap','textField'=>'label','parameters'=>['modalidade'=>'after_di']]);
    }
}
