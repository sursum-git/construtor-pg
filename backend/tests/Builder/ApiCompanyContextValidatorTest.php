<?php
namespace App\Tests\Builder;
use App\Builder\ApiCompanyContextValidator;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;

final class ApiCompanyContextValidatorTest extends TestCase
{
    public function testSingleAdapterForAllApiCallsWithoutTableOrOperationClassification():void
    {
        $config=['version'=>1,'moduleId'=>'ciw','adapter'=>'empresa'];
        self::assertSame($config,ApiCompanyContextValidator::validateEntityConfig($config,'api'));
        foreach(['read','get','create','update','delete','options','process','auxiliar'] as $operation) {
            self::assertSame(['mode'=>'company','adapter'=>'empresa'],ApiCompanyContextValidator::operationEntry($config,$operation));
        }
        $this->expectException(RuntimeHttpException::class);
        ApiCompanyContextValidator::validateEntityConfig($config,'persistence');
    }
    private function binding():array{return ['key'=>'ima','ownerTenantId'=>'default','moduleId'=>'ciw','apiSourceCode'=>'ciw','externalCompanyId'=>1,'ems2Code'=>1,'ems5Code'=>100,'revision'=>1,'enabled'=>true];}
    public function testBindingIsStrictAndDoesNotInferCodes():void
    {
        $v=new ApiCompanyContextValidator();$b=$this->binding();self::assertSame($b,$v->validateBinding($b));
        foreach([['externalCompanyId'=>'1'],['revision'=>0],['ems2Code'=>-1],['enabled'=>1],['key'=>'CIW.Company'],['password'=>'forbidden'],['externalCompanyId'=>2147483648]]as $bad){
            try{$v->validateBinding(array_replace($b,$bad));self::fail('Invalid binding accepted');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_CONFIG_INVALID',$e->getErrorCode());}
        }
    }
    public function testEntityModesRequireExplicitApiTypeAndRegisteredMapping():void
    {
        $v=new ApiCompanyContextValidator();$c=['version'=>1,'moduleId'=>'ciw','operations'=>['read'=>['mode'=>'company','adapter'=>'ciw-v1'],'get'=>['mode'=>'record','adapter'=>'ciw-v1'],'options'=>['mode'=>'shared']]];
        self::assertSame($c,$v->validateEntityConfig($c,'api'));self::assertSame([],$v->validateEntityConfig([],'persistence'));
        foreach([[$c,'persistence'],[array_replace($c,['version'=>2]),'api'],[array_replace($c,['operations'=>['read'=>['mode'=>'guess']]]),'api'],[array_replace($c,['operations'=>['read'=>['mode'=>'company']]]),'api']]as [$invalid,$type]){
            try{$v->validateEntityConfig($invalid,$type);self::fail('Invalid context accepted');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_CONFIG_INVALID',$e->getErrorCode());}
        }
    }
    public function testSourceMappingRejectsCredentialsAndFreeDestinations():void
    {
        $v=new ApiCompanyContextValidator();$config=['version'=>1,'adapters'=>['ciw-v1'=>['body'=>['empresa_id'=>'externalCompanyId'],'query'=>[],'headers'=>[]]]];
        self::assertSame($config,$v->validateSourceConfig($config));
        foreach([['headers'=>['Authorization'=>'externalCompanyId']],['url'=>'externalCompanyId'],['body'=>['empresa_id'=>'request.empresa']],['body'=>['x.y'=>'ems2Code']]]as $bad){
            try{$v->validateSourceConfig(['version'=>1,'adapters'=>['ciw-v1'=>$bad]]);self::fail('Unsafe mapping accepted');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_CONFIG_INVALID',$e->getErrorCode());}
        }
    }
    public function testUnsupportedProviderCannotEnableAnIneffectiveCompanySelector():void
    {
        $class=new \ReflectionClass(\App\Builder\ProgramBuilderService::class);$builder=$class->newInstanceWithoutConstructor();
        $this->expectException(RuntimeHttpException::class);$this->expectExceptionMessage('Contexto empresarial exige transporte API generico.');
        $class->getMethod('normalizeApiSourceRegistryPayload')->invoke($builder,['code'=>'example','name'=>'Example','providerType'=>'odoo','companyContext'=>['version'=>1,'adapters'=>['company'=>['body'=>['empresa_id'=>'externalCompanyId']]]]]);
    }
    public function testBuilderRegistryAndCompiledBindingsPreserveCompanyConfiguration():void
    {
        $class=new \ReflectionClass(\App\Builder\ProgramBuilderService::class);$builder=$class->newInstanceWithoutConstructor();
        $context=['version'=>1,'adapters'=>['ciw'=>['body'=>['empresa_id'=>'externalCompanyId']]]];
        $raw=['code'=>'ciw','name'=>'CIW','baseUrl'=>'https://example.invalid','companyContext'=>$context,'operations'=>[['code'=>'read','name'=>'Ler','type'=>'list','path'=>'/rows','method'=>'POST','itemsPath'=>'data']]];
        $normalized=$class->getMethod('normalizeApiSourceRegistryPayload')->invoke($builder,$raw);
        self::assertSame($context,$normalized['companyContext']??null);
        $source=(new \App\Entity\BuilderApiSource())->setCode('ciw')->setBaseUrl('https://example.invalid')->setMetadata(['operations'=>$normalized['operations'],'companyContext'=>$context]);
        $compiled=$class->getMethod('resolveApiSourceBinding')->invoke($builder,$source,'read','');
        self::assertSame($context,$compiled['companyContext']??null);
        $payload=$class->getMethod('apiSourcePayload')->invoke($builder,$source);
        self::assertSame($context,$payload['companyContext']??null);
    }
    public function testEntityOptInSurvivesNormalizationAndEditorPayloadRoundTrip():void
    {
        $class=new \ReflectionClass(\App\Builder\ProgramBuilderService::class);$builder=$class->newInstanceWithoutConstructor();
        $source=(new \App\Entity\BuilderApiSource())->setCode('ciw')->setMetadata(['companyContext'=>['version'=>1,'adapters'=>['ciw'=>['body'=>['empresa_id'=>'externalCompanyId']]]],'operations'=>[['code'=>'read','type'=>'list','path'=>'https://example.invalid/rows','method'=>'POST','itemsPath'=>'data']]]);
        $repo=$this->createStub(\App\Repository\BuilderApiSourceRepository::class);$repo->method('findOneBy')->willReturn($source);$class->getProperty('apiSources')->setValue($builder,$repo);
        $company=['version'=>1,'moduleId'=>'ciw','operations'=>['read'=>['mode'=>'company','adapter'=>'ciw']]];
        $input=['code'=>'company_rows','name'=>'Rows','entityType'=>'api','skipStructureValidation'=>true,'apiSourceCode'=>'ciw','apiListOperationCode'=>'read','apiCompanyContext'=>$company,'fields'=>[['code'=>'id','label'=>'ID','dataType'=>'integer','primaryKey'=>true,'apiJsonPath'=>'id']]];
        $normalized=$class->getMethod('normalizeEntityPayload')->invoke($builder,$input);self::assertSame($company,$normalized['apiCompanyContext']);
        $entity=(new \App\Entity\BuilderEntity())->setCode('company_rows')->setName('Rows')->setEntityType('api')->setMetadata(['apiSource'=>$normalized['apiSource'],'apiBinding'=>$normalized['apiBinding'],'apiCompanyContext'=>$normalized['apiCompanyContext']]);
        self::assertSame($company,$class->getMethod('entityPayload')->invoke($builder,$entity)['apiCompanyContext']);
        $input['apiCompanyContext']['operations']['read']['adapter']='missing';
        $this->expectException(RuntimeHttpException::class);$class->getMethod('normalizeEntityPayload')->invoke($builder,$input);
    }
}
