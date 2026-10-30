<?php
namespace App\Tests\Runtime;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Repository\BuilderEntityRepository;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeExternalJsonClient;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeTransactionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class RuntimeApiCollectionsOptionsTest extends TestCase {
    private function entity(): BuilderEntity {
        return (new BuilderEntity())->setCode('despesas')->setEntityType('api')->setMetadata(['apiSource'=>[
            'authHeaders'=>[], 'listEndpoint'=>['url'=>'/despesas','method'=>'POST'], 'listResponse'=>['itemsPath'=>'data'],
            'updateEndpoint'=>['url'=>'/despesas','method'=>'POST'], 'createEndpoint'=>['url'=>'/despesas','method'=>'POST','bodyTemplate'=>['operation'=>'create','relacs_desp_calculada'=>[99]]],
            'operations'=>[['code'=>'opcoes_despesas','path'=>'/despesas','method'=>'POST','itemsPath'=>'data','bodyTemplate'=>['operation'=>'opcoes_despesas']]]
        ]])->addField((new BuilderField())->setCode('despesa_id')->setDataType('integer')->setPrimaryKey(true)->setOptions(['api'=>['jsonPath'=>'despesa_id']]))
        ->addField((new BuilderField())->setCode('eh_imposto')->setDataType('boolean')->setOptions(['readonly'=>true,'api'=>['jsonPath'=>'eh_imposto']]))
        ->addField((new BuilderField())->setCode('relacs_desp_calculada')->setDataType('json')->setOptions(['editor'=>'multiselect','api'=>[
            'jsonPath'=>'relacs_desp_calculada','collection'=>['itemType'=>'integer'],
            'optionsSource'=>['operationCode'=>'opcoes_despesas','valueField'=>'despesa_id','textField'=>'descricao','copyFields'=>['eh_imposto'=>'log_imposto']]
        ]]));
    }
    private function service(array $response, ?array &$request=null): RuntimeApiEntityActionService {
        $repo=$this->createStub(BuilderEntityRepository::class); $repo->method('findOneBy')->willReturn($this->entity());
        $client=$this->createMock(RuntimeExternalJsonClient::class);
        $client->expects(self::once())->method('request')->willReturnCallback(function($source,$endpoint,$headers,$data)use($response,&$request){$request=['endpoint'=>$endpoint,'data'=>$data];return ['body'=>$response,'status'=>200];});
        return new RuntimeApiEntityActionService($repo,$this->createStub(RuntimeTransactionService::class),$client);
    }
    public function testCreateClearsCollectionDespiteNonemptyTemplate(): void {
        $request=null; $result=$this->service(['data'=>[]],$request)->handle('ciw.despesas','create',['entityCode'=>'despesas','operation'=>'create'],['relacs_desp_calculada'=>[]]);
        self::assertSame([], $request['data']['relacs_desp_calculada']); self::assertSame([], $result['relacs_desp_calculada']);
    }
    public function testCollectionPreservesIntegerIdsInReadAndNestedMapping(): void {
        $result=$this->service(['data'=>[['despesa_id'=>7,'relacs_desp_calculada'=>[2,2147483647]]]])->handle('ciw.despesas','read',['entityCode'=>'despesas'],[]);
        self::assertSame([2,2147483647],$result['data'][0]['relacs_desp_calculada']);
    }
    public function testContextFilterIsAppliedBeforePaginationAndTotal(): void {
        $result=$this->service(['data'=>[['despesa_id'=>7,'relacs_desp_calculada'=>[]],['despesa_id'=>8,'relacs_desp_calculada'=>[]]]])->handle('ciw.despesas','read',['entityCode'=>'despesas'],['filters'=>[['field'=>'despesa_id','operator'=>'eq','value'=>7]]]);
        self::assertSame(1,$result['total']);self::assertSame([7],array_column($result['data'],'despesa_id'));
    }
    public static function invalidCollections(): array {return [[null],['1,2'],[[1,1]],[[0]],[[2147483648]],[['1']],[[true]],[['x'=>1]],[range(1,101)]];}
    #[DataProvider('invalidCollections')]
    public function testRejectsInvalidCollectionsBeforeExternalWrite(mixed $value): void {
        $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($this->entity());
        $client=$this->createMock(RuntimeExternalJsonClient::class);$client->expects(self::never())->method('request');
        $this->expectException(RuntimeHttpException::class);
        (new RuntimeApiEntityActionService($repo,$this->createStub(RuntimeTransactionService::class),$client))->handle('ciw.despesas','create',['entityCode'=>'despesas'],['relacs_desp_calculada'=>$value]);
    }
    public function testUpdateOmitsReadonlyPresentationFields(): void {
        $request=null;$this->service(['data'=>[]],$request)->handle('ciw.despesas','update',['entityCode'=>'despesas'],['despesa_id'=>7,'eh_imposto'=>true,'relacs_desp_calculada'=>[]]);
        self::assertArrayNotHasKey('eh_imposto',$request['data']);self::assertSame(7,$request['data']['despesa_id']);
    }
    public function testOptionsUseOnlyConfiguredFieldOperationAndMapSafeValues(): void {
        $request=null; $result=$this->service(['data'=>[['despesa_id'=>4,'descricao'=>'Água','log_imposto'=>1]]],$request)->handle('ciw.despesas','options.relacs_desp_calculada',['entityCode'=>'despesas','operation'=>'options','fieldCode'=>'relacs_desp_calculada'],[]);
        self::assertSame([['value'=>4,'text'=>'Água','fields'=>['eh_imposto'=>true]]],$result['data']);self::assertSame('opcoes_despesas',$request['data']['operation']);
    }
    public function testNullableOptionDescriptionFallsBackToIntegerId(): void {
        $result=$this->service(['data'=>[['despesa_id'=>4,'descricao'=>null,'log_imposto'=>0]]])->handle('ciw.despesas','options.relacs_desp_calculada',['entityCode'=>'despesas','operation'=>'options','fieldCode'=>'relacs_desp_calculada'],[]);
        self::assertSame([['value'=>4,'text'=>'4','fields'=>['eh_imposto'=>false]]],$result['data']);
    }
    public static function invalidOptionDescriptions(): array { return [[[1]], [['unexpected'=>'text']]]; }
    #[DataProvider('invalidOptionDescriptions')]
    public function testOptionDescriptionRejectsComplexValues(mixed $text): void {
        $this->expectException(RuntimeHttpException::class);
        $this->service(['data'=>[['despesa_id'=>4,'descricao'=>$text,'log_imposto'=>0]]])->handle('ciw.despesas','options.relacs_desp_calculada',['entityCode'=>'despesas','operation'=>'options','fieldCode'=>'relacs_desp_calculada'],[]);
    }
    public function testCopiedUnknownTaxFlagIsStillRejected(): void {
        $this->expectException(RuntimeHttpException::class);
        $this->service(['data'=>[['despesa_id'=>4,'descricao'=>null,'log_imposto'=>null]]])->handle('ciw.despesas','options.relacs_desp_calculada',['entityCode'=>'despesas','operation'=>'options','fieldCode'=>'relacs_desp_calculada'],[]);
    }
    public function testOptionsRejectArbitraryBrowserOperationOrField(): void {
        $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($this->entity());
        $client=$this->createMock(RuntimeExternalJsonClient::class);$client->expects(self::never())->method('request');
        $this->expectException(RuntimeHttpException::class);
        (new RuntimeApiEntityActionService($repo,$this->createStub(RuntimeTransactionService::class),$client))->handle('ciw.despesas','options.relacs_desp_calculada',['entityCode'=>'despesas','operation'=>'options','fieldCode'=>'relacs_desp_calculada'],['operationCode'=>'delete','url'=>'https://evil.invalid']);
    }
}
