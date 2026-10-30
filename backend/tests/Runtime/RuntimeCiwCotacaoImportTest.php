<?php
namespace App\Tests\Runtime;
use App\Runtime\RuntimeCiwCotacaoAudit;
use PHPUnit\Framework\TestCase;
final class RuntimeCiwCotacaoImportTest extends TestCase
{
    public static function definition(): array
    {
        $fields=[];
        foreach(['hist_cotacao_bc_id'=>'integer','data'=>'date','serie'=>'integer','valor'=>'decimal','dt_hr_busca'=>'datetime','nome_serie'=>'string','log_ok'=>'boolean','log_manual'=>'boolean','row_stamp'=>'string'] as $k=>$v) $fields[$k]=['dataType'=>$v,'jsonPath'=>$k];
        return ['entityCode'=>'hist_cotacoes_bc_progress','primaryKey'=>'hist_cotacao_bc_id','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs'],'fields'=>$fields];
    }
    public static function endpoint(): array {return ['method'=>'POST','url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','bodyTemplate'=>['operation'=>'import-bc','resource'=>'hist_cotacoes_bc']];}
    public static function body(): array
    {
        return ['audit'=>['version'=>1,'source'=>'progress-transaction','resource'=>'hist_cotacoes_bc','operation'=>'import-bc','recordId'=>10,'before'=>[],
            'after'=>['hist_cotacao_bc_id'=>10,'data'=>'2026-10-20','serie'=>1,'valor'=>5.12345,'dt_hr_busca'=>'2026-10-21T14:00:00.000','nome_serie'=>'Dólar americano (venda) - diário','log_ok'=>true,'log_manual'=>false,'row_stamp'=>str_repeat('A',64)],'changes'=>[]]];
    }
    public function testImportIsAutomaticWithClosedSourceAndConfirmedSnapshot(): void
    {
        $a=RuntimeCiwCotacaoAudit::inspect(self::definition(),self::endpoint(),self::body(),'import-bc',null);
        self::assertTrue($a['metadata']['available']); self::assertSame([], $a['before']);self::assertFalse($a['after']['log_manual']);self::assertSame(10,$a['metadata']['recordId']);
    }
    public function testImportCannotAcceptManualFlagOtherSeriesBadSourceOrCostEffects(): void
    {
        foreach(['manual','series','zero','ok','before','effect','url','source'] as $bad) {
            $d=self::definition();$e=self::endpoint();$b=self::body();
            if($bad==='manual')$b['audit']['after']['log_manual']=true;
            if($bad==='series')$b['audit']['after']['serie']=9999;
            if($bad==='zero')$b['audit']['after']['valor']=0;
            if($bad==='ok')$b['audit']['after']['log_ok']=false;
            if($bad==='before')$b['audit']['before']=$b['audit']['after'];
            if($bad==='effect')$b['audit']['changes']=[[]];
            if($bad==='url')$e['url']='https://evil.example/api';
            if($bad==='source')$d['apiBinding']['sourceCode']='other';
            self::assertFalse(RuntimeCiwCotacaoAudit::inspect($d,$e,$b,'import-bc',null)['metadata']['available'],$bad);
        }
    }
}
