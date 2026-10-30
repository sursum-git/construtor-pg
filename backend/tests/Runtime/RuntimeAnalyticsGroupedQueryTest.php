<?php
namespace App\Tests\Runtime;
use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\{RuntimeAnalyticsService,RuntimeAnalyticsApiDatasetService,RuntimeExternalJsonClient,RuntimeEntityDefinitionResolver,PermissionResolver,StructuralIntegrityService,ProgramCustomizationResolver,RuntimeHttpException};
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class RuntimeAnalyticsGroupedQueryTest extends TestCase
{
    private function service(bool $valid=true,bool $allowed=true,bool $denyLast=false):RuntimeAnalyticsService {
        $screen=(new ScreenDefinition())->setScreenId('iol.test')->setPageType('analytics')->setStatus('published')->setDefinition(['analytics'=>['presentation'=>['mode'=>'dashboard'],'datasets'=>[['id'=>'resumo','source'=>['type'=>'api','entityCode'=>'iol_resumo'],'limit'=>1],['id'=>'vendedores','source'=>['type'=>'api','entityCode'=>'iol_vendedores'],'limit'=>1000]]]]);
        $repo=$this->createStub(ScreenDefinitionRepository::class);$repo->method('findPublishedByScreenId')->willReturn($screen);
        $custom=$this->createStub(ProgramCustomizationResolver::class);$custom->method('resolve')->willReturn(null);
        $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturnCallback(fn($code)=>$allowed&&(!$denyLast||$code!=='iol_vendedores.read'));
        $api=$this->createMock(RuntimeAnalyticsApiDatasetService::class);$api->expects($valid&&$allowed&&!$denyLast?self::exactly(2):self::never())->method('run')->willReturnCallback(fn($s,$d)=>['data'=>[['id'=>1]],'total'=>1,'datasetId'=>$d['id']]);
        return new RuntimeAnalyticsService($repo,$this->createStub(RuntimeEntityDefinitionResolver::class),$this->createStub(Connection::class),$permissions,$this->createStub(StructuralIntegrityService::class),$custom,null,null,$api,new RuntimeExternalJsonClient());
    }
    public function testReturnsBothDatasetsInOneEnvelope():void {
        $r=$this->service()->run('iol.test',['datasetIds'=>['resumo','vendedores']]);
        self::assertSame(['resumo','vendedores'],array_keys($r['datasets']));self::assertSame('bypassed',$r['datasets']['resumo']['_runtime']['analyticsCache']['status']);
    }
    public function testRepeatedIdsRejectedBeforeReading():void {$this->expectException(RuntimeHttpException::class);$this->service(false)->run('iol.test',['datasetIds'=>['resumo','resumo']]);}
    public function testIdentityOverrideRejectedBeforeReading():void {$this->expectException(RuntimeHttpException::class);$this->service(false)->run('iol.test',['datasetIds'=>['resumo','vendedores'],'login'=>'other']);}
    public function testEveryPermissionCheckedBeforeReading():void {$this->expectException(RuntimeHttpException::class);$this->service(allowed:false)->run('iol.test',['datasetIds'=>['resumo','vendedores']]);}
    public function testLastDatasetPermissionIsCheckedBeforeTheFirstRead():void {$this->expectException(RuntimeHttpException::class);$this->service(denyLast:true)->run('iol.test',['datasetIds'=>['resumo','vendedores']]);}
}
