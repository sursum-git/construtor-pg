<?php
namespace App\Tests\Builder;
use App\Builder\AnalyticsApiDashboardConfig;
use App\Entity\{BuilderEntity,BuilderField};
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;

final class AnalyticsApiDashboardConfigTest extends TestCase
{
    private function config():array {return ['datasets'=>[['id'=>'resumo','source'=>['type'=>'api','entityCode'=>'iol_resumo'],'fields'=>['valor']]],'dashboard'=>['id'=>'inicio','tiles'=>[['id'=>'meta','type'=>'progress','valueField'=>'valor'],['id'=>'nav','type'=>'links','items'=>[['navigationId'=>'precos']]]]],'navigationHosts'=>['imaonline.imatextil.com.br'],'navigation'=>['precos'=>['label'=>'Preços','url'=>'https://imaonline.imatextil.com.br/iol/precos/']]];}
    private function normalize(array $config):array {
        $entity=(new BuilderEntity())->setCode('iol_resumo')->setEntityType('api')->setMetadata(['apiSource'=>['listEndpoint'=>['url'=>'http://trusted/read']]]);
        $entity->addField((new BuilderField())->setCode('valor')->setLabel('Valor')->setDataType('decimal'));
        $entity->addField((new BuilderField())->setCode('nome')->setLabel('Nome')->setDataType('string'));
        return AnalyticsApiDashboardConfig::normalize($config,fn(string $code)=>$code==='iol_resumo'?$entity:null);
    }
    public function testNormalizesRegisteredDatasetsAndClosedNavigation():void {
        $r=$this->normalize($this->config()); self::assertSame('live',$r['datasets'][0]['executionMode']);self::assertSame('valor',$r['datasets'][0]['fields'][0]['field']);self::assertSame('resumo',$r['dashboard']['datasetId']);
    }
    public function testUnknownFieldRejected():void {$c=$this->config();$c['dashboard']['tiles'][0]['valueField']='private';$this->expectException(RuntimeHttpException::class);$this->normalize($c);}
    public function testJavascriptLinkRejected():void {$c=$this->config();$c['navigation']['precos']['url']='javascript:alert(1)';$this->expectException(RuntimeHttpException::class);$this->normalize($c);}
    public function testExternalHostRejected():void {$c=$this->config();$c['navigation']['precos']['url']='https://evil.example/';$this->expectException(RuntimeHttpException::class);$this->normalize($c);}
    public function testRawTemplateRejected():void {$c=$this->config();$c['dashboard']['tiles'][0]['template']='<p>HTML</p>';$this->expectException(RuntimeHttpException::class);$this->normalize($c);}
    public function testRecursiveGroupLimit():void {$c=$this->config();for($i=0;$i<5;$i++)$c['dashboard']['tiles']=[['id'=>'group'.$i,'type'=>'group','children'=>$c['dashboard']['tiles']]];$this->expectException(RuntimeHttpException::class);$this->normalize($c);}
    public function testListTitleCaseIsDeclarativeAndPreserved():void {
        $c=$this->config();$c['datasets'][0]['fields'][]='nome';$c['dashboard']['tiles'][]=['id'=>'nomes','type'=>'list','valueField'=>'valor','labelField'=>'nome','labelCase'=>'title'];
        $r=$this->normalize($c);self::assertSame('title',$r['dashboard']['tiles'][2]['labelCase']);
    }
    public function testUnknownLabelCaseRejected():void {
        $c=$this->config();$c['datasets'][0]['fields'][]='nome';$c['dashboard']['tiles'][]=['id'=>'nomes','type'=>'list','valueField'=>'valor','labelField'=>'nome','labelCase'=>'javascript'];
        $this->expectException(RuntimeHttpException::class);$this->normalize($c);
    }
}
