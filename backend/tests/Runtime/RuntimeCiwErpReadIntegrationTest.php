<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService};
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/RuntimeCiwErpQueryTest.php';
final class RuntimeCiwErpReadIntegrationTest extends TestCase
{
    public function testPageFromErpIsNotPagedAgainByPhp(): void
    {
        $d=RuntimeCiwErpQueryTest::definition();$e=RuntimeCiwErpQueryTest::endpoint();
        $d['apiSource']+=['listEndpoint'=>$e,'listResponse'=>['itemsPath'=>'data','totalPath'=>'total'],'authHeaders'=>[]];
        foreach($d['fields']as $name=>&$f)$f+=['code'=>$name,'jsonPath'=>$name,'collection'=>null];unset($f);
        $rows=[['record_key'=>'a','nr_nota_fis'=>'0001','cod_emitente'=>1,'vl_tot_nota'=>1.12345,'dt_emis_nota'=>'2026-10-01'],['record_key'=>'b','nr_nota_fis'=>'0002','cod_emitente'=>2,'vl_tot_nota'=>2.12345,'dt_emis_nota'=>'2026-10-02']];
        $client=$this->createMock(RuntimeExternalJsonClient::class);$client->expects(self::once())->method('request')->willReturnCallback(function($url,$method,$headers,$body)use($rows){self::assertSame(40,$body['skip']);self::assertSame(20,$body['take']);return ['status'=>200,'body'=>['success'=>true,'data'=>$rows,'total'=>42]];});
        $tx=$this->createMock(RuntimeTransactionService::class);$tx->expects(self::once())->method('log')->with('notas_fiscais_med_progress.api.read',self::anything(),self::anything(),self::anything(),self::callback(fn($m)=>($m['serverPaging']??null)===true&&$m['total']===42));
        $r=new \ReflectionClass(RuntimeApiEntityActionService::class);$service=$r->newInstanceWithoutConstructor();
        $r->getProperty('externalJsonClient')->setValue($service,$client);$r->getProperty('transactions')->setValue($service,$tx);
        $out=$r->getMethod('read')->invoke($service,$d,['skip'=>40,'take'=>20]);
        self::assertSame(42,$out['total']);self::assertEquals($rows,$out['data']);self::assertSame(1.12345,$out['data'][0]['vl_tot_nota']);
    }
}
