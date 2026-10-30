<?php
namespace App\Tests\Runtime;

use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeSituationService;
use App\Runtime\RuntimeHttpException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class RuntimeAllocationLogScopeTest extends TestCase
{
    public function testDeletionScopeUsesSuccessfulCrudEventsIncludingMixedBatchAndLegacyWithoutSnapshot(): void
    {
        $service=$this->service();
        $payload=['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'audit_operation','operator'=>'eq','value'=>'delete']], 'sort'=>[['field'=>'id','dir'=>'asc']], 'take'=>1, 'skip'=>1];
        $result=$this->read($service,$payload);
        self::assertSame(3,$result['total']);
        self::assertSame([6],array_column($result['data'],'id'));
        $payload['take']=100;$payload['skip']=0;
        self::assertSame([3,6,7],array_column($this->read($service,$payload)['data'],'id'));
    }

    public function testRecordScopeNeverIncludesSiblingBatchRowsOrOtherScreensOrTenants(): void
    {
        $service=$this->service();
        $scope=[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'audit_record_id','operator'=>'eq','value'=>'2']];
        $result=$this->read($service,['filters'=>$scope,'sort'=>[['field'=>'id','dir'=>'asc']]]);
        self::assertSame([2,11,12],array_column($result['data'],'id'));
        self::assertSame(3,$result['total']);
        $scope[1]['value']=3;
        self::assertSame([3,5],array_column($this->read($service,['filters'=>$scope,'sort'=>[['field'=>'id','dir'=>'asc']]])['data'],'id'));
    }

    public function testOrdinaryCustomFiltersAreCombinedWithTheVirtualScopeBeforeCountAndPagination(): void
    {
        $result=$this->read($this->service(),['filters'=>[
            ['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],
            ['field'=>'audit_operation','operator'=>'eq','value'=>'delete'],
            ['field'=>'event_type','operator'=>'eq','value'=>'tipos.api.delete']]]);
        self::assertSame(2,$result['total']);
        self::assertSame([3,6],array_column($result['data'],'id'));
    }

    public function testMalformedVirtualScopesAreRejectedInsteadOfBroadeningTheQuery(): void
    {
        $cases=[
            [['field'=>'audit_screen_id','operator'=>'contains','value'=>'ciw.tipos-anexo']],
            [['field'=>'audit_screen_id','operator'=>'eq','value'=>'']],
            [['field'=>'audit_screen_id','operator'=>'eq','value'=>['ciw.tipos-anexo']]],
            [['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo OR 1=1']],
            [['field'=>'audit_operation','operator'=>'eq','value'=>'delete']],
            [['field'=>'audit_record_id','operator'=>'eq','value'=>2]],
            [['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'audit_operation','operator'=>'eq','value'=>'update']],
            [['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'audit_record_id','operator'=>'eq','value'=>'02']],
            [['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'audit_record_id','operator'=>'eq','value'=>0]],
            [['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'audit_record_id','operator'=>'eq','value'=>2147483648]],
            [['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'audit_record_id','operator'=>'eq','value'=>null]],
            [['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.other']],
            [['field'=>'audit_other','operator'=>'eq','value'=>'wrong']],
        ];
        foreach ($cases as $index=>$filters) {
            try { $this->read($this->service(),['filters'=>$filters]); self::fail('Invalid scope '.$index.' was accepted'); }
            catch (RuntimeHttpException $error) { self::assertSame('AUDIT_LOG_SCOPE_INVALID',$error->getErrorCode()); }
        }
        try { $this->read($this->service(),['filter'=>['logic'=>'or','filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'id','operator'=>'eq','value'=>8]]]]); self::fail('OR scope was accepted'); }
        catch (RuntimeHttpException $error) { self::assertSame('AUDIT_LOG_SCOPE_INVALID',$error->getErrorCode()); }
        foreach (['filter'=>['logic'=>'and','filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo']]],
            'sort'=>[['field'=>'audit_record_id','dir'=>'asc']], 'group'=>[['field'=>'audit_screen_id']],
            'filterUnknown'=>['filters'=>[['filters'=>[['field'=>'audit_unknown','operator'=>'eq','value'=>'x']]]]]] as $key=>$value) {
            try { $this->read($this->service(),[$key==='filterUnknown'?'filter':$key=>$value]); self::fail('Unsupported virtual scope in '.$key.' was accepted'); }
            catch (RuntimeHttpException $error) { self::assertSame('AUDIT_LOG_SCOPE_INVALID',$error->getErrorCode()); }
        }
        try { $this->read($this->service(),['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo']]],'runtime_transaction'); self::fail('Other entity accepted reserved scope'); }
        catch (RuntimeHttpException $error) { self::assertSame('AUDIT_LOG_SCOPE_INVALID',$error->getErrorCode()); }
    }

    public function testGetIsIsolatedByParentTenantEvenWithoutScopeFilters(): void
    {
        $service=$this->service();
        self::assertSame(3,$service->handle('admin.logs-transacoes','get',['entityCode'=>'runtime_transaction_log','operation'=>'get'],['id'=>3])['id']);
        $this->expectException(RuntimeHttpException::class);
        $service->handle('admin.logs-transacoes','get',['entityCode'=>'runtime_transaction_log','operation'=>'get'],['id'=>9]);
    }

    public function testOnlyTheThreeExactNamesAreReservedForBusinessEntitiesAndDeleteCanCombineWithRecordScope(): void
    {
        $business=$this->read($this->service(),['filters'=>[['field'=>'audit_business','operator'=>'contains','value'=>'x']]],'runtime_transaction');
        self::assertSame(7,$business['total']);
        $logs=$this->read($this->service(),['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.tipos-anexo'],['field'=>'audit_operation','operator'=>'eq','value'=>'delete'],['field'=>'audit_record_id','operator'=>'eq','value'=>3]]]);
        self::assertSame([3],array_column($logs['data'],'id'));
        self::assertSame(1,$logs['total']);
    }

    public function testContainerRecordIncludesConfirmedChildEffectsAcrossScreensWithoutSiblingsTenantsOrRollback(): void
    {
        $service=$this->service(true);
        $scope=static fn($screen,$id)=>['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>$screen],['field'=>'audit_record_id','operator'=>'eq','value'=>$id]],'sort'=>[['field'=>'id','dir'=>'asc']]];
        self::assertSame([101,102,103,108,109,110,111,112,113,114],array_column($this->read($service,$scope('ciw.container-painel',17))['data'],'id'));
        self::assertSame([102],array_column($this->read($service,$scope('ciw.container-despesas',71))['data'],'id'));
        self::assertSame([103],array_column($this->read($service,$scope('ciw.container-despesas',72))['data'],'id'));
        self::assertSame([],array_column($this->read($service,$scope('ciw.container-painel',71))['data'],'id'));
    }

    public function testVersionAndCostRecordQueriesUseTheirOwnVerifiedKeys(): void
    {
        $service=$this->service(true);
        $scope=static fn($screen,$id)=>['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>$screen],['field'=>'audit_record_id','operator'=>'eq','value'=>$id]],'sort'=>[['field'=>'id','dir'=>'asc']]];
        self::assertSame([108,109,110,111,112,114],array_column($this->read($service,$scope('ciw.container-versoes-custo',5))['data'],'id'));
        self::assertSame([108,109,112,113],array_column($this->read($service,$scope('ciw.container-custos-itens',111))['data'],'id'));
        self::assertSame([],array_column($this->read($service,$scope('ciw.container-custos-itens',500))['data'],'id'));
    }

    public function testFailedAndUnconfirmedProcessMessagesRemainVisibleByValidatedContainerContextOnly(): void
    {
        $service=$this->service(true);
        $result=$this->read($service,['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.container-painel'],['field'=>'audit_record_id','operator'=>'eq','value'=>19]],'sort'=>[['field'=>'id','dir'=>'asc']]]);
        self::assertSame([120],array_column($result['data'],'id'));
    }

    public function testItemRecordIncludesItsSupplierMovementsWithoutSiblingItemsOrOtherTenants(): void
    {
        $service=$this->service(true);
        $db=(new \ReflectionProperty($service,'connection'))->getValue($service);
        foreach([201=>[101,111,17],202=>[101,222,17],203=>[102,111,17],204=>[103,111,17]] as $id=>[$tx,$item,$cid]){
            $db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$tx,'event_type'=>'container.api.create','metadata'=>json_encode(['audit'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','operation'=>'create','recordId'=>$id,'resource'=>'cta_corren_fornec_imp','containerId'=>$cid,'scope'=>'container-panel-v1','itemContainerId'=>$item]])]);
        }
        $result=$this->read($service,['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.container-itens'],['field'=>'audit_record_id','operator'=>'eq','value'=>111]],'sort'=>[['field'=>'id','dir'=>'asc']]]);
        self::assertSame([108,109,112,113,201],array_column($result['data'],'id'));
    }

    private function paymentKey(int $title=7): string
    { $part=static fn(string $s):string=>'V'.mb_strlen($s,'UTF-8').':'.$s; return 'p_'.strtoupper(hash('sha256',$part('17').$part('505').$part((string)$title))); }
    private function paymentService(): RuntimeEntityActionService
    {
        $service=$this->service(true);$db=(new \ReflectionProperty($service,'connection'))->getValue($service);$key=$this->paymentKey();
        foreach([301=>['tenant-a','succeeded',$key],302=>['tenant-b','succeeded',$key],303=>['tenant-a','failed',$key],304=>['tenant-a','succeeded',$this->paymentKey(8)],306=>['tenant-a','succeeded',$key]] as $id=>[$tenant,$status,$record]){
            $db->insert('runtime_transaction',['id'=>$id,'tenant_id'=>$tenant,'screen_id'=>'ciw.container-pagamentos','operation'=>$id===306?'delete':'create','record_id'=>$record,'status'=>$status,'request_context'=>'{}']);
        }
        foreach([301=>[301,'container_payment_links',$key,$key,'create'],302=>[301,'containers',17,$key,'update'],303=>[301,'cta_corren_fornec_imp',91,$key,'create'],304=>[301,'despesas_container',81,$key,'update'],305=>[304,'container_payment_links',$this->paymentKey(8),$this->paymentKey(8),'create'],306=>[301,'unknown',7,$key,'create'],307=>[302,'container_payment_links',$key,$key,'create'],308=>[303,'cta_corren_fornec_imp',92,$key,'create'],309=>[306,'container_payment_links',$key,$key,'delete']] as $id=>[$tx,$resource,$record,$paymentKey,$op]){
            $db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$tx,'event_type'=>'payment.api.'.$op,'metadata'=>json_encode(['audit'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','operation'=>$op,'recordId'=>$record,'resource'=>$resource,'containerId'=>17,'paymentKey'=>$paymentKey,'scope'=>'container-panel-v1']])]);
        }
        return $service;
    }
    public function testPaymentOpaqueRecordScopeIncludesOnlyItsValidatedMainMasterAndChildrenBeforePagination(): void
    {
        $service=$this->paymentService();$filters=[['field'=>'audit_record_id','operator'=>'eq','value'=>$this->paymentKey()],['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.container-pagamentos']];
        $result=$this->read($service,['filters'=>$filters,'sort'=>[['field'=>'id','dir'=>'asc']]]);
        self::assertSame([301,302,303,304,309],array_column($result['data'],'id'));self::assertSame(5,$result['total']);
        $page=$this->read($service,['filters'=>$filters,'sort'=>[['field'=>'id','dir'=>'asc']],'skip'=>1,'take'=>2]);self::assertSame(5,$page['total']);self::assertSame([302,303],array_column($page['data'],'id'));
        $filters[0]['value']=$this->paymentKey(8);self::assertSame([305],array_column($this->read($service,['filters'=>$filters])['data'],'id'));
    }
    public function testPaymentDeletionScopeUsesStringRecordIdentityAndParentLogsRetainVirtualLinkEvents(): void
    {
        $service=$this->paymentService();$filters=[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.container-pagamentos'],['field'=>'audit_record_id','operator'=>'eq','value'=>$this->paymentKey()],['field'=>'audit_operation','operator'=>'eq','value'=>'delete']];
        self::assertSame([309],array_column($this->read($service,['filters'=>$filters])['data'],'id'));
        $parent=$this->read($service,['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>'ciw.container-painel'],['field'=>'audit_record_id','operator'=>'eq','value'=>17]],'sort'=>[['field'=>'id','dir'=>'asc']]]);
        self::assertSame([301,302,303,304,305,309],array_values(array_filter(array_column($parent['data'],'id'),fn($id)=>$id>=300)));
    }
    public function testOpaqueRecordScopeIsAnExceptionOnlyForPaymentScreenAndExactUppercaseKey(): void
    {
        foreach([['ciw.container-pagamentos','p_'.str_repeat('a',64)],['ciw.container-pagamentos',$this->paymentKey().' OR 1=1'],['ciw.container-pagamentos',7],['ciw.container-pagamentos','free text'],['ciw.container-itens',$this->paymentKey()],['ciw.tipos-anexo',$this->paymentKey()]] as [$screen,$record]){
            try{$this->read($this->paymentService(),['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>$screen],['field'=>'audit_record_id','operator'=>'eq','value'=>$record]]]);self::fail('Invalid payment/numeric scope accepted');}
            catch(RuntimeHttpException $e){self::assertSame('AUDIT_LOG_SCOPE_INVALID',$e->getErrorCode());}
        }
    }

    private function read(RuntimeEntityActionService $service,array $payload,string $entity='runtime_transaction_log'): array
    {
        return $service->handle('admin.logs-transacoes','read',['entityCode'=>$entity,'operation'=>'read'],$payload);
    }

    private function service(bool $containers=false): RuntimeEntityActionService
    {
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
        $db->executeStatement('CREATE TABLE runtime_transaction (id INTEGER PRIMARY KEY, tenant_id TEXT, screen_id TEXT, operation TEXT, record_id TEXT, status TEXT, request_context TEXT)');
        $db->executeStatement('CREATE TABLE runtime_transaction_log (id INTEGER PRIMARY KEY, transaction_id INTEGER, event_type TEXT, metadata TEXT)');
        foreach ([1=>['tenant-a','ciw.tipos-anexo','batch',null,'succeeded'],2=>['tenant-a','ciw.tipos-anexo','update','2','succeeded'],3=>['tenant-a','ciw.tipos-anexo','delete','6','succeeded'],4=>['tenant-a','ciw.tipos-anexo','delete','7','failed'],5=>['tenant-a','ciw.other','delete','2','succeeded'],6=>['tenant-b','ciw.tipos-anexo','delete','2','succeeded'],7=>['tenant-a','ciw.tipos-anexo','delete','8','succeeded']] as $id=>$values) {
            $db->insert('runtime_transaction',array_combine(['id','tenant_id','screen_id','operation','record_id','status','request_context'],[$id,...$values,'{}']));
        }
        $audit=static fn($id,$op)=>['audit'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','operation'=>$op,'recordId'=>$id]];
        foreach ([
            1=>[1,'tipos.api.create',$audit(4,'create')],2=>[1,'tipos.api.update',$audit(2,'update')],3=>[1,'tipos.api.delete',$audit(3,'delete')],
            4=>[1,'tipos.api.batch',['audit'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','operation'=>'batch']]],
            5=>[2,'tipos.api.update',$audit(3,'update')],6=>[3,'tipos.api.delete',['audit'=>['available'=>false]]],7=>[7,'native.delete',[]],
            8=>[4,'tipos.api.delete',$audit(7,'delete')],9=>[6,'tipos.api.delete',$audit(2,'delete')],10=>[5,'tipos.api.delete',$audit(2,'delete')],
            11=>[2,'tipos.api.get',[]],12=>[2,'tipos.api.audit.unavailable',[]],13=>[1,'tipos.api.batch.failed',[]],14=>[1,'tipos.api.delete.failed',[]],
        ] as $id=>[$tx,$event,$metadata]) { $db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$tx,'event_type'=>$event,'metadata'=>json_encode($metadata)]); }
        if($containers){
            foreach([101=>['tenant-a','ciw.container-versoes-gerar','succeeded'],102=>['tenant-b','ciw.container-versoes-gerar','succeeded'],103=>['tenant-a','ciw.container-versoes-gerar','failed']] as $id=>[$tenant,$screen,$status]){
                $db->insert('runtime_transaction',['id'=>$id,'tenant_id'=>$tenant,'screen_id'=>$screen,'operation'=>'process','record_id'=>'17','status'=>$status,'request_context'=>'{}']);
            }
            foreach([101=>[101,'containers',17,17],102=>[101,'despesas_container',71,17],103=>[101,'despesas_container',72,17],104=>[101,'despesas_container',73,18],105=>[102,'despesas_container',71,17],106=>[103,'despesas_container',71,17],107=>[101,'unknown',17,17]] as $id=>[$tx,$resource,$record,$cid]){
                $db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$tx,'event_type'=>'container.api.update','metadata'=>json_encode(['audit'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','operation'=>'update','recordId'=>$record,'resource'=>$resource,'containerId'=>$cid,'scope'=>'container-panel-v1']])]);
            }
        }
        if($containers){
            foreach([108=>['item_container_custos',500,111,5],109=>['item_custos',9,111,5],110=>['container_versions',5,null,5],111=>['item_container_custo_despesas','[5,3]',null,5],112=>['cta_corren_custo_distr',91,111,5],113=>['cta_corren_custo_distr',92,111,null],114=>['cta_corren_custo_distr',93,222,5]] as $id=>[$resource,$record,$item,$version]){
                $db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>101,'event_type'=>'container.api.update','metadata'=>json_encode(['audit'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','operation'=>'update','recordId'=>$record,'resource'=>$resource,'containerId'=>17,'scope'=>'container-panel-v1','itemContainerId'=>$item,'versionId'=>$version]])]);
            }
        }
        if($containers){
            foreach([120=>[101,19,'container_alocacao_progress'],121=>[102,19,'container_alocacao_progress'],122=>[101,20,'container_alocacao_progress'],123=>[101,19,'untrusted']] as $id=>[$tx,$cid,$entity]){
                $db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$tx,'event_type'=>'container.api.process.failed','metadata'=>json_encode(['entityCode'=>$entity,'containerContext'=>['version'=>1,'source'=>'runtime-validated-context','containerId'=>$cid]])]);
            }
        }
        $definitions=$this->createStub(RuntimeEntityDefinitionResolver::class);
        $definitions->method('resolve')->willReturnCallback(static function(string $entity):array {
            $columns=$entity==='runtime_transaction'?['id'=>'integer','tenant_id'=>'string','screen_id'=>'string','operation'=>'string','record_id'=>'string','status'=>'string','request_context'=>'json']:['id'=>'integer','transaction_id'=>'integer','event_type'=>'string','metadata'=>'json'];$fields=[];
            foreach($columns as $code=>$type){$fields[$code]=['code'=>$code,'column'=>$code,'dataType'=>$type,'readable'=>true,'writable'=>false];}
            return ['entityCode'=>$entity,'tableName'=>$entity,'quotedTableName'=>'"'.$entity.'"','primaryKey'=>'id','primaryColumn'=>'id','dbColumns'=>array_fill_keys(array_keys($columns),true),'fields'=>$fields,'subscriberIsolation'=>['enabled'=>false]];
        });
        $permissions=$this->createStub(PermissionResolver::class);$permissions->method('getTenantId')->willReturn('tenant-a');
        $situations=$this->createStub(RuntimeSituationService::class);$situations->method('decorateRow')->willReturnCallback(static fn($definition,$row)=>$row);
        $arguments=[];
        foreach((new \ReflectionClass(RuntimeEntityActionService::class))->getConstructor()->getParameters() as $parameter){$type=$parameter->getType()->getName();$arguments[]=match($type){Connection::class=>$db,RuntimeEntityDefinitionResolver::class=>$definitions,PermissionResolver::class=>$permissions,RuntimeSituationService::class=>$situations,\App\Runtime\RuntimeConfiguredRuleExecutor::class=>new \App\Runtime\RuntimeConfiguredRuleExecutor($this->createStub(\App\Runtime\RuntimeTransactionService::class)),default=>$this->createStub($type)};}
        return new RuntimeEntityActionService(...$arguments);
    }
}
