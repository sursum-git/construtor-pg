<?php
namespace App\Tests\Runtime;

use App\Admin\AdminCrudDefinitionFactory;
use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\{PermissionResolver,ProgramCustomizationResolver,RuntimeMetadataSanitizer,ScreenDefinitionService,StructuralIntegrityService,UserLayoutService};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request,RequestStack};

final class ScreenDefinitionLogsTest extends TestCase
{
    public static function purchaseLogModes(): array
    {
        return [
            'payment links'=>['proc_compra_pagamentos_progress','recordEvents','runtime.transaction-events'],
            'purchase parent'=>['procs_compra_financeiro_med_progress','recordEvents','runtime.transaction-events'],
            'generic CRUD'=>['test','transactions','runtime.transactions'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('purchaseLogModes')]
    public function testPurchaseEntityFormsUseScopedRecordEventsWhileGenericCrudKeepsTransactions(string $entity,string $mode,string $document): void
    {
        $source=$this->screen('ciw.empresas',['program'=>['entity'=>$entity]]);
        $stored=$source->getDefinition();
        $definition=$this->service($this->catalog($source))->getDefinition('ciw.empresas');
        self::assertSame($mode,$definition['crud']['form']['logs']['mode']);
        self::assertSame($document,$definition['crud']['form']['logs']['documentId']);
        self::assertSame('ciw.empresas',$definition['crud']['form']['logs']['screenId']);
        self::assertTrue($definition['crud']['form']['logs']['requiresRecord']);
        self::assertSame('deletions',$definition['program']['logs']['mode']);
        self::assertSame($stored,$source->getDefinition());
    }

    public function testPurchaseEventShortcutStillRequiresReadPermissionOnEventDestination(): void
    {
        foreach(['proc_compra_pagamentos_progress','procs_compra_financeiro_med_progress'] as $entity){
            $source=$this->screen('ciw.empresas',['program'=>['entity'=>$entity]]);$catalog=$this->catalog($source);
            $catalog['admin.logs-transacoes']->setDefinition(['pageType'=>'crud','permissions'=>['read'=>false]]);
            $definition=$this->service($catalog)->getDefinition('ciw.empresas');
            self::assertFalse($definition['crud']['form']['logs']['enabled']);
            self::assertArrayNotHasKey('logs',$definition['program']);
        }
    }

    private function screen(string $id, array $definition = []): ScreenDefinition
    {
        return (new ScreenDefinition())->setScreenId($id)->setStatus('published')->setDefinition(array_replace_recursive([
            'pageType'=>'crud', 'program'=>['id'=>'test','entity'=>'test'], 'permissions'=>['read'=>true],
            'crud'=>['form'=>['logs'=>['enabled'=>false]]],
        ], $definition));
    }

    private function service(array $screens, string $groups = 'admin', string $permissions = ''): ScreenDefinitionService
    {
        $repo = $this->createStub(ScreenDefinitionRepository::class);
        $repo->method('findPublishedByScreenId')->willReturnCallback(fn($id) => $screens[$id] ?? null);
        $request = new Request(); $request->headers->set('X-Runtime-Groups', $groups); $request->headers->set('X-Runtime-Permissions', $permissions);
        $stack = new RequestStack(); $stack->push($request);
        $layouts = $this->createStub(UserLayoutService::class);
        $layouts->method('buildUserLayout')->willReturn([]);
        $custom = $this->createStub(ProgramCustomizationResolver::class);
        $custom->method('resolve')->willReturn(null);
        $integrity = $this->createMock(StructuralIntegrityService::class);
        $integrity->expects(self::once())->method('assertScreen')->with($screens['ciw.empresas'] ?? $screens['admin.transacoes'] ?? $screens['admin.logs-transacoes']);
        return new ScreenDefinitionService($repo, new RuntimeMetadataSanitizer(), new PermissionResolver($stack), $layouts, $custom, $integrity);
    }

    private function catalog(ScreenDefinition $source): array
    {
        return [$source->getScreenId()=>$source,
            'admin.transacoes'=>$this->screen('admin.transacoes',['security'=>['userGroups'=>['admin']]]),
            'admin.logs-transacoes'=>$this->screen('admin.logs-transacoes',['security'=>['userGroups'=>['admin']]])];
    }

    public function testPanelAndProcessGetAuthorizedContainerEventsWithoutChangingSignedMetadata(): void
    {
        foreach(['crud','process'] as $type){
            $source=$this->screen('ciw.empresas',['pageType'=>$type,'program'=>['entity'=>$type==='crud'?'container_painel_progress':'container_processos_progress'],'contextFields'=>[['field'=>'container_id','required'=>true,'readonly'=>true]]]);
            $before=$source->getDefinition();$definition=$this->service($this->catalog($source))->getDefinition('ciw.empresas');
            $logs=$type==='crud'?$definition['crud']['form']['logs']:($definition['logs']??[]);
            self::assertSame('recordEvents',$logs['mode']??null);self::assertTrue($logs['enabled']??false);
            if($type==='process'){self::assertSame('container_id',$logs['contextField']);self::assertSame('ciw.container-painel',$logs['screenId']);}
            self::assertSame($before,$source->getDefinition());
            $denied=$this->service($this->catalog($source),'vendas')->getDefinition('ciw.empresas');
            self::assertFalse(($type==='crud'?$denied['crud']['form']['logs']:$denied['logs'])['enabled']??false);
        }
    }

    public function testCrudGetsAuthorizedNavigationWithoutMutatingSignedDefinition(): void
    {
        $source=$this->screen('ciw.empresas'); $before=$source->getDefinition();
        $definition=$this->service($this->catalog($source))->getDefinition('ciw.empresas');
        self::assertSame('deletions',$definition['program']['logs']['mode'] ?? null);
        self::assertSame('Logs Exclusão',$definition['program']['logs']['title'] ?? null);
        self::assertFalse($definition['program']['logs']['requiresRecord']);
        self::assertSame('transactions',$definition['crud']['form']['logs']['mode'] ?? null);
        self::assertSame('ciw.empresas',$definition['crud']['form']['logs']['screenId'] ?? null);
        self::assertTrue($definition['crud']['form']['logs']['requiresRecord']);
        self::assertSame($before,$source->getDefinition());
    }

    public function testUnauthorizedUserDoesNotReceiveTransactionNavigation(): void
    {
        $definition=$this->service($this->catalog($this->screen('ciw.empresas')),'vendas')->getDefinition('ciw.empresas');
        self::assertArrayNotHasKey('logs',$definition['program']);
        self::assertFalse($definition['crud']['form']['logs']['enabled']);
    }

    public function testCustomLogDocumentIsPreserved(): void
    {
        $logs=['enabled'=>true,'documentId'=>'custom-log'];
        $source=$this->screen('ciw.empresas',['program'=>['logs'=>$logs],'crud'=>['form'=>['logs'=>$logs]]]);
        $definition=$this->service($this->catalog($source))->getDefinition('ciw.empresas');
        self::assertSame($logs,$definition['program']['logs']);
        self::assertSame($logs,$definition['crud']['form']['logs']);
    }

    public function testTransactionOpensItsEventsAndEventScreenDoesNotRecurse(): void
    {
        $transaction=$this->screen('admin.transacoes'); $catalog=$this->catalog($transaction);
        $definition=$this->service($catalog)->getDefinition('admin.transacoes');
        self::assertFalse($definition['crud']['form']['logs']['enabled']);
        self::assertSame('linkedPage',$definition['crud']['form']['tabs'][0]['type']);
        self::assertSame('id',$definition['crud']['form']['tabs'][0]['linkedPage']['filters'][0]['sourceField']);
        self::assertSame('admin.logs-transacoes',$definition['crud']['grid']['rowLinks'][0]['screenId']);
        self::assertSame('Eventos',$definition['crud']['grid']['rowLinks'][0]['label']);
        self::assertFalse($definition['program']['logs']['enabled']);
        self::assertSame([['field'=>'screen_id','filter'=>true],['field'=>'record_id','filter'=>true]],$definition['contextFields']);
        unset($catalog['admin.transacoes']);
        $events=$this->service($catalog)->getDefinition('admin.logs-transacoes');
        self::assertFalse($events['crud']['form']['logs']['enabled']);
        self::assertSame(['transaction_id','audit_screen_id','audit_operation','audit_record_id'],array_column($events['contextFields'],'field'));
        foreach (['audit_screen_id'=>'string','audit_operation'=>'string','audit_record_id'=>'string'] as $field=>$type) {
            self::assertSame($type,$events['dataModel']['fields'][$field]['type']);
            self::assertFalse($events['dataModel']['fields'][$field]['editable']);
            self::assertNotEmpty($events['dataModel']['fields'][$field]['label']);
            self::assertTrue(array_column($events['contextFields'],null,'field')[$field]['filter']);
        }
        self::assertSame([['value'=>'delete','text'=>'Exclusão']],$events['dataModel']['fields']['audit_operation']['options']);
        self::assertFalse($events['program']['logs']['enabled']);
        self::assertTrue($events['crud']['auditSummary']['enabled']);
    }

    public function testPublishedAdminScreensKeepNewestTransactionsAndEventsFirst(): void
    {
        $definitions=AdminCrudDefinitionFactory::screens();
        $byId=array_column($definitions,null,'screenId');
        self::assertSame([['field'=>'started_at','dir'=>'desc']],$byId['admin.transacoes']['definition']['crud']['query']['defaultSort']);
        self::assertSame([['field'=>'created_at','dir'=>'desc']],$byId['admin.logs-transacoes']['definition']['crud']['query']['defaultSort']);
    }

    public function testHeaderCustomDocumentAtRootIsPreserved(): void
    {
        $logs=['enabled'=>true,'documentId'=>'custom-root-log'];
        $source=$this->screen('ciw.empresas',['logs'=>$logs]);
        $definition=$this->service($this->catalog($source))->getDefinition('ciw.empresas');
        self::assertArrayNotHasKey('logs',$definition['program']);
        self::assertSame($logs,$definition['logs']);
    }

    public function testTransactionScreenReadDeniedHidesOnlyTheRecordTransactionShortcut(): void
    {
        $source=$this->screen('ciw.empresas');$catalog=$this->catalog($source);
        $catalog['admin.transacoes']->setDefinition(['pageType'=>'crud','program'=>['entity'=>'runtime_transaction'],'permissions'=>['read'=>false]]);
        $definition=$this->service($catalog,'admin')->getDefinition('ciw.empresas');
        self::assertSame('deletions',$definition['program']['logs']['mode']);
        self::assertFalse($definition['crud']['form']['logs']['enabled']);
    }


    public function testCustomFormLogButtonKeepsItsDocumentDestination(): void
    {
        $source=$this->screen('ciw.empresas')->setDefinition(['pageType'=>'crud','crud'=>['form'=>['buttons'=>[['action'=>'logs','documentId'=>'custom-button']]]]]);
        $definition=$this->service($this->catalog($source))->getDefinition('ciw.empresas');
        self::assertArrayNotHasKey('logs',$definition['crud']['form']);
        self::assertSame('custom-button',$definition['crud']['form']['buttons'][0]['documentId']);
    }


    public function testAuditScreensExposeActorAndComparisonWithoutChangingStoredDefinition(): void
    {
        foreach (['admin.transacoes','admin.logs-transacoes'] as $screenId) {
            $stored=AdminCrudDefinitionFactory::screens()[$screenId]['definition'];
            $source=$this->screen($screenId)->setDefinition($stored);
            $catalog=$this->catalog($source);$catalog[$screenId]=$source;
            if ($screenId==='admin.logs-transacoes') { unset($catalog['admin.transacoes']); }
            $definition=$this->service($catalog)->getDefinition($screenId);
            self::assertSame(['actor_user_name','actor_user_id'],array_column(array_slice($definition['crud']['grid']['columns'],0,2),'field'));
            self::assertFalse($definition['dataModel']['fields']['actor_user_name']['editable']);
            self::assertSame($stored,$source->getDefinition());
            if ($screenId==='admin.logs-transacoes') {
                self::assertSame('audit-diff',$definition['crud']['form']['tabs'][0]['fields'][2]['renderAs']);
                self::assertSame('before_data',$definition['crud']['form']['tabs'][0]['fields'][2]['auditDiff']['beforeField']);
            }
        }
    }


    public function testBatchRowsGetOnlyTheirRecordEventsAndNavigationChecksTheMatchingDestination(): void
    {
        $source=$this->screen('ciw.empresas',['crud'=>['grid'=>['editing'=>['mode'=>'batch']]]]);
        $definition=$this->service($this->catalog($source))->getDefinition('ciw.empresas');
        self::assertSame('recordEvents',$definition['crud']['form']['logs']['mode']);
        self::assertTrue($definition['crud']['form']['logs']['requiresRecord']);
        $catalog=$this->catalog($source);$catalog['admin.logs-transacoes']->setDefinition(['pageType'=>'crud','permissions'=>['read'=>false]]);
        $denied=$this->service($catalog)->getDefinition('ciw.empresas');
        self::assertArrayNotHasKey('logs',$denied['program']);
        self::assertFalse($denied['crud']['form']['logs']['enabled']);
    }

    public function testNormalFormAndDeletionHeaderCheckIndependentDestinationPermissions(): void
    {
        $source=$this->screen('ciw.empresas');$catalog=$this->catalog($source);
        $catalog['admin.transacoes']->setDefinition(['pageType'=>'crud','permissions'=>['read'=>false]]);
        $definition=$this->service($catalog)->getDefinition('ciw.empresas');
        self::assertSame('deletions',$definition['program']['logs']['mode']);
        self::assertFalse($definition['crud']['form']['logs']['enabled']);
        $catalog=$this->catalog($source);$catalog['admin.logs-transacoes']->setDefinition(['pageType'=>'crud','permissions'=>['read'=>false]]);
        $definition=$this->service($catalog)->getDefinition('ciw.empresas');
        self::assertArrayNotHasKey('logs',$definition['program']);
        self::assertSame('transactions',$definition['crud']['form']['logs']['mode']);
    }

    public function testStoredBuiltInNavigationIsRefreshedAndDisabledWhenItsDestinationIsDenied(): void
    {
        $logs=['enabled'=>true,'mode'=>'transactions','documentId'=>'runtime.transactions','screenId'=>'ciw.empresas','requiresRecord'=>false,'title'=>'Logs'];
        $source=$this->screen('ciw.empresas',['program'=>['logs'=>$logs],'crud'=>['form'=>['logs'=>$logs]]]);
        $catalog=$this->catalog($source);
        $catalog['admin.transacoes']->setDefinition(['pageType'=>'crud','permissions'=>['read'=>false]]);
        $catalog['admin.logs-transacoes']->setDefinition(['pageType'=>'crud','permissions'=>['read'=>false]]);
        $definition=$this->service($catalog)->getDefinition('ciw.empresas');
        self::assertFalse($definition['program']['logs']['enabled']);
        self::assertFalse($definition['crud']['form']['logs']['enabled']);
    }

    public function testTransactionDoesNotExposeEventTabWhenEventReadIsDenied(): void
    {
        $source=$this->screen('admin.transacoes',['program'=>['logs'=>['enabled'=>true,'documentId'=>'old-custom-log']],'crud'=>['form'=>['logs'=>['enabled'=>true,'documentId'=>'old-custom-log']]]]);
        $catalog=$this->catalog($source);$catalog['admin.transacoes']=$source;
        $catalog['admin.logs-transacoes']->setDefinition(['pageType'=>'crud','permissions'=>['read'=>false]]);
        $definition=$this->service($catalog)->getDefinition('admin.transacoes');
        self::assertFalse($definition['program']['logs']['enabled']);self::assertFalse($definition['crud']['form']['logs']['enabled']);
        self::assertEmpty(array_filter($definition['crud']['form']['tabs']??[],fn($tab)=>($tab['type']??'')==='linkedPage'));
        self::assertEmpty($definition['crud']['grid']['rowLinks']??[]);
    }

    public function testStringViewerContextPreservesStrictNumericAndOpaqueBackendScopeValidation(): void
    {
        $class=new \ReflectionClass(\App\Runtime\RuntimeEntityActionService::class);
        $service=$class->newInstanceWithoutConstructor();$method=$class->getMethod('withAuditLogScope');
        $hash='p_'.str_repeat('A',64);
        foreach([
            ['ciw.processos-compra-financeiro-med','17',17],
            ['ciw.empresas','17',17],
            ['ciw.proc-compra-pagamentos',$hash,$hash],
            ['ciw.container-pagamentos',$hash,$hash],
        ] as [$screen,$value,$expected]){
            $payload=['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>$screen],['field'=>'audit_record_id','operator'=>'eq','value'=>$value]]];
            $result=$method->invoke($service,['entityCode'=>'runtime_transaction_log'],$payload,'read');
            self::assertSame($expected,$result['_auditLogScope']['audit_record_id']);
        }
        foreach([
            ['ciw.processos-compra-financeiro-med',$hash],['ciw.empresas',$hash],
            ['ciw.proc-compra-pagamentos','17'],['ciw.proc-compra-pagamentos','p_invalid'],
            ['ciw.empresas','17.5'],['ciw.empresas','0'],
        ] as [$screen,$value]){
            $payload=['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>$screen],['field'=>'audit_record_id','operator'=>'eq','value'=>$value]]];
            try{$method->invoke($service,['entityCode'=>'runtime_transaction_log'],$payload,'read');self::fail('Invalid string context accepted');}
            catch(\App\Runtime\RuntimeHttpException $error){self::assertSame('AUDIT_LOG_SCOPE_INVALID',$error->getErrorCode());}
        }
    }

}

