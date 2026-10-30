<?php
namespace App\Tests\Runtime;
use App\Entity\BuilderEntity;use App\Entity\BuilderField;use App\Repository\BuilderEntityRepository;
use App\Runtime\RuntimeApiEntityActionService;use App\Runtime\RuntimeExternalJsonClient;use App\Runtime\RuntimeTransactionService;use App\Runtime\PermissionResolver;use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\Attributes\DataProvider;use PHPUnit\Framework\TestCase;
final class RuntimeApiProcessTest extends TestCase {
    private function service(callable $request,bool $allowed=true,array $override=[],string $contract='sursum-programs',string $parameterType='integer'): RuntimeApiEntityActionService {
        $entity=(new BuilderEntity())->setCode('panel')->setEntityType('api')->setMetadata(['apiSource'=>['responseContract'=>$contract,'authHeaders'=>['X-Token'=>'test-only'], 'listEndpoint'=>['url'=>'/read'],'listResponse'=>['itemsPath'=>'data'],'operations'=>[
            array_replace(['code'=>'sync','allowProcess'=>true,'type'=>'custom','method'=>'POST','path'=>'/run','requestContextFields'=>['container_id'],'itemPath'=>'data','bodyTemplate'=>['operation'=>'sincronizar','resource'=>'impostos']],$override)]]]);
        $entity->addField((new BuilderField())->setCode('container_id')->setDataType('integer')->setPrimaryKey(true));
        $entity->addField((new BuilderField())->setCode('quantidade')->setDataType($parameterType)->setRequired(true));
        $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($entity);
        $client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturnCallback($request);
        $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturn($allowed);
        return new RuntimeApiEntityActionService($repo,$this->createStub(RuntimeTransactionService::class),$client,$permissions);
    }
    private function invokeProcess(RuntimeApiEntityActionService $service,array $payload): array {
        return $service->handle('ciw.process','run',['entityCode'=>'panel','operation'=>'process','allowProcess'=>true,'permissionPrefix'=>'panel','processOptions'=>['operationCode'=>'sync','parameterFields'=>['quantidade']],'contextFields'=>[['field'=>'container_id','required'=>true,'readonly'=>true]]],$payload);
    }
    public function testOnlyDeclaredFieldsAndContextReachRegisteredPost(): void {
        $calls=0;$service=$this->service(function($url,$method,$headers,$body)use(&$calls){++$calls;self::assertSame('/run',$url);self::assertSame('POST',$method);self::assertSame(['operation'=>'sincronizar','resource'=>'impostos','quantidade'=>3,'container_id'=>17],$body);return ['status'=>200,'body'=>['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.']]];});
        $result=$this->invokeProcess($service,['parameters'=>['quantidade'=>3,'container_id'=>17],'context'=>['container_id'=>'17']]);
        self::assertSame(1,$calls);self::assertSame('succeeded',$result['status']);self::assertSame(['type'=>'message','message'=>'Confirmado.'],$result['result']);
    }
    public static function invalidPayloads(): array {return [
        [['parameters'=>['quantidade'=>3],'context'=>[]]],
        [['parameters'=>['quantidade'=>3,'operation'=>'delete'],'context'=>['container_id'=>17]]],
        [['parameters'=>['quantidade'=>3,'container_id'=>18],'context'=>['container_id'=>17]]],
        [['parameters'=>['quantidade'=>'3 OR true'],'context'=>['container_id'=>17]]],
        [['parameters'=>['quantidade'=>3],'context'=>['container_id'=>17],'url'=>'/evil']],
    ];}
    #[DataProvider('invalidPayloads')]
    public function testInvalidInputNeverCallsExternalApi(array $payload): void {
        $service=$this->service(function(){self::fail('API nao deve ser chamada.');});$this->expectException(RuntimeHttpException::class);$this->invokeProcess($service,$payload);
    }
    public function testPermissionDeniedNeverCallsApi(): void {
        $service=$this->service(function(){self::fail('API nao deve ser chamada.');},false);$this->expectException(RuntimeHttpException::class);$this->invokeProcess($service,['parameters'=>['quantidade'=>3],'context'=>['container_id'=>17]]);
    }
    public function testInvalidResultCannotReportSuccess(): void {
        $service=$this->service(fn()=>['status'=>200,'body'=>['data'=>['type'=>'report','url'=>'https://evil.example']]]);$this->expectException(RuntimeHttpException::class);$this->invokeProcess($service,['parameters'=>['quantidade'=>3],'context'=>['container_id'=>17]]);
    }
    public function testGridResultUsesClosedColumnsAndScalarRows(): void {
        $service=$this->service(fn()=>['status'=>200,'body'=>['data'=>['type'=>'grid','columns'=>[['field'=>'total','title'=>'Total']],'data'=>[['total'=>2]]]]]);
        self::assertSame(2,$this->invokeProcess($service,['parameters'=>['quantidade'=>3],'context'=>['container_id'=>17]])['result']['data'][0]['total']);
    }
 public function testEndpointWithoutExplicitEnableFlagNeverCallsApi(): void {
  $service=$this->service(function(){self::fail('API nao deve ser chamada.');});$this->expectException(RuntimeHttpException::class);
  $service->handle('ciw.process','run',['entityCode'=>'panel','operation'=>'process','permissionPrefix'=>'panel'],['parameters'=>['quantidade'=>3],'context'=>['container_id'=>17]]);
 }

    public static function disabledOperations(): array {return [[['allowProcess'=>false]],[['allowProcess'=>'true']],[['method'=>'GET']],[['type'=>'list']],[['path'=>'']], [[], 'generic']];}
    #[DataProvider('disabledOperations')]
    public function testUnconfiguredOrNonOptedInSourceNeverCallsApi(array $override,string $contract='sursum-programs'): void {
        $service=$this->service(function(){self::fail('API nao deve ser chamada.');},true,$override,$contract);$this->expectException(RuntimeHttpException::class);
        $this->invokeProcess($service,['parameters'=>['quantidade'=>3],'context'=>['container_id'=>17]]);
    }

    public static function invalidTypedParameters(): array {return [
        ['datetime','2026-02-31T12:00:00'],
        ['datetime','2026-10-16T24:00:00'],
        ['datetime','2026-10-16T12:00:00+25:00'],
        ['date','2026-02-31'],
        ['boolean',1],
        ['decimal','1.2'],
    ];}
    #[DataProvider('invalidTypedParameters')]
    public function testInvalidTypedParameterNeverCallsApi(string $type,mixed $value): void {
        $service=$this->service(function(){self::fail('API nao deve ser chamada.');},true,[],'sursum-programs',$type);
        $this->expectException(RuntimeHttpException::class);
        $this->invokeProcess($service,['parameters'=>['quantidade'=>$value],'context'=>['container_id'=>17]]);
    }
}
