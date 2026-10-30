<?php
namespace App\Tests\Runtime;
use App\Ciw\BancoCentralSgsClient;
use App\Repository\BuilderEntityRepository;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,PermissionResolver,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/RuntimeCiwCotacaoImportTest.php';

final class RuntimeCiwCotacaoProcessTest extends TestCase
{
    private function execute(array $parameters=['serie'=>1],bool $allowed=true,bool $bcFails=false): array
    {
        $valid=$parameters===['serie'=>1]&&$allowed;
        $bc=$this->createMock(BancoCentralSgsClient::class);
        if($valid&&$bcFails)$bc->expects(self::once())->method('fetchLatestDollar')->willThrowException(new RuntimeHttpException('BCB_UNAVAILABLE','Indisponivel',502));
        elseif($valid)$bc->expects(self::once())->method('fetchLatestDollar')->willReturn(['serie'=>1,'data'=>'2026-10-20','valor'=>5.12345,'nome_serie'=>'Dólar americano (venda) - diário']);
        else $bc->expects(self::never())->method('fetchLatestDollar');
        $client=$this->createMock(RuntimeExternalJsonClient::class);
        $body=RuntimeCiwCotacaoImportTest::body();$body['success']=true;$body['data']=['type'=>'message','message'=>'Confirmado'];
        if($valid&&!$bcFails)$client->expects(self::once())->method('request')->willReturnCallback(function($url,$method,$headers,$request)use($body){
            self::assertSame('POST',$method);self::assertSame('import-bc',$request['operation']);self::assertSame(5.12345,$request['valor']);self::assertSame('2026-10-20',$request['data']);
            self::assertArrayNotHasKey('user',$request);return ['status'=>200,'body'=>$body];
        });else $client->expects(self::never())->method('request');
        $tx=$valid&&!$bcFails?$this->createMock(RuntimeTransactionService::class):$this->createStub(RuntimeTransactionService::class);
        if($valid&&!$bcFails){
            $tx->expects(self::once())->method('bindRecordId')->with(10);
            $tx->expects(self::once())->method('log')->with('hist_cotacoes_bc_progress.api.process',self::anything(),[],self::callback(fn($a)=>$a['log_manual']===false&&$a['valor']===5.12345),self::callback(fn($m)=>$m['audit']['available']===true));
        }
        $permission=$this->createStub(PermissionResolver::class);$permission->method('hasPermission')->willReturn($allowed);
        $s=new RuntimeApiEntityActionService($this->createStub(BuilderEntityRepository::class),$tx,$client,$permission,bancoCentral:$bc);
        $d=RuntimeCiwCotacaoImportTest::definition();$e=RuntimeCiwCotacaoImportTest::endpoint();
        $d['apiSource']['operations']=[['code'=>'hist_cotacoes_bc_import','allowProcess'=>true,'type'=>'custom','method'=>'POST','path'=>$e['url'],'bodyTemplate'=>$e['bodyTemplate'],'itemPath'=>'data']];
        $d['fields']['serie']+=['code'=>'serie','label'=>'Série','required'=>true];
        return (new \ReflectionMethod($s,'process'))->invoke($s,$d,['operation'=>'process','allowProcess'=>true,'processOptions'=>['operationCode'=>'hist_cotacoes_bc_import','parameterFields'=>['serie']],'contextFields'=>[]],['parameters'=>$parameters,'context'=>[]]);
    }
    public function testOnlyPhpClientSuppliesQuoteAndSnapshotIsLogged(): void {self::assertSame('succeeded',$this->execute()['status']);}
    public function testBrowserCannotProvideQuoteOrSeries(): void {foreach([['valor'=>10],['serie'=>9999],['url'=>'https://evil']] as $p){try{$this->execute($p);self::fail('Injecao aceita');}catch(RuntimeHttpException $e){self::assertSame('ENTITY_API_PROCESS_INVALID',$e->getErrorCode());}}}
    public function testPermissionDeniedDoesNotCallBcOrProgress(): void {$this->expectException(RuntimeHttpException::class);$this->execute(['serie'=>1],false);}
    public function testBcUnavailableDoesNotCallProgress(): void {$this->expectException(RuntimeHttpException::class);$this->execute(['serie'=>1],true,true);}
}
