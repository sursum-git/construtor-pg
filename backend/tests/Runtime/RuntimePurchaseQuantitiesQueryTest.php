<?php
namespace App\Tests\Runtime;
use App\Runtime\{ApiRequestContext,RuntimeCiwErpQuery,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseQuantitiesQueryTest extends TestCase
{
    private static function definition(bool $contextual): array
    {
        return ['entityCode'=>$contextual?'proc_compra_quantidades_progress':'proc_compra_quantidades_med_progress','primaryKey'=>'record_key','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs'],'fields'=>['proc_compra_id'=>['dataType'=>'integer'],'item_proc_compra_id'=>['dataType'=>'integer'],'item_container_id'=>['dataType'=>'integer'],'record_key'=>['dataType'=>'string']]];
    }
    private static function endpoint(bool $contextual): array
    {
        return ['url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','method'=>'POST','bodyTemplate'=>['operation'=>'read','resource'=>'proc_compra_quantidades'],'requestContextFields'=>$contextual?['proc_compra_id']:[]];
    }
    public function testBothQuantityViewsUseRegisteredServerPagination(): void
    {
        self::assertTrue(RuntimeCiwErpQuery::supports(self::definition(false)));
        self::assertTrue(RuntimeCiwErpQuery::supports(self::definition(true)));
        $sort=[['field'=>'proc_compra_id','dir'=>'desc']];
        $query=RuntimeCiwErpQuery::prepare(self::definition(false),self::endpoint(false),['take'=>20,'skip'=>200,'sort'=>$sort,'filter'=>['field'=>'proc_compra_id','operator'=>'eq','value'=>1679]]);
        self::assertSame(200,$query['skip']);self::assertSame(20,$query['take']);self::assertSame($sort,$query['sort']);self::assertSame(['field'=>'proc_compra_id','operator'=>'eq','value'=>1679],$query['filter']);
    }
    public function testContextIsForwardedAndResponseMustMatch(): void
    {
        $query=RuntimeCiwErpQuery::prepare(self::definition(true),self::endpoint(true),['take'=>10,'context'=>['proc_compra_id'=>1679]]);
        self::assertSame(['skip'=>0,'take'=>10,'proc_compra_id'=>1679],$query);
        ApiRequestContext::assertRows([['proc_compra_id'=>1679]],['proc_compra_id'=>$query['proc_compra_id']]);
        $this->expectException(RuntimeHttpException::class);ApiRequestContext::assertRows([['proc_compra_id'=>10]],['proc_compra_id'=>$query['proc_compra_id']]);
    }
    public function testContextCannotBeMissingConflictingOrAddedToGlobalView(): void
    {
        foreach([[true,[]],[true,['context'=>['proc_compra_id'=>1679],'filter'=>['field'=>'proc_compra_id','operator'=>'eq','value'=>10]]],[false,['context'=>['proc_compra_id'=>1679]]],[false,['take'=>201]]] as [$contextual,$payload]){
            try{RuntimeCiwErpQuery::prepare(self::definition($contextual),self::endpoint($contextual),$payload);self::fail('Invalid query accepted');}catch(RuntimeHttpException $e){self::assertSame(422,$e->getStatusCode());}
        }
    }
    public function testRegistrationDoesNotPermitOtherRoutesResourcesOrWriters(): void
    {
        foreach(['resource','route','writer'] as $variant){$e=self::endpoint(false);if($variant==='resource')$e['bodyTemplate']['resource']='other';elseif($variant==='route')$e['url']='https://other.example/api';else$e['bodyTemplate']['operation']='update';
            try{RuntimeCiwErpQuery::prepare(self::definition(false),$e,[]);self::fail('Binding accepted');}catch(RuntimeHttpException $x){self::assertSame(422,$x->getStatusCode());}}
    }
}
