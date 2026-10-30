<?php
namespace App\Tests\Runtime;

use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Repository\BuilderEntityRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeAnalyticsApiDatasetService;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;

final class RuntimeAnalyticsApiDatasetServiceTest extends TestCase
{
    private function service(bool $allowed = true, string $type = 'api', array $rows = [['id'=>1,'valor'=>44.0]], array $pages = []): RuntimeAnalyticsApiDatasetService
    {
        $entity=(new BuilderEntity())->setCode('iol_resumo')->setEntityType($type)->setMetadata(['apiSource'=>['listEndpoint'=>['url'=>'http://server/read']]]);
        foreach (['id'=>'integer','valor'=>'decimal'] as $code=>$dataType) {
            $entity->getFields()->add((new BuilderField())->setCode($code)->setLabel($code)->setDataType($dataType));
        }
        $repo=$this->createStub(BuilderEntityRepository::class);
        $repo->method('findOneBy')->willReturn($entity);
        $permissions=$this->createStub(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn($allowed);
        $actions=$this->createMock(RuntimeApiEntityActionService::class);
        $call=$actions->expects($pages ? self::exactly(count($pages)) : ($allowed && $type==='api' ? self::atMost(1) : self::never()))->method('handle')->with('iol.inicial','read',['entityCode'=>'iol_resumo','operation'=>'read'],self::callback(static fn(array $payload):bool => !array_diff(array_keys($payload),['take','skip','sort'])));
        if ($pages) { $call->willReturnOnConsecutiveCalls(...$pages); } else { $call->willReturn(['data'=>$rows,'total'=>count($rows)]); }
        return new RuntimeAnalyticsApiDatasetService($repo,$permissions,$actions);
    }
    private function dataset(): array { return ['id'=>'resumo','source'=>['type'=>'api','entityCode'=>'iol_resumo'],'fields'=>['id','valor'],'limit'=>100]; }
    public function testProducesTypedColumnsAndDropsUndeclaredFields(): void
    {
        $result=$this->service(rows:[['id'=>1,'valor'=>44.0,'private'=>'secret']])->run('iol.inicial', $this->dataset(), [], 'default');
        self::assertSame([['id'=>1,'valor'=>44.0]],$result['data']);
        self::assertSame('decimal',$result['columns'][1]['type']);
        self::assertSame('resumo',$result['datasetId']);
    }
    public function testForbiddenBeforeTransport(): void { $this->expectException(RuntimeHttpException::class); $this->service(false)->run('iol.inicial',$this->dataset(),[],'default'); }
    public function testPersistentEntityRejectedBeforeTransport(): void { $this->expectException(RuntimeHttpException::class); $this->service(type:'persistence')->run('iol.inicial',$this->dataset(),[],'default'); }
    public function testSpoofedLoginRejected(): void { $this->expectException(RuntimeHttpException::class); $this->service()->run('iol.inicial',$this->dataset(),['login'=>'other'],'default'); }
    public function testDispatcherEnvelopeIsIgnoredAndNeverForwarded(): void
    {
        $result=$this->service()->run('iol.inicial',$this->dataset(),['datasetId'=>'resumo','entityCode'=>'ignored','operation'=>'ignored','programId'=>'ignored','actionId'=>'analytics.query.run','_runtimeEndpoint'=>['actionId'=>'analytics.query.run','entityCode'=>null,'operation'=>null,'programId'=>null,'traceability'=>[]]],'default');
        self::assertSame(1,$result['total']);
    }
    public function testMalformedNumericResponseRejected(): void { $this->expectException(RuntimeHttpException::class); $this->service(rows:[['id'=>1,'valor'=>'not a number']])->run('iol.inicial',$this->dataset(),[],'default'); }
    public function testMissingResponseFieldIsNotZero(): void { $this->expectException(RuntimeHttpException::class); $this->service(rows:[['id'=>1]])->run('iol.inicial',$this->dataset(),[],'default'); }
    public function testUndeclaredSortFieldRejected(): void { $this->expectException(RuntimeHttpException::class); $this->service()->run('iol.inicial',$this->dataset(),['sort'=>[['field'=>'private','dir'=>'asc']]],'default'); }
    public function testAggregatesRejected(): void { $this->expectException(RuntimeHttpException::class); $this->service()->run('iol.inicial',$this->dataset()+['measures'=>[['field'=>'valor','aggregate'=>'sum']]],[],'default'); }
    public function testCollectsEveryPageUpToTheDeclaredLimit(): void
    {
        $dataset=$this->dataset();$dataset['limit']=1000;
        $rows=array_fill(0,500,['id'=>1,'valor'=>44.0]);
        $result=$this->service(pages:[['data'=>$rows,'total'=>501],['data'=>[['id'=>501,'valor'=>1.0]],'total'=>501]])->run('iol.inicial',$dataset,['take'=>1000],'default');
        self::assertCount(501,$result['data']);self::assertSame(501,$result['data'][500]['id']);
    }
}
