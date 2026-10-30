<?php
namespace App\Tests\Runtime;
use App\Runtime\{ApiCompanyRequestAdapter,ApiCompanyContext,RuntimeHttpException};
use PHPUnit\Framework\TestCase;

final class RuntimeApiCompanyContextTest extends TestCase
{
    public function testSingleAdapterIsUsedForRegisteredAuxiliaryCallsWithoutTableClassification():void
    {
        $reflection=new \ReflectionClass(\App\Runtime\RuntimeApiEntityActionService::class);$service=$reflection->newInstanceWithoutConstructor();
        $context=$this->context();
        $endpoint=['url'=>'https://example.invalid/options','method'=>'POST'];
        $definition=['apiSource'=>['operations'=>[['code'=>'opcoes','url'=>$endpoint['url'],'method'=>'POST']]],'apiCompanyContext'=>['version'=>1,'moduleId'=>'ciw','adapter'=>'empresa'],'_apiCompanyOperation'=>'options'];
        self::assertSame($context->mapping,$reflection->getMethod('companyEndpointMapping')->invoke($service,$definition,$endpoint,$context));
    }
    private function context():ApiCompanyContext
    {return new ApiCompanyContext('ciw','ciw','company',2,['key'=>'ima','companyCode'=>'ima','externalCompanyId'=>1,'ems2Code'=>1,'ems5Code'=>100,'revision'=>1],['body'=>['empresa_id'=>'externalCompanyId']]);}
    public function testTrustedValuesAreInjectedWithoutChangingUrlOrCredentials():void
    {
        $a=new ApiCompanyRequestAdapter();$c=$this->context();$r=$a->apply($c,$c->mapping,['url'=>'https://example.invalid/api','method'=>'POST'],['headers'=>['Authorization'=>'fixture-only'],'query'=>[],'body'=>['operation'=>'read']]);
        self::assertSame(1,$r['body']['empresa_id']);self::assertSame(['Authorization'=>'fixture-only'],$r['headers']);self::assertSame([],$r['query']);
        self::assertSame(['operation'=>'read'],['operation'=>$r['body']['operation']]);
    }
    public function testConflictsInPayloadTemplateQueryHeadersAndBatchAreRejected():void
    {
        $a=new ApiCompanyRequestAdapter();$c=$this->context();
        foreach([['headers'=>[],'query'=>[],'body'=>['empresa_id'=>5]],['headers'=>[],'query'=>[],'body'=>['changes'=>[['record'=>['empresa_id'=>5]]]]]]as $request){
            try{$a->apply($c,$c->mapping,['method'=>'POST','url'=>'https://example.invalid'],$request);self::fail('Company conflict accepted');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_CONTEXT_CONFLICT',$e->getErrorCode());}
        }
        foreach([['query'=>['empresa'=>'externalCompanyId']],['headers'=>['X-Empresa'=>'externalCompanyId']]]as $mapping){
            $request=['headers'=>['x-empresa'=>'5'],'query'=>['empresa'=>5],'body'=>[]];
            try{$a->apply($c,$mapping,['method'=>'POST','url'=>'https://example.invalid'],$request);self::fail('Transport conflict accepted');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_CONTEXT_CONFLICT',$e->getErrorCode());}
        }
        try{$a->apply($c,['query'=>['empresa'=>'externalCompanyId']],['method'=>'GET','url'=>'https://example.invalid?empresa=5'],['headers'=>[],'query'=>[],'body'=>null]);self::fail('URL company accepted');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_CONTEXT_CONFLICT',$e->getErrorCode());}
        try{$a->apply($c,$c->mapping,['method'=>'POST','bodyTemplate'=>['empresa_id'=>5]],['headers'=>[],'query'=>[],'body'=>['empresa_id'=>1]]);self::fail('Conflicting static template accepted');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_CONTEXT_CONFLICT',$e->getErrorCode());}
    }
    public function testRemovingConfigurationStillVerifiesTheEntityBeforeAnyHttp():void
    {
        $entity=(new \App\Entity\BuilderEntity())->setCode('a')->setEntityType('api')->setMetadata([]);
        $entities=$this->createStub(\App\Repository\BuilderEntityRepository::class);$entities->method('findOneBy')->willReturn($entity);
        $classifier=$this->createStub(\App\Runtime\ApiCompanyAuditClassifier::class);$classifier->method('assertEntity')->willThrowException(new RuntimeHttpException('INTEGRITY_INVALID','Tampered',403));
        $client=$this->createMock(\App\Runtime\RuntimeExternalJsonClient::class);$client->expects(self::never())->method('request');
        $service=new \App\Runtime\RuntimeApiEntityActionService($entities,$this->createStub(\App\Runtime\RuntimeTransactionService::class),$client,null,null,$this->createStub(\App\Runtime\ApiCompanyContextResolver::class),new ApiCompanyRequestAdapter(),$classifier);
        $this->expectException(RuntimeHttpException::class);$this->expectExceptionMessage('Tampered');$service->handle('ciw.a','read',['entityCode'=>'a','operation'=>'read'],[]);
    }
    public function testRegisteredQueriesAndHeadersDistinguishOperationsAtTheSameUrl():void
    {
        $reflection=new \ReflectionClass(\App\Runtime\RuntimeApiEntityActionService::class);$service=$reflection->newInstanceWithoutConstructor();
        $context=new ApiCompanyContext('ciw','fixture','company',2,['externalCompanyId'=>5],['query'=>['company'=>'externalCompanyId']],['query-company'=>['query'=>['company'=>'externalCompanyId']]]);
        foreach(['queryParams','headers'] as $location){
            $list=['url'=>'/fixture/data','method'=>'GET',$location=>['resource'=>'rows']];
            $definition=['apiSource'=>['listEndpoint'=>$list,'operations'=>[['code'=>'countries','path'=>'/fixture/data','method'=>'GET',$location=>['resource'=>'countries']]]],
              'apiCompanyContext'=>['operations'=>['read'=>['mode'=>'company','adapter'=>'query-company'],'countries'=>['mode'=>'shared']]],'_apiCompanyOperation'=>'read'];
            self::assertSame($context->mapping,$reflection->getMethod('companyEndpointMapping')->invoke($service,$definition,$list,$context));
        }
    }
    public function testRuntimeInjectsPinnedContextIntoTheActualHttpCall():void
    {
        $entity=(new \App\Entity\BuilderEntity())->setCode('a')->setEntityType('api')->setMetadata([
            'apiCompanyContext'=>['version'=>1,'moduleId'=>'ciw','operations'=>['read'=>['mode'=>'company','adapter'=>'ciw']]],
            'apiSource'=>['authHeaders'=>[],'listEndpoint'=>['url'=>'https://example.invalid/rows','method'=>'POST','bodyTemplate'=>['operation'=>'read']],'listResponse'=>['itemsPath'=>'data']]]);
        $field=(new \App\Entity\BuilderField())->setCode('id')->setLabel('ID')->setDataType('integer')->setPrimaryKey(true);$entity->addField($field);
        $entities=$this->createStub(\App\Repository\BuilderEntityRepository::class);$entities->method('findOneBy')->willReturn($entity);
        $client=$this->createMock(\App\Runtime\RuntimeExternalJsonClient::class);
        $client->expects(self::once())->method('request')->willReturnCallback(function($url,$method,$headers,$body){self::assertSame(1,($body['empresa_id']??null));self::assertSame('read',$body['operation']);return ['body'=>['data'=>[['id'=>7]]],'status'=>200];});
        $resolver=$this->createMock(\App\Runtime\ApiCompanyContextResolver::class);$resolver->expects(self::exactly(2))->method('forOperation')->with('ciw.a','a','read',2)->willReturn($this->context());
        $service=new \App\Runtime\RuntimeApiEntityActionService($entities,$this->createStub(\App\Runtime\RuntimeTransactionService::class),$client,null,null,$resolver,new ApiCompanyRequestAdapter(),$this->createStub(\App\Runtime\ApiCompanyAuditClassifier::class));
        self::assertSame(1,$service->handle('ciw.a','read',['entityCode'=>'a','operation'=>'read'],['apiCompany'=>['selectionRevision'=>2]])['total']);
    }
}

