<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeCiwErpQuery,RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/RuntimePurchaseOrderAuditTest.php';
final class RuntimePurchaseOrderQueryTest extends TestCase
{
    private function definition(): array { $d=RuntimePurchaseOrderAuditTest::definition();$d['fields']=[];foreach(RuntimePurchaseOrderAuditTest::data()as $name=>$v){$type=match($name){'proc_compra_id','empresa_id','fornecedor_id','qt_container','cod_situacao','qt_dias_translado'=>'integer','cotacao_prevista'=>'decimal','dt_registro','dt_limite'=>'date','log_facilid_contato_fornec','log_considerar_previsao'=>'boolean',default=>'string'};$d['fields'][$name]=['code'=>$name,'jsonPath'=>$name,'dataType'=>$type,'collection'=>null];}$d['apiSource']+=['authHeaders'=>[],'listEndpoint'=>RuntimePurchaseOrderAuditTest::endpoint('read')];return $d; }
    public function testHeaderNormalizesCivilDateBooleanAndIntegerFiltersOnTheSharedQueryPath(): void {
        $d=$this->definition();self::assertTrue(RuntimeCiwErpQuery::supports($d));$q=RuntimeCiwErpQuery::prepare($d,$d['apiSource']['listEndpoint'],['skip'=>40,'take'=>20,'filters'=>[['field'=>'dt_registro','operator'=>'eq','value'=>'2026-10-23T02:59:59.999Z'],['field'=>'log_considerar_previsao','operator'=>'eq','value'=>true],['field'=>'proc_compra_id','operator'=>'in','value'=>['17','18']]]]);self::assertSame('2026-10-22',$q['filter']['filters'][0]['value']);self::assertTrue($q['filter']['filters'][1]['value']);self::assertSame(17,$q['filter']['filters'][2]['filters'][0]['value']);
    }
    public function testHeaderPageKeepsItsFifteenFieldsAndServerTotalWithoutNaturalKeyOrSecondPaging(): void {
        $d=$this->definition();$client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturn(['status'=>200,'body'=>['success'=>true,'data'=>[RuntimePurchaseOrderAuditTest::data()],'total'=>41]]);$r=new \ReflectionClass(RuntimeApiEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('externalJsonClient')->setValue($s,$client);$r->getProperty('transactions')->setValue($s,$this->createStub(RuntimeTransactionService::class));$out=$r->getMethod('read')->invoke($s,$d,['skip'=>40,'take'=>20]);self::assertSame(41,$out['total']);self::assertSame(RuntimePurchaseOrderAuditTest::data(),$out['data'][0]);self::assertCount(15,$out['data'][0]);
    }
    public function testBooleanFractionLocalizedMoneyAndStampCannotBypassTypedQueryContract(): void {
        foreach([['field'=>'log_considerar_previsao','operator'=>'eq','value'=>'true'],['field'=>'log_considerar_previsao','operator'=>'gt','value'=>true],['field'=>'cod_situacao','operator'=>'eq','value'=>1.5],['field'=>'cotacao_prevista','operator'=>'eq','value'=>'1,25'],['field'=>'row_stamp','operator'=>'eq','value'=>str_repeat('A',64)]]as $filter){$d=$this->definition();try{RuntimeCiwErpQuery::prepare($d,$d['apiSource']['listEndpoint'],['filter'=>$filter]);self::fail('Invalid header query accepted');}catch(RuntimeHttpException $e){self::assertSame('CIW_ERP_QUERY_INVALID',$e->getErrorCode());}}
    }
}
