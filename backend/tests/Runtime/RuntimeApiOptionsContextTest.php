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
final class RuntimeApiOptionsContextTest extends TestCase {
 private function entity(bool $conflict=false): BuilderEntity {
  return (new BuilderEntity())->setCode('container_itens_progress')->setEntityType('api')->setMetadata(['apiSource'=>[
   'authHeaders'=>[], 'listEndpoint'=>['url'=>'/containers-painel','method'=>'POST'], 'listResponse'=>['itemsPath'=>'data'],
   'operations'=>[['code'=>'itens_disponiveis','type'=>'custom','path'=>'/containers-itens','method'=>'POST','itemsPath'=>'data','requestContextFields'=>['container_id'],
    'bodyTemplate'=>['operation'=>'read','resource'=>'itens-disponiveis']+($conflict?['container_id'=>999]:[])]]
  ]])->addField((new BuilderField())->setCode('item_container_id')->setDataType('integer')->setPrimaryKey(true))
   ->addField((new BuilderField())->setCode('container_id')->setDataType('integer'))
   ->addField((new BuilderField())->setCode('saldo_disponivel_texto')->setDataType('string')->setOptions(['readonly'=>true]))
   ->addField((new BuilderField())->setCode('item_proc_compra_id')->setDataType('integer')->setOptions(['api'=>['optionsSource'=>[
    'operationCode'=>'itens_disponiveis','valueField'=>'item_proc_compra_id','textField'=>'label','copyFields'=>['saldo_disponivel_texto'=>'saldo_disponivel_texto']
   ]]]));
 }
 private function runOptions(array $payload,bool $httpExpected,bool $conflict=false,?array &$sent=null): array {
  $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($this->entity($conflict));
  $client=$this->createMock(RuntimeExternalJsonClient::class);
  $client->expects($httpExpected?self::once():self::never())->method('request')->willReturnCallback(function($source,$endpoint,$headers,$data)use(&$sent){
   $sent=$data; return ['status'=>200,'body'=>['data'=>[['item_proc_compra_id'=>17,'label'=>'Pedido 10 / ERP1 / saldo 1,25','saldo_disponivel_texto'=>'1,25']]]];
  });
  return (new RuntimeApiEntityActionService($repo,$this->createStub(RuntimeTransactionService::class),$client))->handle('ciw.container-itens','options.item_proc_compra_id',
   ['entityCode'=>'container_itens_progress','operation'=>'options','fieldCode'=>'item_proc_compra_id'],$payload);
 }
 public function testForwardsOnlyRegisteredParentContextAndPreservesOptionMapping(): void {
  $sent=null;$result=$this->runOptions(['context'=>['container_id'=>'123']],true,false,$sent);
  self::assertSame(['operation'=>'read','resource'=>'itens-disponiveis','container_id'=>123],$sent);
  self::assertSame([['value'=>17,'text'=>'Pedido 10 / ERP1 / saldo 1,25','fields'=>['saldo_disponivel_texto'=>'1,25']]],$result['data']);
 }
 public static function invalidContexts(): array {return [[[]],[['context'=>[]]],[['context'=>['container_id'=>0]]],[['context'=>['container_id'=>'1e2']]],[['context'=>['container_id'=>1.5]]],[['context'=>['container_id'=>true]]],[['context'=>['container_id'=>123,'empresa_id'=>9]]],[['context'=>[123]]]];}
 #[DataProvider('invalidContexts')]
 public function testRejectsMissingInvalidOrUndeclaredContextBeforeHttp(array $payload): void {
  $this->expectException(RuntimeHttpException::class);$this->expectExceptionCode(0);$this->runOptions($payload,false);
 }
 public function testRejectsConflictWithRegisteredStaticTemplateBeforeHttp(): void {
  $this->expectException(RuntimeHttpException::class);$this->runOptions(['context'=>['container_id'=>123]],false,true);
 }
 public function testDoesNotAcceptBrowserFiltersOrOperationSelection(): void {
  $this->expectException(RuntimeHttpException::class);$this->runOptions(['context'=>['container_id'=>123],'operationCode'=>'delete'],false);
 }
}
