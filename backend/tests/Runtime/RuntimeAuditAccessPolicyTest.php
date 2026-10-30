<?php
namespace App\Tests\Runtime;

use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\{PublishedModuleCatalog,PermissionResolver,RuntimeAuditAccessPolicy,RuntimeAuditAccessScope,StructuralIntegrityService};
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request,RequestStack};

final class RuntimeAuditAccessPolicyTest extends TestCase
{
    private function permissions(array $map, array $groups=[]): PermissionResolver
    {
        $payload=['groups'=>$groups,'permissions'=>$map];
        return new class(new RequestStack(),$payload) extends PermissionResolver {
            public function __construct(RequestStack $stack, private array $payload){parent::__construct($stack);}
            public function getCurrentUserPayload():array{return $this->payload;}
            public function getTenantId():string{return 'a';}
        };
    }
    private function catalog(): PublishedModuleCatalog
    {
        $definitions=[
            'home'=>['programs'=>[['id'=>'a','moduleId'=>'ciw','screenId'=>'ciw.a'],['id'=>'fake','moduleId'=>'sales','screenId'=>'ciw.fake']], 'navigation'=>['groups'=>[]]],
            'ciw.a'=>['program'=>['entity'=>'a','permission'=>'a.read'],'permissions'=>['read'=>true], 'crud'=>['form'=>['tabs'=>[['linkedPage'=>['screenId'=>'ciw.child']]]]]],
            'ciw.child'=>['program'=>['entity'=>'child','permission'=>'child.read'],'permissions'=>['read'=>true]],
            'ciw.fake'=>['program'=>['entity'=>'fake','permission'=>'fake.read'],'permissions'=>['read'=>true]],
        ];
        $screens=[];foreach($definitions as $id=>$d)$screens[$id]=(new ScreenDefinition())->setScreenId($id)->setStatus('published')->setDefinition($d);
        $repo=$this->createStub(ScreenDefinitionRepository::class);$repo->method('findPublishedByScreenId')->willReturnCallback(fn($id)=>$screens[$id]??null);
        return new PublishedModuleCatalog($repo,$this->createStub(StructuralIntegrityService::class));
    }
    public function testCatalogUsesPublishedNavigationAndLinkedPagesRatherThanPrefix():void
    {
        self::assertSame(['ciw.a','ciw.child'],$this->catalog()->screenIds('ciw'));
    }
    public function testRestrictedListCountAndEventJoinCannotBeWidenedByCallerFilters():void
    {
        $permissions=$this->permissions(['ciw.audit.read'=>true,'a.read'=>true,'child.read'=>false,'fake.read'=>true]);
        $policy=new RuntimeAuditAccessPolicy($permissions,$this->catalog());
        $scope=$policy->resolve();self::assertSame(['ciw.a'],$scope->screenIds);
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
        $db->executeStatement('CREATE TABLE runtime_transaction (id INTEGER PRIMARY KEY,tenant_id TEXT,screen_id TEXT)');
        $db->executeStatement('CREATE TABLE runtime_transaction_log (id INTEGER PRIMARY KEY,transaction_id INTEGER)');
        foreach([[1,'a','ciw.a'],[2,'b','ciw.a'],[3,'a','ciw.child'],[4,'a','ciw.fake'],[5,'a','sales.order']] as [$id,$tenant,$screen]){
            $db->insert('runtime_transaction',['id'=>$id,'tenant_id'=>$tenant,'screen_id'=>$screen]);$db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$id]);
        }
        foreach(['runtime_transaction','runtime_transaction_log'] as $entity){
            $q=$db->createQueryBuilder()->select('t.id')->from($entity,'t');$policy->apply($q,['entityCode'=>$entity],$scope);
            $q->andWhere('(t.id = 1 OR t.id = 4 OR t.id = 5)');
            self::assertSame([1],array_map('intval',$q->executeQuery()->fetchFirstColumn()));
            self::assertSame(1,(int)(clone $q)->select('COUNT(*)')->executeQuery()->fetchOne());
            $q->andWhere('t.id = 4');self::assertSame([],$q->executeQuery()->fetchAllAssociative());
        }
    }
    public function testEmptyScopeAlwaysDeniesAndAdminStillKeepsTenantIsolation():void
    {
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
        $db->executeStatement('CREATE TABLE runtime_transaction (id INTEGER,tenant_id TEXT,screen_id TEXT)');
        $db->executeStatement("INSERT INTO runtime_transaction VALUES (1,'a','ciw.a'),(2,'b','ciw.a')");
        foreach([[[],[],[]],[['ciw.audit.read'=>true],[],[]],[[],['admin'],[1]]] as [$map,$groups,$expected]){
            $p=new RuntimeAuditAccessPolicy($this->permissions($map,$groups),$this->catalog());
            $q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction','t');$p->applyForCurrentUser($q,['entityCode'=>'runtime_transaction']);
            self::assertSame($expected,array_map('intval',$q->executeQuery()->fetchFirstColumn()));
        }
    }
    private function situations(): \App\Runtime\RuntimeSituationService { $s=$this->createStub(\App\Runtime\RuntimeSituationService::class);$s->method('decorateRow')->willReturnCallback(static fn($d,$r)=>$r);return $s; }
    public function testGenericRuntimeAppliesPolicyToListCountAndDirectGet():void
    {
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
        $db->executeStatement('CREATE TABLE runtime_transaction (id INTEGER PRIMARY KEY,tenant_id TEXT,screen_id TEXT)');
        $db->executeStatement('CREATE TABLE runtime_transaction_log (id INTEGER PRIMARY KEY,transaction_id INTEGER)');
        $db->executeStatement("INSERT INTO runtime_transaction VALUES (1,'a','ciw.a'),(2,'a','ciw.fake'),(3,'b','ciw.a')");
        $db->executeStatement('INSERT INTO runtime_transaction_log VALUES (1,1),(2,2),(3,3)');
        $permissions=$this->permissions(['ciw.audit.read'=>true,'a.read'=>true]);$policy=new RuntimeAuditAccessPolicy($permissions,$this->catalog());
        $definitions=$this->createStub(\App\Runtime\RuntimeEntityDefinitionResolver::class);
        $definitions->method('resolve')->willReturnCallback(static fn($e)=>['entityCode'=>$e,'quotedTableName'=>$e,'primaryKey'=>'id','primaryColumn'=>'id','dbColumns'=>['id'=>true],'fields'=>['id'=>['readable'=>true,'code'=>'id','column'=>'id','dataType'=>'integer','label'=>'ID']],'subscriberIsolation'=>['enabled'=>false]]);
        $args=[];
        foreach((new \ReflectionClass(\App\Runtime\RuntimeEntityActionService::class))->getConstructor()->getParameters()as $param){
            $type=$param->getType()->getName();$args[]=match($type){
                \Doctrine\DBAL\Connection::class=>$db,\App\Runtime\RuntimeEntityDefinitionResolver::class=>$definitions,
                PermissionResolver::class=>$permissions,RuntimeAuditAccessPolicy::class=>$policy,
                \App\Runtime\RuntimeConfiguredRuleExecutor::class=>new \App\Runtime\RuntimeConfiguredRuleExecutor($this->createStub(\App\Runtime\RuntimeTransactionService::class)),
                \App\Runtime\RuntimeSituationService::class=>$this->situations(),default=>$this->createStub($type)};
        }
        $service=new \App\Runtime\RuntimeEntityActionService(...$args);
        try{$service->handle('admin.transacoes','read',['entityCode'=>'runtime_transaction','operation'=>'read'],['entityCode'=>'auth_user']);self::fail('Caller replaced the signed entity');}
        catch(\App\Runtime\RuntimeHttpException $error){self::assertSame('ENTITY_CONTEXT_CONFLICT',$error->getErrorCode());}
        $endpoint=(new \App\Entity\RuntimeEndpoint())->setScreenId('admin.transacoes')->setEndpointId('read')->setHandler('entity.crud')->setPermission('admin.read')->setConfig(['entityCode'=>'runtime_transaction','operation'=>'read']);
        $endpoints=$this->createStub(\App\Repository\RuntimeEndpointRepository::class);$endpoints->method('findEnabled')->willReturn($endpoint);
        $dispatcherArgs=[];foreach((new \ReflectionClass(\App\Runtime\RuntimeEndpointDispatcher::class))->getConstructor()->getParameters()as $param){$type=$param->getType()->getName();$dispatcherArgs[]=match($type){\App\Repository\RuntimeEndpointRepository::class=>$endpoints,PermissionResolver::class=>$permissions,\App\Runtime\RuntimeEntityActionService::class=>$service,default=>$this->createStub($type)};}
        $dispatcher=new \App\Runtime\RuntimeEndpointDispatcher(...$dispatcherArgs);
        try{$dispatcher->dispatch('admin.transacoes','read',['entityCode'=>'auth_user']);self::fail('Dispatcher accepted entity replacement');}
        catch(\App\Runtime\RuntimeHttpException $error){self::assertSame('ENTITY_CONTEXT_CONFLICT',$error->getErrorCode());}
        foreach(['runtime_transaction','runtime_transaction_log']as $e){
            $result=$service->handle('admin.transacoes','read',['entityCode'=>$e,'operation'=>'read'],[]);
            self::assertSame(1,$result['total']);self::assertSame([1],array_column($result['data'],'id'));
            try{$service->handle('admin.transacoes','get',['entityCode'=>$e,'operation'=>'get'],['id'=>2]);self::fail('Foreign record exposed');}
            catch(\App\Runtime\RuntimeHttpException $error){self::assertSame('RECORD_NOT_FOUND',$error->getErrorCode());}
        }
        $args[array_key_last($args)]=null;$unconfigured=new \App\Runtime\RuntimeEntityActionService(...$args);
        try{$unconfigured->handle('admin.transacoes','read',['entityCode'=>'runtime_transaction','operation'=>'read'],[]);self::fail('Missing mandatory policy accepted');}
        catch(\App\Runtime\RuntimeHttpException $error){self::assertSame('AUDIT_ACCESS_POLICY_REQUIRED',$error->getErrorCode());}
    }
    public function testRevocationUsesLiveSignedAccessRatherThanLoginSnapshot():void
    {
        $map=['ciw.audit.read'=>true,'a.read'=>true];
        $auth=$this->createStub(\App\Auth\AuthService::class);
        $auth->method('resolveLiveSubscriberAccess')->willReturnCallback(function()use(&$map){return new \App\Auth\AuthenticatedUser('a','u','u','User',null,[],$map,'ldap');});
        $session=(new \App\Entity\RuntimeUserSession())->setTenantId('a')->setUserId('u')->setPermissionSnapshot(['tenantId'=>'a','user'=>['username'=>'u']]);
        $sessions=$this->createStub(\App\Auth\AuthenticatedSessionResolver::class);$sessions->method('resolve')->willReturn($session);
        $policy=new RuntimeAuditAccessPolicy($this->permissions($map),$this->catalog(),$auth,$sessions);
        self::assertSame(['ciw.a'],$policy->resolve()->screenIds);
        $map=[];
        self::assertSame([], $policy->resolve()->screenIds);
        $auth2=$this->createStub(\App\Auth\AuthService::class);
        $auth2->method('resolveLiveSubscriberAccess')->willReturn(new \App\Auth\AuthenticatedUser('a','u','u','User',null,[],[],'ldap'));
        self::assertSame([], (new RuntimeAuditAccessPolicy($this->permissions(['ciw.audit.read'=>true,'a.read'=>true]),$this->catalog(),$auth2,$sessions))->resolve()->screenIds);
    }
}


