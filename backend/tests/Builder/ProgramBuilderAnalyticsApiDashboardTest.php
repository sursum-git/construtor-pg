<?php
namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Entity\BuilderModule;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderModuleRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProgramBuilderAnalyticsApiDashboardTest extends TestCase
{
    public function testExternalValidationAcceptsReadonlyApiAnalytics():void
    {
        $entity=['code'=>'consultas','name'=>'Consultas','entityType'=>'api','createPhysicalTable'=>false,'skipStructureValidation'=>true,'apiSource'=>['listEndpoint'=>['url'=>'https://trusted.example/read','method'=>'POST'],'listResponse'=>['itemsPath'=>'data']],'fields'=>[['code'=>'field_1','label'=>'Total','dataType'=>'integer','primaryKey'=>true,'readonlyField'=>true,'apiJsonPath'=>'field_1']]];
        $program=['programCode'=>'op1015','programTitle'=>'Painel','module'=>'operacional','pageType'=>'analytics','screenId'=>'iol.test','builderEntityCode'=>'consultas','allowCreate'=>false,'allowUpdate'=>false,'allowDelete'=>false,'analyticsConfig'=>['datasets'=>[['id'=>'resumo','source'=>['type'=>'api','entityCode'=>'consultas'],'fields'=>['field_1']]],'dashboard'=>['id'=>'inicio','tiles'=>[['id'=>'total','type'=>'kpi','valueField'=>'field_1']]]]];
        $result=$this->service()->validateExternalDraft(['entityDraft'=>$entity,'programDraft'=>$program]);
        self::assertTrue($result['readyToApply']);self::assertSame('analytics',$result['generatedDefinition']['pageType']);
    }
    private function service(): ProgramBuilderService
    {
        $entity = (new BuilderEntity())->setCode('consultas')->setName('Consultas')->setEntityType('api')
            ->setMetadata(['apiSource' => ['listEndpoint' => ['url' => '/records'], 'detailEndpoint' => ['url' => '/record']]]);
        foreach (range(1, 20) as $index) {
            $entity->addField((new BuilderField())->setCode('field_' . $index)->setLabel('Campo ' . $index)
                ->setDataType('integer')->setPrimaryKey($index === 1)->setOptions([
                    'readonly' => true, 'writable' => false, 'api' => ['showInGrid' => true, 'showInFilter' => true],
                ]));
        }
        $entity->addField((new BuilderField())->setCode('json_data')->setLabel('JSON')->setDataType('json'));
        $modules = $this->createStub(BuilderModuleRepository::class);
        $modules->method('findOneBy')->willReturn((new BuilderModule())->setCode('operacional')->setName('Operacional')
            ->setAbbreviation('op')->setNumberStart(1000)->setNumberEnd(1999));
        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturn($entity);
        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn(true);
        $overrides = [BuilderModuleRepository::class => $modules, BuilderEntityRepository::class => $entities, PermissionResolver::class => $permissions];
        $reflection = new \ReflectionClass(ProgramBuilderService::class);
        $args = [];
        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $type = $parameter->getType()->getName();
            $args[] = $overrides[$type] ?? $this->createStub($type);
        }
        return $reflection->newInstanceArgs($args);
    }


    public function testApiDashboardRoundtripUsesOfficialGeneration(): void
    {
        $svc=$this->service();
        $payload=['programCode'=>'op1015','programTitle'=>'Painel IOL','module'=>'operacional','pageType'=>'analytics','screenId'=>'iol.inicial-gerencial','builderEntityCode'=>'consultas','allowCreate'=>false,'allowUpdate'=>false,'allowDelete'=>false,
        'analyticsConfig'=>['datasets'=>[['id'=>'resumo','source'=>['type'=>'api','entityCode'=>'consultas'],'fields'=>['field_1']]],'dashboard'=>['id'=>'dashboard','tiles'=>[['id'=>'total','type'=>'kpi','valueField'=>'field_1']]]]];
        $draft=$svc->saveDraft($payload);
        self::assertSame('api',$draft['generatedDefinition']['analytics']['datasets'][0]['source']['type']);
        self::assertFalse($draft['generatedDefinition']['permissions']['materialize']);
        self::assertSame('dashboard',$draft['generatedDefinition']['analytics']['presentation']['mode']);
        $next=$svc->saveDraft(array_replace($payload,['analyticsConfig'=>$draft['builderConfig']['analyticsConfig']]));
        self::assertSame($draft['generatedDefinition']['analytics']['datasets'],$next['generatedDefinition']['analytics']['datasets']);
        $export=(new \ReflectionMethod($svc,'externalProgramPayload'))->invoke($svc,$draft['builderConfig'],$draft['generatedDefinition']);
        self::assertSame($draft['builderConfig']['analyticsConfig'],$export['analyticsConfig']);
    }
}
