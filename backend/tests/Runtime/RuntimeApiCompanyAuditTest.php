<?php
namespace App\Tests\Runtime;
use App\Runtime\{ApiCompanyContext,RuntimeTransactionService,PermissionResolver,RuntimeAuditAccessPolicy,RuntimeAuditAccessScope,PublishedModuleCatalog};
use App\Entity\RuntimeTransactionLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class RuntimeApiCompanyAuditTest extends TestCase
{
    public function testTransactionAndEventsUsePinnedServerContextInsteadOfSubmittedMetadata():void
    {
        $logs=[];$em=$this->createStub(EntityManagerInterface::class);$em->method('persist')->willReturnCallback(function($entity)use(&$logs){if($entity instanceof RuntimeTransactionLog)$logs[]=$entity;});
        $p=new PermissionResolver(new RequestStack());$args=[];
        foreach((new \ReflectionClass(RuntimeTransactionService::class))->getConstructor()->getParameters()as $param){$type=$param->getType()->getName();$args[]=match($type){EntityManagerInterface::class=>$em,PermissionResolver::class=>$p,\App\Auth\AuthenticatedSessionResolver::class=>null,default=>$this->createStub($type)};}
        $service=new RuntimeTransactionService(...$args);
        $operational=$service->beginOperational(['apiCompany'=>['source'=>'authenticated-api-company','key'=>'forged']]);
        self::assertArrayNotHasKey('apiCompany',$operational->getRequestContext());
        $transaction=$service->begin('ciw.a','read','entity.api.crud',['apiCompany'=>['key'=>'forged']]);
        $context=new ApiCompanyContext('ciw','ciw','company',2,['key'=>'ima','companyCode'=>'ima','externalCompanyId'=>1,'ems2Code'=>1,'ems5Code'=>100,'revision'=>3],[]);
        $service->attachApiCompanyContext($context);$service->log('test',metadata:['apiCompany'=>['key'=>'forged','token'=>'fixture-only']]);
        self::assertSame($context->auditMetadata(),$transaction->getRequestContext()['apiCompany']);self::assertSame($context->auditMetadata(),$logs[array_key_last($logs)]->getMetadata()['apiCompany']);
        self::assertStringNotContainsString('fixture-only',json_encode($logs[array_key_last($logs)]->getMetadata()));
    }
    public function testConfiguredRequestIsPendingBeforeTheFirstPersistAndCanBecomeAuthorized():void
    {
        $marker=['version'=>1,'source'=>'authenticated-api-company','moduleId'=>'ciw','sourceCode'=>'ciw','mode'=>'pending'];
        $classifier=$this->createStub(\App\Runtime\ApiCompanyAuditClassifier::class);$classifier->method('pending')->willReturn($marker);
        $first=null;$em=$this->createStub(EntityManagerInterface::class);$em->method('persist')->willReturnCallback(function($entity)use(&$first){if($entity instanceof \App\Entity\RuntimeTransaction && $first===null)$first=$entity->getRequestContext();});
        $args=[];foreach((new \ReflectionClass(RuntimeTransactionService::class))->getConstructor()->getParameters()as $param){$type=$param->getType()->getName();$args[]=match($type){EntityManagerInterface::class=>$em,\App\Runtime\ApiCompanyAuditClassifier::class=>$classifier,\App\Auth\AuthenticatedSessionResolver::class=>null,default=>$this->createStub($type)};}
        $service=new RuntimeTransactionService(...$args);$tx=$service->begin('ciw.a','read','entity.api.crud',['apiCompany'=>['mode'=>'shared'],'record'=>['id'=>7]]);
        self::assertSame($marker,$first['apiCompany']??null);
        $context=new ApiCompanyContext('ciw','ciw','company',2,['key'=>'ima','companyCode'=>'ima','externalCompanyId'=>1,'ems2Code'=>1,'ems5Code'=>100,'revision'=>1],[]);
        $service->attachApiCompanyContext($context);self::assertSame('ima',$tx->getRequestContext()['apiCompany']['key']);
    }
    public function testCompanyScopeAppliesToParentListCountAndEventDetails():void
    {
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
        $db->executeStatement('CREATE TABLE runtime_transaction (id INTEGER PRIMARY KEY,tenant_id TEXT,screen_id TEXT,request_context TEXT)');
        $db->executeStatement('CREATE TABLE runtime_transaction_log (id INTEGER PRIMARY KEY,transaction_id INTEGER)');
        $data=[1=>[],2=>['apiCompany'=>['version'=>1,'moduleId'=>'ciw','mode'=>'company','key'=>'ima']],3=>['apiCompany'=>['version'=>1,'moduleId'=>'ciw','mode'=>'record','key'=>'med']],4=>['apiCompany'=>['version'=>1,'moduleId'=>'ciw','mode'=>'shared']],6=>['apiCompany'=>['version'=>1,'moduleId'=>'ciw','mode'=>'pending']],5=>['apiCompany'=>['version'=>2,'moduleId'=>'ciw','mode'=>'company','key'=>'ima']]];
        foreach($data as $id=>$ctx){if(isset($ctx['apiCompany']))$ctx['apiCompany']['source']='authenticated-api-company';$db->insert('runtime_transaction',['id'=>$id,'tenant_id'=>'a','screen_id'=>'ciw.a','request_context'=>json_encode($ctx)]);$db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$id]);}
        $policy=new RuntimeAuditAccessPolicy($this->createStub(PermissionResolver::class),$this->createStub(PublishedModuleCatalog::class),connection:$db);$scope=new RuntimeAuditAccessScope('a',false,['ciw.a'],['ciw/ima']);
        foreach(['runtime_transaction','runtime_transaction_log']as $entity){
            $q=$db->createQueryBuilder()->select('t.id')->from($entity,'t');$policy->apply($q,['entityCode'=>$entity],$scope);
            self::assertSame([1,2,4],array_map('intval',$q->orderBy('t.id')->executeQuery()->fetchFirstColumn()));self::assertSame(3,(int)(clone $q)->select('COUNT(*)')->executeQuery()->fetchOne());
            $q->andWhere('t.id=3');self::assertSame([],$q->executeQuery()->fetchAllAssociative());
        }
    }
}
