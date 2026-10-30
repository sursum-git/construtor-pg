<?php
namespace App\Tests\Runtime;
use App\Runtime\RuntimeEstoqueTermoAudit;
use PHPUnit\Framework\TestCase;
final class RuntimeEstoqueTermoAuditTest extends TestCase
{
 private function definition():array{return ['entityCode'=>'estoque_termos_busca_progress','primaryKey'=>'termo_digitado','apiBinding'=>['sourceCode'=>'motor_progress_estoque_termos'],'apiSource'=>['responseContract'=>'sursum-programs'],'fields'=>array_combine(['termo_digitado','termo_pesquisa','ativo','row_stamp'],array_map(static fn($name,$type)=>['dataType'=>$type,'jsonPath'=>$name],['termo_digitado','termo_pesquisa','ativo','row_stamp'],['string','string','boolean','string']))];}
 private function endpoint():array{return ['url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','method'=>'POST','bodyTemplate'=>['resource'=>'estoque_termos','operation'=>'update']];}
 private function body():array{$row=['termo_digitado'=>'SC-TESTE','termo_pesquisa'=>'TESTE','ativo'=>true,'row_stamp'=>str_repeat('A',64)];return ['audit'=>['version'=>1,'source'=>'progress-transaction','resource'=>'estoque_termos','operation'=>'update','recordId'=>'SC-TESTE','before'=>$row,'after'=>array_replace($row,['ativo'=>false])]];}
 public function testPreservesBothImagesIncludingInactiveState():void
 {$a=RuntimeEstoqueTermoAudit::inspect($this->definition(),$this->endpoint(),$this->body(),'update','SC-TESTE');self::assertTrue($a['metadata']['available']);self::assertTrue($a['before']['ativo']);self::assertFalse($a['after']['ativo']);self::assertSame('TESTE',$a['after']['termo_pesquisa']);}
 public function testRejectsForgedBindingsAndSnapshots():void
 {foreach(['resource','path','source','key','extra','boolean','id','stamp'] as $bad){$d=$this->definition();$e=$this->endpoint();$b=$this->body();if($bad==='resource')$e['bodyTemplate']['resource']='im_param';if($bad==='path')$e['url']='http://127.0.0.1:19910/med/web/SursumPrograms/echo';if($bad==='source')$d['apiBinding']['sourceCode']='other';if($bad==='key')$d['primaryKey']='id';if($bad==='extra')$b['audit']['after']['extra']='x';if($bad==='boolean')$b['audit']['after']['ativo']='false';if($bad==='id')$b['audit']['recordId']='OUTRO';if($bad==='stamp')$b['audit']['after']['row_stamp']='x';self::assertFalse(RuntimeEstoqueTermoAudit::inspect($d,$e,$b,'update','SC-TESTE')['metadata']['available'],$bad);}}
}
