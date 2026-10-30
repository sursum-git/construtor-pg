<?php
namespace App\Tests\Runtime;

use App\Entity\{BuilderEntity,RuntimeUserSession,ScreenDefinition};
use App\Repository\BuilderEntityRepository;
use App\Runtime\{ApiCompanyContextResolver,ApiCompanyBindingRegistry,RuntimeSessionGuard,RuntimeLivePermissionResolver,PermissionResolver,PublishedModuleCatalog,StructuralIntegrityService,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class ApiCompanyContextResolverTest extends TestCase
{
    private function fixture(array &$permissions,RuntimeUserSession $session,string $type='api',bool $simple=false):ApiCompanyContextResolver
    {
        $bindings=[];foreach(['ima'=>1,'med'=>5]as $key=>$id)$bindings[]=['key'=>$key,'moduleId'=>'ciw','apiSourceCode'=>'ciw','ownerTenantId'=>'default','externalCompanyId'=>$id,'ems2Code'=>$id,'ems5Code'=>$id*100,'revision'=>1,'enabled'=>true,'companyCode'=>$key,'label'=>strtoupper($key)];
        $registry=$this->createStub(ApiCompanyBindingRegistry::class);$registry->method('bindings')->willReturn($bindings);$registry->method('moduleUsesApi')->willReturn($type==='api');
        $registry->method('revisionFingerprint')->willReturnCallback(fn($b,$k)=>hash('sha256',$k));$registry->method('permission')->willReturnCallback(fn($m,$k)=>"api.company.$m.$k.use");
        $registry->method('findEnabled')->willReturnCallback(function($t,$m,$k,$s=null)use($bindings){foreach($bindings as $b)if($b['key']===$k&&$t==='default'&&$m==='ciw'&&($s===null||$s==='ciw'))return $b;throw new RuntimeHttpException('API_COMPANY_UNAVAILABLE','Indisponivel',403);});
        $registry->method('sourceConfig')->willReturn(['version'=>1,'adapters'=>['ciw'=>['body'=>['empresa_id'=>'externalCompanyId']]]]);
        $guard=$this->createStub(RuntimeSessionGuard::class);$guard->method('ensureActive')->willReturn($session);
        $live=$this->createStub(RuntimeLivePermissionResolver::class);$live->method('resolve')->willReturnCallback(function()use(&$permissions){return new class(new RequestStack(),$permissions)extends PermissionResolver{public function __construct($s,private array $p){parent::__construct($s);}public function getTenantId():string{return 'default';}public function getCurrentUserPayload():array{return ['groups'=>[],'permissions'=>$this->p];}};});
        $catalog=$this->createStub(PublishedModuleCatalog::class);$catalog->method('screenIds')->willReturn(['ciw.a']);$catalog->method('screen')->willReturn((new ScreenDefinition())->setDefinition(['program'=>['entity'=>'a','permission'=>'a.read'],'permissions'=>['read'=>true]]));
        $entity=(new BuilderEntity())->setCode('a')->setEntityType($type)->setMetadata(['apiBinding'=>['sourceCode'=>'ciw'],'apiCompanyContext'=>['version'=>1,'moduleId'=>'ciw','operations'=>['read'=>['mode'=>'company','adapter'=>'ciw'],'update'=>['mode'=>'record','adapter'=>'ciw'],'options'=>['mode'=>'shared']]]]);
        if($simple){$metadata=$entity->getMetadata();$metadata['apiCompanyContext']=['version'=>1,'moduleId'=>'ciw','adapter'=>'ciw'];$entity->setMetadata($metadata);}
        $entities=$this->createStub(BuilderEntityRepository::class);$entities->method('findOneBy')->willReturn($entity);
        $em=$this->createStub(EntityManagerInterface::class);$em->method('wrapInTransaction')->willReturnCallback(fn($work)=>$work($em));
        $endpoints=$this->createStub(\App\Repository\RuntimeEndpointRepository::class);$endpoints->method('findBy')->willReturn([(new \App\Entity\RuntimeEndpoint())->setEndpointId('read')->setHandler('entity.api.crud')->setConfig(['entityCode'=>'a','operation'=>'read'])]);
        return new ApiCompanyContextResolver($registry,$guard,$live,$entities,$catalog,$this->createStub(StructuralIntegrityService::class),$em,$endpoints);
    }
    public function testSingleTransportMappingRequiresAndSendsCompanyForAllOperations():void
    {
        $p=['api.company.ciw.ima.use'=>true,'a.read'=>true];$session=(new RuntimeUserSession())->setTenantId('default')->setUserId('u');$r=$this->fixture($p,$session,'api',true);
        $r->select('ciw','ima',0);
        foreach(['read','get','create','update','delete','batch','options','process','auxiliar'] as $op){
            $context=$r->forOperation('ciw.a','a',$op,1);
            self::assertSame(1,$context->trustedValues()['externalCompanyId']);
        }
        self::assertSame(['mode'=>'company','operation'=>'read'],$r->describeScreen('ciw.a',['program'=>['entity'=>'a']])['endpointModes']['read']);
    }
    public function testPublicScreenMetadataContainsOnlyModuleAndClassifiedEndpoints():void
    {
        $p=['a.read'=>true];$session=(new RuntimeUserSession())->setTenantId('default');$r=$this->fixture($p,$session);
        $description=$r->describeScreen('ciw.a',['program'=>['entity'=>'a'],'dataSource'=>['api'=>['read'=>['endpointId'=>'read','method'=>'POST']]]]);
        self::assertSame('ciw',$description['moduleId']);
        self::assertSame(['mode'=>'company','operation'=>'read'],$description['endpointModes']['read']);
        self::assertArrayNotHasKey('externalCompanyId',$description);
    }
    public function testSelectionIsAuthorizedPerModuleAndDoesNotChangeTenant():void
    {
        $p=['api.company.ciw.ima.use'=>true,'a.read'=>true];$session=(new RuntimeUserSession())->setTenantId('default')->setUserId('u');$r=$this->fixture($p,$session);
        self::assertSame(['ima'],array_column($r->options('ciw')['items'],'key'));
        $selected=$r->select('ciw','ima',0);self::assertSame(1,$selected['selectionRevision']);self::assertSame('default',$session->getTenantId());
        $context=$r->forOperation('ciw.a','a','read',1);self::assertSame(1,$context->trustedValues()['externalCompanyId']);self::assertNull($r->forOperation('ciw.a','a','options',null));
        try{$r->select('ciw','med',1);self::fail('Unauthorized selection');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_FORBIDDEN',$e->getErrorCode());}
    }
    public function testConcurrentSelectionStaleFormAndRevokedGrantFailClosed():void
    {
        $p=['api.company.ciw.ima.use'=>true,'a.read'=>true];$session=(new RuntimeUserSession())->setTenantId('default')->setUserId('u');$r=$this->fixture($p,$session);$r->select('ciw','ima',0);
        foreach([fn()=>$r->select('ciw','ima',0),fn()=>$r->forOperation('ciw.a','a','update',0)]as $call){try{$call();self::fail('Stale context accepted');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_CONTEXT_CHANGED',$e->getErrorCode());}}
        $p=['a.read'=>true];try{$r->forOperation('ciw.a','a','read',1);self::fail('Revoked company accepted');}catch(RuntimeHttpException $e){self::assertSame('API_COMPANY_FORBIDDEN',$e->getErrorCode());}
        self::assertSame([], $r->options('ciw')['items']);
    }
}
