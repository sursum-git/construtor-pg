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
final class ContainerItemsMetadataTest extends TestCase
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
        foreach (['cabecalho', 'despesas', 'processos', 'itens'] as $domain) {
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

    public function testRegisteredSourceHasOnlySixFixedContextualOperations(): void {
        $entities=[];$service=$this->service($entities);$source=$this->invoke($service,'normalizeApiSourceRegistryPayload',$this->json('itens-api-source-candidate.json'));
        self::assertSame('motor_progress_container_itens',$source['code']);self::assertCount(6,$source['operations']);
        foreach($source['operations'] as $op){self::assertSame(['container_id'],$op['requestContextFields']);self::assertSame('POST',$op['method']);self::assertFalse($op['allowProcess']??false);}
    }
    public function testItemCandidateGeneratesNativeCrudThroughRealBuilder(): void {
        $entities=[];$service=$this->service($entities);$input=$this->json('itens-1.0.1-builder-draft-candidate.json');
        $entities[$input['entityDraft']['code']]=$this->entity($service,$input['entityDraft']);$result=$service->saveDraft($input['programDraft']);$definition=$result['generatedDefinition'];
        self::assertSame('crud',$definition['pageType']);self::assertSame('op1002',$definition['runtime']['programId']);
        foreach(['read','get','create','update','delete','options.item_proc_compra_id'] as $endpoint){self::assertArrayHasKey($endpoint,$definition['dataSource']['api']);}
        self::assertSame('form',$definition['crud']['grid']['editing']['mode']);self::assertArrayNotHasKey('batch',$definition['dataSource']['api']);
    }
    public function testExactlyElevenLegacyEditsAndPurchaseOnlyAtCreate(): void {
        $draft=$this->json('itens-1.0.1-builder-draft-candidate.json');$fields=array_column($draft['entityDraft']['fields'],null,'code');
        $expected=['qt_alocada','qt_confirm_emb','qt_recbto_fisico','log_rec_doc_prazo_emb','observacao','cod_tipo_calculo','perc_custo','log_calc_icms','log_calc_ipi','perc_icms','perc_ipi','item_proc_compra_id'];
        $writable=array_keys(array_filter($fields,fn($field)=>!$field['readonlyField']));sort($expected);sort($writable);self::assertSame($expected,$writable);
        foreach($writable as $name){self::assertSame('values.'.$name,$fields[$name]['apiWritePath']);}
        self::assertTrue($fields['item_proc_compra_id']['options']['readonlyOnEdit']);self::assertTrue($fields['container_id']['readonlyField']);
    }
    public function testContextAndExistingProgramIdentityArePreserved(): void {
        $draft=$this->json('itens-1.0.1-builder-draft-candidate.json');$before=$this->json('itens-builder-draft.json');
        foreach(['programCode','builderEntityCode','screenId','permissionPrefix','contextFields','crudOptions'] as $name){self::assertSame($before['programDraft'][$name],$draft['programDraft'][$name]);}
        self::assertSame('1.0.1',$draft['programDraft']['version']);foreach(['allowCreate','allowUpdate','allowDelete'] as $name){self::assertTrue($draft['programDraft'][$name]);}
    }
    public function testLookupUsesRegisteredOperationAndReadonlyTextPresentation(): void {
        $entities=[];$service=$this->service($entities);$input=$this->json('itens-1.0.1-builder-draft-candidate.json');$entities[$input['entityDraft']['code']]=$this->entity($service,$input['entityDraft']);
        $definition=$service->saveDraft($input['programDraft'])['generatedDefinition'];$field=$definition['dataModel']['fields']['item_proc_compra_id'];
        self::assertSame('dropdown',$field['editor']);self::assertSame('options.item_proc_compra_id',$field['optionsSource']['endpointId']);self::assertSame(['saldo_disponivel_texto'],$field['optionsSource']['copyFields']);
        self::assertSame('text',$definition['dataModel']['fields']['saldo_disponivel_texto']['type']);self::assertTrue($definition['dataModel']['fields']['saldo_disponivel_texto']['readonly']);
    }
    public function testAllocationIsExplicitRequiredQuantityWithoutGlobalPurchaseCrud(): void {
        $draft=$this->json('itens-1.0.1-builder-draft-candidate.json');$fields=array_column($draft['entityDraft']['fields'],null,'code');
        self::assertTrue($fields['qt_alocada']['required']);self::assertSame('decimal',$fields['qt_alocada']['dataType']);self::assertSame(0.01,$fields['qt_alocada']['options']['min']);
        self::assertSame('container_itens_progress',$draft['entityDraft']['code']);self::assertSame([], $draft['programDraft']['rowLinks']);
        foreach($this->json('itens-api-source-candidate.json')['operations'] as $op){self::assertNotSame('batch',$op['type']);self::assertStringNotContainsString('itens-proc-compra',$op['path']);}
    }
    public function testPanelKeepsFourteenTabsAndOnlyChangesItemsTitle(): void {
        $before=$this->json('painel-1.0.4-builder-draft-candidate.json');$after=$this->json('painel-1.0.5-builder-draft-candidate.json');self::assertCount(14,$after['programDraft']['formOptions']['tabs']);
        self::assertSame('1.0.5',$after['programDraft']['version']);$after['programDraft']['version']=$before['programDraft']['version'];
        foreach($after['programDraft']['formOptions']['tabs'] as $index=>&$tab){if(($tab['linkedPage']['screenId']??null)==='ciw.container-itens'){self::assertSame('Itens / Alocação',$tab['title']);$tab['title']=$before['programDraft']['formOptions']['tabs'][$index]['title'];}}unset($tab);
        self::assertSame($before,$after);
    }
    public function testPanel106OnlyEnablesNativeItemsHeaderAndPreservesEverythingElse(): void {
        $before=$this->json('painel-1.0.5-builder-draft-candidate.json');$after=$this->json('painel-1.0.6-builder-draft-candidate.json');
        self::assertSame('1.0.6',$after['programDraft']['version']);self::assertCount(14,$after['programDraft']['formOptions']['tabs']);
        $after['programDraft']['version']='1.0.5';
        foreach($after['programDraft']['formOptions']['tabs'] as &$tab){if($tab['id']==='itens'){self::assertFalse($tab['linkedPage']['hideHeader']);self::assertSame('ciw.container-itens',$tab['linkedPage']['screenId']);$tab['linkedPage']['hideHeader']=true;}}unset($tab);
        self::assertSame($before,$after);
    }
    public function testLookupCannotDeclareFreeUrlOrParameters(): void {
        $entities=[];$service=$this->service($entities);$this->expectException(\App\Runtime\RuntimeHttpException::class);
        $this->invoke($service,'normalizeApiOptionsSource',['operationCode'=>'itens_disponiveis','valueField'=>'item_proc_compra_id','textField'=>'label','url'=>'https://invalid.example']);
    }
}






