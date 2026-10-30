<?php
namespace App\Tests\Runtime;
use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\{RuntimeAnalyticsService,RuntimeAnalyticsApiDatasetService,RuntimeEntityDefinitionResolver,PermissionResolver,StructuralIntegrityService,ProgramCustomizationResolver,RuntimeHttpException};
use Doctrine\DBAL\{Connection,DriverManager};
use PHPUnit\Framework\TestCase;

final class RuntimeAnalyticsApiCacheTest extends TestCase
{
    private function service(): RuntimeAnalyticsService {
        $screen=(new ScreenDefinition())->setScreenId('iol.test')->setPageType('analytics')->setStatus('published')->setDefinition([
            'analytics'=>['datasets'=>[['id'=>'resumo','source'=>['type'=>'api','entityCode'=>'iol_resumo'],'executionMode'=>'cached']]]]);
        $screens=$this->createStub(ScreenDefinitionRepository::class);$screens->method('findPublishedByScreenId')->willReturn($screen);
        $entities=$this->createStub(RuntimeEntityDefinitionResolver::class);
        $conn=$this->createMock(Connection::class);
        $conn->expects(self::never())->method('executeQuery');
        $conn->expects(self::never())->method('fetchAssociative');
        $conn->expects(self::never())->method('insert');
        $integrity=$this->createStub(StructuralIntegrityService::class);
        $custom=$this->createStub(ProgramCustomizationResolver::class);$custom->method('resolve')->willReturn(null);
        $api=$this->createStub(RuntimeAnalyticsApiDatasetService::class);$api->method('run')->willReturn(['data'=>[['id'=>1]],'total'=>1,'columns'=>[]]);
        return new RuntimeAnalyticsService($screens,$entities,$conn,$this->createStub(PermissionResolver::class),$integrity,$custom,null,null,$api);
    }
    public function testCachedRequestStillExecutesApiWithoutReadingCache():void {
        $r=$this->service()->run('iol.test',['datasetId'=>'resumo','executionMode'=>'cached']);
        self::assertSame([['id'=>1]],$r['data']);self::assertSame('bypassed',$r['_runtime']['analyticsCache']['status']);
    }
    public function testApiCacheStatusNeverReadsOtherUsersCache():void { self::assertSame('bypassed',$this->service()->cacheStatus('iol.test',['datasetId'=>'resumo'])['status']); }
    public function testApiCannotMaterialize():void {
        try {$this->service()->materialize('iol.test',['datasetId'=>'resumo']);self::fail('Materializacao deveria ser bloqueada.');}
        catch(RuntimeHttpException $e){ self::assertSame('Materializacao indisponivel para fontes API.', $e->getMessage()); }
    }
}
