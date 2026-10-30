<?php
namespace App\Tests\Builder;
use App\Builder\ProgramBuilderService;
use App\Entity\{BuilderEntity,BuilderField};
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
final class ProgramBuilderBcProcessTest extends TestCase
{
    private function invoke(string $entityCode='hist_cotacoes_bc_progress',string $url='http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros',array $parameters=['serie']): array
    {
        $e=(new BuilderEntity())->setCode($entityCode)->setEntityType('api')->setMetadata(['apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs','operations'=>[['code'=>'hist_cotacoes_bc_import','method'=>'POST','type'=>'custom','allowProcess'=>true,'path'=>$url,'bodyTemplate'=>['operation'=>'import-bc','resource'=>'hist_cotacoes_bc']]]]]);
        $e->addField((new BuilderField())->setCode('hist_cotacao_bc_id')->setDataType('integer')->setPrimaryKey(true));
        $e->addField((new BuilderField())->setCode('serie')->setDataType('integer')->setRequired(true)->setOptions(['defaultValue'=>1]));
        $r=new \ReflectionClass(ProgramBuilderService::class);
        return $r->getMethod('normalizeProcessOptions')->invoke($r->newInstanceWithoutConstructor(),['operationCode'=>'hist_cotacoes_bc_import','parameterFields'=>$parameters,'confirmation'=>['message'=>'Buscar cotação?']],$e,[]);
    }
    public function testBcUsesExistingDeclaredSeriesParameter(): void {self::assertSame(['serie'],$this->invoke()['parameterFields']);}
    public function testOtherBindingsStillRequireParameters(): void
    {
        foreach([['other','http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros'],['hist_cotacoes_bc_progress','https://evil.example/api']] as [$code,$url]){
            try{$this->invoke($code,$url,[]);self::fail('Processo vazio aceito');}catch(RuntimeHttpException $e){self::assertSame('PROGRAM_PROCESS_OPTIONS_INVALID',$e->getErrorCode());}
        }
    }
}
