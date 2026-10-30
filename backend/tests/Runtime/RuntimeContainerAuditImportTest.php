<?php
namespace App\Tests\Runtime;

use App\Entity\{BuilderEntity,BuilderField,RuntimeTransaction,RuntimeTransactionLog};
use App\Repository\BuilderEntityRepository;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeExecutionContext,RuntimeEntityDefinitionResolver,RuntimeEnvironmentIdentityResolver,PermissionResolver};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerAuditImportTest extends TestCase
{
    private function execute(array $body, string $domain='process', ?\Throwable $failure=null): array
    {
        [$entityCode,$source,$route,$resource,$op,$pk] = match($domain) {
            'header'=>['container_painel_progress','motor_progress_container_cabecalho','containers-cabecalho','containers','update','container_id'],
            'expense'=>['container_despesas_progress','motor_progress_container_despesas','containers-despesas','container-despesas','update','despesa_container_id'],
            default=>['container_processos_progress','motor_progress_container_processos','containers-processos','versoes','gerar','container_id'],
        };
        $endpoint=['url'=>'/SursumPrograms/'.$route,'method'=>'POST','requestContextFields'=>$domain==='header'?[]:['container_id'],'bodyTemplate'=>['operation'=>$op,'resource'=>$resource]];
        $api=['responseContract'=>'sursum-programs','authHeaders'=>[],'listEndpoint'=>$endpoint,'listResponse'=>['itemsPath'=>'data']];
        if($domain==='process'){$api['operations']=[['code'=>'run','path'=>$endpoint['url'],'method'=>'POST','type'=>'custom','allowProcess'=>true,'requestContextFields'=>['container_id'],'bodyTemplate'=>$endpoint['bodyTemplate'],'itemPath'=>'data']];}
        else {$api['updateEndpoint']=$endpoint;$api['updateResponse']=['itemPath'=>'data'];}
        $entity=(new BuilderEntity())->setCode($entityCode)->setEntityType('api')->setMetadata(['apiSource'=>$api,'apiBinding'=>['sourceCode'=>$source]]);
        foreach(array_unique([$pk,'container_id','vl_prev']) as $code){$entity->addField((new BuilderField())->setCode($code)->setLabel($code)->setDataType($code==='vl_prev'?'decimal':'integer')->setPrimaryKey($code===$pk)->setOptions($domain==='expense' && $code==='container_id'?['readonly'=>true]:[]));}
        $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($entity);
        $logs=[];$manager=$this->createStub(EntityManagerInterface::class);$manager->method('persist')->willReturnCallback(static function($item)use(&$logs){if($item instanceof RuntimeTransactionLog){$logs[]=$item;}});
        $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturn(true);
        $transactions=new RuntimeTransactionService($manager,$permissions,new RuntimeExecutionContext(),$this->createStub(RuntimeEntityDefinitionResolver::class),$this->createStub(RuntimeEnvironmentIdentityResolver::class));
        $tx=(new RuntimeTransaction())->setTenantId('tenant-a')->setRequestContext(['actor'=>['source'=>'authenticated-session','version'=>1,'username'=>'real-user']]);
        (new \ReflectionProperty($transactions,'current'))->setValue($transactions,$tx);
        $client=$this->createMock(RuntimeExternalJsonClient::class);$expect=$client->expects(self::once())->method('request');
        if($failure){$expect->willThrowException($failure);}else{$expect->willReturnCallback(static function($url,$method,$headers,$request)use($body,$domain){if($domain==='expense'){self::assertSame(17,$request['container_id']??null);}return ['status'=>200,'body'=>$body];});}
        $service=new RuntimeApiEntityActionService($repo,$transactions,$client,$permissions);
        try{$result=$service->handle('ciw.test','run',['entityCode'=>$entityCode,'operation'=>$domain==='process'?'process':'update','allowProcess'=>true,'permissionPrefix'=>'panel','processOptions'=>['operationCode'=>'run','parameterFields'=>[]],'contextFields'=>[['field'=>'container_id']]],
            $domain==='process'?['parameters'=>[],'context'=>['container_id'=>17]]:['record'=>[$pk=>$domain==='expense'?71:17,'container_id'=>17,'vl_prev'=>999],'context'=>['container_id'=>17]]);
        }catch(\Throwable $error){if(!$failure){throw $error;}$result=$error;}
        return [$result,$logs,$tx];
    }
    private function audit(): array
    {
        return ['version'=>1,'source'=>'progress-transaction','resource'=>'container_processes','operation'=>'gerar','recordId'=>17,'container_id'=>17,
            'before'=>['container_id'=>17,'ptax'=>null],'after'=>['container_id'=>17,'ptax'=>0],
            'changes'=>[['resource'=>'despesas_container','operation'=>'update','recordId'=>71,'before'=>['container_id'=>17,'despesa_container_id'=>71,'vl_prev'=>null],'after'=>['container_id'=>17,'despesa_container_id'=>71,'vl_prev'=>0]]],
            'actor'=>['login'=>'forged-response-user']];
    }
    public function testConfirmedProcessImportsMasterAndChildrenWithAuthenticatedActor(): void
    {
        [$result,$logs,$tx]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$this->audit()]);
        self::assertSame('succeeded',$result['status']);self::assertSame('17',$tx->getRecordId());
        $confirmed=array_values(array_filter($logs,fn($l)=>($l->getMetadata()['audit']['available']??false)===true));
        self::assertCount(2,$confirmed);
        self::assertSame(['before'=>null,'after'=>0],$confirmed[0]->getDiffData()['ptax']);
        self::assertSame(17,$confirmed[1]->getMetadata()['audit']['containerId']);
        self::assertSame('despesas_container',$confirmed[1]->getMetadata()['audit']['resource']);
        self::assertSame('real-user',$confirmed[1]->getMetadata()['actor']['username']);
        self::assertStringNotContainsString('forged-response-user',json_encode(array_map(fn($l)=>$l->getMetadata(),$logs)));
    }
    public function testInvalidChildRejectsAllSnapshotsAndReturnsCommittedWarningWithoutRetry(): void
    {
        foreach(['scope','unknown','key','nested','extra','op'] as $case){
            $audit=$this->audit();switch($case){case 'scope':$audit['changes'][0]['after']['container_id']=18;break;case 'unknown':$audit['changes'][0]['resource']='app_user';break;case 'key':$audit['changes'][0]['recordId']=72;break;case 'nested':$audit['changes'][0]['after']['vl_prev']=['secret'=>'x'];break;case 'extra':$audit['changes'][0]['after']['tenant_id']='other';break;case 'op':$audit['operation']='fechar';break;}
            [$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);
            self::assertSame('succeeded',$result['status']);self::assertSame('invalid',$result['audit']['reason']??null);
            self::assertStringContainsString('nao repita',strtolower($result['result']['message']));
            foreach($logs as $log){self::assertSame([],$log->getBeforeData());self::assertSame([],$log->getAfterData());}
        }
    }
    public function testExpenseImportsFiscalEffectsAndMasterWithoutLosingRowIdentity(): void
    {
        $audit=$this->audit();$audit['resource']='container-despesas';$audit['operation']='update';$audit['recordId']=71;
        $audit['master']=['before'=>$audit['before'],'after'=>$audit['after']];$audit['before']=$audit['changes'][0]['before'];$audit['after']=$audit['changes'][0]['after'];
        [$result,$logs,$tx]=$this->execute(['success'=>true,'data'=>$audit['after'],'audit'=>$audit],'expense');
        self::assertSame('71',$tx->getRecordId());self::assertSame(0,$result['vl_prev']);
        $confirmed=array_values(array_filter($logs,fn($l)=>($l->getMetadata()['audit']['available']??false)===true));
        self::assertCount(2,$confirmed);self::assertSame('containers',$confirmed[0]->getMetadata()['audit']['resource']);
    }
    public function testCompositeVersionKeysAreValidatedAgainstSnapshotsAndPreserveNull(): void
    {
        $audit=$this->audit();$row=['container_id'=>17,'versao_id'=>5,'despesa_id'=>3,'vl_prev'=>null];
        $audit['changes'][]=['resource'=>'item_container_custo_despesas','operation'=>'create','recordId'=>'[5,3]','before'=>[],'after'=>$row];
        [$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);
        self::assertTrue($result['audit']['available']);self::assertSame($row,$logs[2]->getAfterData());
        $audit['changes'][1]['recordId']='[5,4]';
        [$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);self::assertFalse($result['audit']['available']);
    }
    public function testHeaderDeleteCompositesUseTheExactNamedPhysicalKey(): void
    {
        $definition=['entityCode'=>'container_painel_progress','primaryKey'=>'container_id','apiBinding'=>['sourceCode'=>'motor_progress_container_cabecalho'],'apiSource'=>['responseContract'=>'sursum-programs']];
        $endpoint=['method'=>'POST','url'=>'/SursumPrograms/containers-cabecalho','bodyTemplate'=>['resource'=>'containers','operation'=>'delete']];
        $row=['nr_container'=>17,'cod_repres'=>4,'regiao_repres'=>'SP','tipo_repres'=>2,'perc_saldo'=>0];
        $key=['nr_container'=>17,'cod_repres'=>4,'regiao_repres'=>'SP','tipo_repres'=>2];
        $audit=['version'=>1,'source'=>'progress-transaction','resource'=>'containers','operation'=>'delete','recordId'=>17,'before'=>['container_id'=>17],'after'=>[],
            'changes'=>[['resource'=>'pp_container_saldo_repres','operation'=>'delete','recordId'=>json_encode($key),'before'=>$row,'after'=>[]]]];
        $result=\App\Runtime\RuntimeContainerAudit::inspect($definition,$endpoint,['success'=>true,'audit'=>$audit],'delete',17);
        self::assertTrue($result['metadata']['available']);self::assertSame($row,$result['events'][0]['before']);
    }
    public function testCommittedContainerWithInvalidResultReturnsWarningInsteadOfAnErrorThatEncouragesRetry(): void
    {
        [$result]=$this->execute(['success'=>true,'data'=>['type'=>'unsafe'],'audit'=>$this->audit()]);
        self::assertSame('succeeded',$result['status']);self::assertStringContainsString('nao repita',strtolower($result['result']['message']));
    }

    public function testVersionAndItemCostChildrenUseVerifiedPhysicalRelationshipsForRecordLogs(): void
    {
        $audit=$this->audit();
        $audit['changes'][]=['resource'=>'item_container_custos','operation'=>'update','recordId'=>500,
            'before'=>['container_id'=>17,'item_container_custo_id'=>500,'item_container_id'=>111,'versao_id'=>5,'item_custo_id'=>8],
            'after'=>['container_id'=>17,'item_container_custo_id'=>500,'item_container_id'=>111,'versao_id'=>5,'item_custo_id'=>9]];
        $audit['changes'][]=['resource'=>'item_custos','operation'=>'create','recordId'=>9,'before'=>[],'after'=>['container_id'=>17,'item_custo_id'=>9,'vl_unit_novo'=>0]];
        [$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);
        self::assertTrue($result['audit']['available']);
        foreach(array_slice($logs,2) as $log){self::assertSame(111,$log->getMetadata()['audit']['itemContainerId']??null);self::assertSame(5,$log->getMetadata()['audit']['versionId']??null);}
    }
    public function testKnownWriterRequiresExplicitSuccessBeforeImportingAudit(): void
    {
        $definition=['entityCode'=>'container_processos_progress','primaryKey'=>'container_id','apiBinding'=>['sourceCode'=>'motor_progress_container_processos'],'apiSource'=>['responseContract'=>'sursum-programs']];
        $endpoint=['method'=>'POST','url'=>'/SursumPrograms/containers-processos','bodyTemplate'=>['resource'=>'versoes','operation'=>'gerar']];
        $result=\App\Runtime\RuntimeContainerAudit::inspect($definition,$endpoint,['audit'=>$this->audit()],'gerar',17,17);
        self::assertFalse($result['metadata']['available']);self::assertSame([],$result['events']);
    }

    public function testOptionalZeroItemLinksOnExpensesDoNotInvalidateAConfirmedTransaction(): void
    {
        $audit=$this->audit();$audit['changes'][0]['before']['item_container_id']=0;$audit['changes'][0]['after']['item_container_id']=0;
        [$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);
        self::assertTrue($result['audit']['available']);self::assertArrayNotHasKey('itemContainerId',$logs[1]->getMetadata()['audit']);
    }

    private function distributionAudit(int $type=3): array
    {
        $audit=$this->audit();
        $audit['changes'][]=['resource'=>'item_container_custos','operation'=>'create','recordId'=>500,'before'=>[],
            'after'=>['container_id'=>17,'item_container_custo_id'=>500,'item_container_id'=>111,'versao_id'=>5,'item_custo_id'=>0,'vl_custo_distribuido'=>18]];
        $audit['changes'][]=['resource'=>'cta_corren_custo_distr','operation'=>'create','recordId'=>91,'before'=>[],
            'after'=>['container_id'=>17,'cta_corren_custo_distr_id'=>91,'item_container_custo_id'=>$type===3?500:0,'item_container_id'=>$type===3?0:111,'cod_tipo_movto'=>$type,'despesa_container_id'=>0,'vl_lancto'=>18]];
        return $audit;
    }
    public function testAutomaticDistributionLedgerUsesTheConfirmedSnapshotLinkWhenItsDirectItemIsZero(): void
    {
        [$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$this->distributionAudit()]);
        self::assertTrue($result['audit']['available']);
        $ledger=$logs[3];self::assertSame(0,$ledger->getAfterData()['item_container_id']);
        self::assertSame(111,$ledger->getMetadata()['audit']['itemContainerId']);self::assertSame(5,$ledger->getMetadata()['audit']['versionId']);
    }
    public function testDirectDistributionLedgerKeepsItsPhysicalItemAndDoesNotInventAVersion(): void
    {
        [$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$this->distributionAudit(4)]);
        self::assertTrue($result['audit']['available']);self::assertSame(111,$logs[3]->getMetadata()['audit']['itemContainerId']);
        self::assertArrayNotHasKey('versionId',$logs[3]->getMetadata()['audit']);
    }
    public function testDistributionLedgerRejectsOrphanForeignMixedOrInvalidLinksBeforeEmittingAnySnapshots(): void
    {
        foreach(['orphan','foreign','mixed3','mixed4','zero4','type','negative','snapshot-zero','snapshot-string'] as $case){
            $audit=$this->distributionAudit($case==='mixed4'||$case==='zero4'?4:3);
            switch($case){
                case 'orphan':array_splice($audit['changes'],1,1);break;
                case 'foreign':$audit['changes'][1]['after']['container_id']=18;break;
                case 'mixed3':$audit['changes'][2]['after']['item_container_id']=111;break;
                case 'mixed4':$audit['changes'][2]['after']['item_container_custo_id']=500;break;
                case 'zero4':$audit['changes'][2]['after']['item_container_id']=0;break;
                case 'type':$audit['changes'][2]['after']['cod_tipo_movto']=2;break;
                case 'negative':$audit['changes'][2]['after']['item_container_id']=-1;break;
                case 'snapshot-zero':$audit['changes'][2]['after']['item_container_custo_id']=0;break;
                case 'snapshot-string':$audit['changes'][2]['after']['item_container_custo_id']='500';break;
            }
            [$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);
            self::assertFalse($result['audit']['available'],$case);
            foreach($logs as $log){self::assertSame([],$log->getBeforeData());self::assertSame([],$log->getAfterData());}
        }
    }
    public function testMalformedChangeOperationDoesNotThrowAfterTheApiConfirmedCommit(): void
    {
        $audit=$this->audit();$audit['changes'][0]['operation']=['unexpected'];
        try{[$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);}
        catch(\Throwable $error){self::fail('Resposta confirmada nao pode virar erro por audit invalido: '.$error::class);}
        self::assertSame('succeeded',$result['status']);self::assertFalse($result['audit']['available']);
        self::assertStringContainsString('nao repita',strtolower($result['result']['message']));
        foreach($logs as $log){self::assertSame([],$log->getBeforeData());self::assertSame([],$log->getAfterData());self::assertStringNotContainsString('.failed',$log->getEventType());}
    }
    public function testProcessAuditAcceptsOnlyTheBoundedOptionalRequestIdAndStillRejectsUnknownFields(): void
    {
        foreach(['',str_repeat('a',128)] as $id){$audit=$this->audit();$audit['request_id']=$id;
            [$result]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);self::assertTrue($result['audit']['available']);}
        foreach([str_repeat('a',129),null,123,[],['unsafe']] as $id){$audit=$this->audit();$audit['request_id']=$id;
            [$result,$logs]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);self::assertFalse($result['audit']['available']);
            foreach($logs as $log){self::assertSame([],$log->getBeforeData());self::assertSame([],$log->getAfterData());}}
        $audit=$this->audit();$audit['unknown']='extra';
        [$result]=$this->execute(['success'=>true,'data'=>['type'=>'message','message'=>'Confirmado.'],'audit'=>$audit]);self::assertFalse($result['audit']['available']);
    }

    public function testRollbackNeverImportsPendingOrForgedAudit(): void
    {
        $failure=new \App\Runtime\RuntimeHttpException('ROLLED_BACK','Recusado.',422);
        [$result,$logs]=$this->execute([],'process',$failure);self::assertSame($failure,$result);
        self::assertSame(['version'=>1,'source'=>'runtime-validated-context','containerId'=>17],$logs[0]->getMetadata()['containerContext']??null);
        foreach($logs as $log){self::assertSame([],$log->getBeforeData());self::assertSame([],$log->getAfterData());}
    }
}
