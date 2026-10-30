<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeCiwErpQuery,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
final class RuntimeCiwErpQueryTest extends TestCase
{
    public static function definition(): array {return ['entityCode'=>'notas_fiscais_med_progress','primaryKey'=>'record_key','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs'],'fields'=>['nr_nota_fis'=>['dataType'=>'string'],'cod_emitente'=>['dataType'=>'integer'],'vl_tot_nota'=>['dataType'=>'decimal'],'dt_emis_nota'=>['dataType'=>'date'],'record_key'=>['dataType'=>'string']]];}
    public static function endpoint(): array {return ['url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','method'=>'POST','bodyTemplate'=>['operation'=>'read','resource'=>'notas_fiscais_med']];}
    public function testPagingAndNestedFiltersReachServerOnceWithoutLocalTruncation(): void
    {
        $filter=['logic'=>'or','filters'=>[['field'=>'cod_emitente','operator'=>'eq','value'=>2],['field'=>'nr_nota_fis','operator'=>'contains','value'=>"a' OR true"]]];
        $p=RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['take'=>20,'skip'=>40,'filter'=>$filter,'sort'=>[['field'=>'nr_nota_fis','dir'=>'desc']]]);
        self::assertSame(['skip'=>40,'take'=>20,'filter'=>$filter,'sort'=>[['field'=>'nr_nota_fis','dir'=>'desc']]],$p);
    }
    public function testUnsupportedBindingCannotOptIn(): void
    {
        $e=self::endpoint();$e['url']='https://evil.example/api';$this->expectException(RuntimeHttpException::class);RuntimeCiwErpQuery::prepare(self::definition(),$e,[]);
    }
    public function testInvalidOrUnboundedQueriesAreRejectedBeforeTransport(): void
    {
        foreach([['take'=>201],['skip'=>-1],['take'=>1.2],['db'=>'ems2ima'],['context'=>['empresa_id'=>1]],['filter'=>['field'=>'auth_user','operator'=>'eq','value'=>'admin']],['filter'=>['field'=>'cod_emitente','operator'=>'eq','value'=>'2 OR TRUE']],['sort'=>[['field'=>'record_key','dir'=>'asc']]],['group'=>[['field'=>'cod_emitente']]],['filters'=>[['field'=>'dt_emis_nota','operator'=>'eq','value'=>'2026-02-30']]]] as $p){
            try{RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),$p);self::fail('Consulta invalida aceita');}catch(RuntimeHttpException $e){self::assertSame('CIW_ERP_QUERY_INVALID',$e->getErrorCode());}
        }
    }
    public function testFlatFilterCollectionIsCombinedWithMainFilterUsingAnd(): void
    {
        $a=['field'=>'cod_emitente','operator'=>'eq','value'=>1];$b=['field'=>'nr_nota_fis','operator'=>'startswith','value'=>'00'];
        $p=RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filter'=>$a,'filters'=>[$b]]);
        self::assertSame(['logic'=>'and','filters'=>[$a,$b]],$p['filter']);
    }
    public function testNativeToolbarDtoAndCamelCaseOperatorsAreNormalized(): void
    {
        $f=['id'=>'nf','label'=>'Nota','field'=>'nr_nota_fis','type'=>'text','dataType'=>'string','operator'=>'startsWith','value'=>'00','displayValue'=>'00'];
        $p=RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filters'=>[$f]]);
        self::assertSame(['field'=>'nr_nota_fis','operator'=>'startswith','value'=>'00'],$p['filter']);
        $f['operator']='isNull';$f['value']=true;
        self::assertSame(['field'=>'nr_nota_fis','operator'=>'isnull'],RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filters'=>[$f]])['filter']);
        $f['operator']='notContains';$f['value']='abc';
        self::assertSame('doesnotcontain',RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filters'=>[$f]])['filter']['operator']);
    }
    public function testNativeDateTimestampRangeAndRelativeRangeKeepBrazilianCivilDates(): void
    {
        $f=['field'=>'dt_emis_nota','operator'=>'between','value'=>['start'=>'2026-10-21T03:00:00.000Z','end'=>'2026-10-23T02:59:59.999Z']];
        $p=RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filters'=>[$f]]);
        self::assertSame(['logic'=>'and','filters'=>[['field'=>'dt_emis_nota','operator'=>'gte','value'=>'2026-10-21'],['field'=>'dt_emis_nota','operator'=>'lte','value'=>'2026-10-22']]],$p['filter']);
        $f['operator']='relative';$f['value']+=['mode'=>'days','amount'=>2,'unit'=>'day','direction'=>'previous','label'=>'2 dias'];
        self::assertSame($p,RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filters'=>[$f]]));
        $f=['field'=>'dt_emis_nota','operator'=>'eq','value'=>'2026-10-22T03:00:00.000Z'];
        self::assertSame('2026-10-22',RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filter'=>$f])['filter']['value']);
    }
    public function testNativeListsAndOpenEndedRangesBecomeTypedLeaves(): void
    {
        $p=RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filters'=>[['field'=>'cod_emitente','operator'=>'notIn','value'=>['1','2']]]]);
        self::assertSame(['logic'=>'and','filters'=>[['field'=>'cod_emitente','operator'=>'neq','value'=>1],['field'=>'cod_emitente','operator'=>'neq','value'=>2]]],$p['filter']);
        $p=RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filters'=>[['field'=>'cod_emitente','operator'=>'between','value'=>['start'=>null,'end'=>10]]]]);
        self::assertSame(['field'=>'cod_emitente','operator'=>'lte','value'=>10],$p['filter']);
        // Comparacao CHARACTER pertence ao ABL; PHP compara strings numericas como numeros.
        foreach([['10','2'],['a','Z']] as [$start,$end]){
            $p=RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filters'=>[['field'=>'nr_nota_fis','operator'=>'between','value'=>['start'=>$start,'end'=>$end]]]]);
            self::assertSame($start,$p['filter']['filters'][0]['value']);
            self::assertSame($end,$p['filter']['filters'][1]['value']);
        }
    }
    public function testNativeMetadataCannotSelectOtherFieldsOrBypassLimits(): void
    {
        foreach([
            ['field'=>'nr_nota_fis','fields'=>['auth_user'],'operator'=>'eq','value'=>'x'],
            ['field'=>'cod_emitente','operator'=>'in','value'=>['2 OR TRUE']],
            ['field'=>'nr_nota_fis','operator'=>'in','value'=>array_fill(0,51,'x')],
            ['field'=>'dt_emis_nota','operator'=>'eq','value'=>'2026-02-30T03:00:00.000Z'],
            ['field'=>'dt_emis_nota','operator'=>'between','value'=>['start'=>'2026-10-22','end'=>'2026-10-21']],
            ['field'=>'cod_emitente','operator'=>'between','value'=>['start'=>null,'end'=>null]],
            ['field'=>'nr_nota_fis','operator'=>'eq','value'=>'x','db'=>'ems2ima'],
        ] as $filter){
            try{RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filters'=>[$filter]]);self::fail('Filtro nativo invalido aceito');}catch(RuntimeHttpException $e){self::assertSame('CIW_ERP_QUERY_INVALID',$e->getErrorCode());}
        }
    }
    public function testCombinedNativeAndColumnFiltersCannotExceedAblDepth(): void
    {
        $leaf=['field'=>'nr_nota_fis','operator'=>'eq','value'=>'x'];$filter=$leaf;
        for($n=0;$n<6;$n++)$filter=['logic'=>'and','filters'=>[$filter]];
        $this->expectException(RuntimeHttpException::class);
        RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint(),['filter'=>$filter,'filters'=>[$leaf]]);
    }
}
