<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeCiwErpQuery,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseQuantityPeriodsQueryTest extends TestCase
{
 private function definition(): array { return ['entityCode'=>'proc_compra_quantidades_periodo_med_progress','primaryKey'=>'record_key','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs'],'fields'=>['record_key'=>['dataType'=>'string'],'dt_registro'=>['dataType'=>'date'],'dt_recebimento'=>['dataType'=>'date'],'dt_prev_chegada'=>['dataType'=>'date'],'pedido_fornec'=>['dataType'=>'string']]]; }
 private function endpoint(): array { return ['url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','method'=>'POST','bodyTemplate'=>['operation'=>'read','resource'=>'proc_compra_quantidades_periodo_med'],'requestContextFields'=>[]]; }
 public function testNativeDateRangesReachTheServerWithoutLocalPaging(): void
 {
  self::assertTrue(RuntimeCiwErpQuery::supports($this->definition()));
  $q=RuntimeCiwErpQuery::prepare($this->definition(),$this->endpoint(),['take'=>20,'skip'=>200,'sort'=>[['field'=>'pedido_fornec','dir'=>'asc']],'filter'=>['field'=>'dt_prev_chegada','operator'=>'between','value'=>['start'=>'2026-11-01','end'=>'2026-11-30']]]);
  self::assertSame(200,$q['skip']);self::assertSame(20,$q['take']);self::assertSame('and',$q['filter']['logic']);self::assertSame('gte',$q['filter']['filters'][0]['operator']);self::assertSame('2026-11-30',$q['filter']['filters'][1]['value']);self::assertSame([['field'=>'pedido_fornec','dir'=>'asc']],$q['sort']);
 }
 public function testAllThreePeriodFieldsUseTheExistingCivilDateParser(): void
 {
  foreach(['dt_registro','dt_recebimento','dt_prev_chegada'] as $field){$q=RuntimeCiwErpQuery::prepare($this->definition(),$this->endpoint(),['filter'=>['field'=>$field,'operator'=>'between','value'=>['start'=>'2026-10-01T03:00:00Z','end'=>'2026-10-31T23:59:59-03:00']]]);self::assertSame('2026-10-01',$q['filter']['filters'][0]['value']);self::assertSame('2026-10-31',$q['filter']['filters'][1]['value']);}
 }
 public function testContextAndUnsafeBindingsRemainRejected(): void
 {
  foreach(['context','declaredContext','resource','route','writer','oversize','sql'] as $kind){$e=$this->endpoint();$p=[];switch($kind){case 'context':$p=['context'=>['proc_compra_id'=>1679]];break;case 'declaredContext':$e['requestContextFields']=['proc_compra_id'];break;case 'resource':$e['bodyTemplate']['resource']='other';break;case 'route':$e['url']='https://other.example/api';break;case 'writer':$e['bodyTemplate']['operation']='delete';break;case 'oversize':$p=['take'=>201];break;case 'sql':$p=['sql'=>'SELECT other'];}
   try{RuntimeCiwErpQuery::prepare($this->definition(),$e,$p);self::fail('Invalid period binding accepted');}catch(RuntimeHttpException $x){self::assertSame(422,$x->getStatusCode());}
  }
 }
}
