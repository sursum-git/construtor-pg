<?php
namespace App\Tests\Runtime;
use App\Entity\{BuilderEntity,BuilderField,RuntimeTransaction,RuntimeTransactionLog};
use App\Repository\BuilderEntityRepository;
use App\Runtime\{RuntimeContainerAudit,RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeExecutionContext,RuntimeEntityDefinitionResolver,RuntimeEnvironmentIdentityResolver,PermissionResolver};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
final class RuntimeContainerPaymentsAuditTest extends TestCase
{
    private function key(int $cid=17,string $estab='505',int $title=7): string
    { $part=static fn(string $s):string=>'V'.mb_strlen($s,'UTF-8').':'.$s; return 'p_'.strtoupper(hash('sha256',$part((string)$cid).$part($estab).$part((string)$title))); }
    private function definition(): array
    { return ['entityCode'=>'container_pagamentos_progress','primaryKey'=>'pagamento_key','apiBinding'=>['sourceCode'=>'motor_progress_container_pagamentos'],'apiSource'=>['responseContract'=>'sursum-programs']]; }
    private function endpoint(string $op): array
    { return ['method'=>'POST','url'=>'/med/web/SursumPrograms/containers-pagamentos','bodyTemplate'=>['resource'=>'container-pagamentos','operation'=>$op]]; }
    private function audit(string $op='create'): array
    {
        $link=['container_id'=>17,'num_id_tit_ap'=>7,'vl_pago_em_dolar'=>0,'vl_pago_em_real'=>0,'vl_aprop_em_dolar'=>0,'vl_cotacao_dolar'=>0,'dt_pagto'=>null,'cod_estab'=>'505','pagamento_key'=>$this->key()];
        return ['version'=>1,'source'=>'progress-transaction','resource'=>'container-pagamentos','operation'=>$op,'recordId'=>$this->key(),'container_id'=>17,'before'=>$op==='create'?[]:$link,'after'=>$op==='delete'?[]:$link,
            'master'=>['before'=>['container_id'=>17,'log_versao_custo_desatualizada'=>false],'after'=>['container_id'=>17,'log_versao_custo_desatualizada'=>true]],
            'changes'=>[['resource'=>'cta_corren_fornec_imp','operation'=>'create','recordId'=>91,'before'=>[],
                'after'=>['container_id'=>17,'cta_corren_fornec_imp_id'=>91,'fornecedor_id'=>8,'cod_tipo_movto'=>5,'cod_acao'=>3,'item_proc_compra_id'=>11,'item_container_id'=>999,'num_id_tit_ap'=>7,'vl_lancto'=>15,'data'=>'2026-10-17','historico'=>'global history','login'=>'trusted-user','data_hora'=>'2026-10-17T12:00:00','transacao_id'=>0]]],
            'actor'=>['login'=>'forged-response-user']];
    }
    private function inspect(array $audit,string $op='create',array $definition=[],array $endpoint=[],array $body=[]): array
    { return RuntimeContainerAudit::inspect(array_replace_recursive($this->definition(),$definition),array_replace($this->endpoint($op),$endpoint),array_replace(['success'=>true,'audit'=>$audit],$body),$op,$this->key(),17); }
    public function testPaymentCreateDeleteValidateOpaqueIdentityAndPropagateOnlyVerifiedPaymentKeyToAllEvents(): void
    {
        foreach(['create','delete'] as $op){$audit=$this->audit($op);$r=$this->inspect($audit,$op);
            self::assertTrue($r['metadata']['available']);self::assertSame('container_payment_links',$r['metadata']['resource']);self::assertSame($this->key(),$r['metadata']['recordId']);self::assertSame($this->key(),$r['metadata']['paymentKey']);
            self::assertSame($audit['before'],$r['before']);self::assertSame($audit['after'],$r['after']);self::assertCount(2,$r['events']);
            foreach($r['events'] as $event){self::assertSame($this->key(),$event['metadata']['paymentKey']);self::assertSame(17,$event['metadata']['containerId']);}
            self::assertSame(999,$r['events'][1]['after']['item_container_id'],'Global historical item may be absent/outside current CID');
        }
        $audit=$this->audit();$audit['changes'][0]['after']['cod_tipo_movto']=4;$audit['changes'][0]['after']['item_container_id']=0;$audit['changes'][0]['after']['num_id_tit_ap']=88;
        $audit['changes'][]=['resource'=>'itens_container','operation'=>'update','recordId'=>71,'before'=>['container_id'=>17,'item_container_id'=>71,'perc_ipi'=>1],'after'=>['container_id'=>17,'item_container_id'=>71,'perc_ipi'=>2]];
        $audit['changes'][]=['resource'=>'calcs_imp_container','operation'=>'create','recordId'=>4,'before'=>[],'after'=>['container_id'=>17,'calc_imp_container_id'=>4]];
        $audit['changes'][]=['resource'=>'despesas_container','operation'=>'update','recordId'=>81,'before'=>['container_id'=>17,'despesa_container_id'=>81,'vl_prev'=>null],'after'=>['container_id'=>17,'despesa_container_id'=>81,'vl_prev'=>0]];
        self::assertCount(5,$this->inspect($audit)['events']);
    }
    public function testOpaqueKeyUsesCharacterLengthsAndStrictCaseWithoutChangingAnyNumericContract(): void
    {
        $audit=$this->audit();$audit['after']['cod_estab']='Áé';$audit['recordId']=$audit['after']['pagamento_key']=$this->key(17,'Áé');
        $r=RuntimeContainerAudit::inspect($this->definition(),$this->endpoint('create'),['success'=>true,'audit'=>$audit],'create',$this->key(17,'Áé'),17);
        self::assertTrue($r['metadata']['available']);
        $audit['after']['pagamento_key']=strtolower($audit['after']['pagamento_key']);self::assertFalse(RuntimeContainerAudit::inspect($this->definition(),$this->endpoint('create'),['success'=>true,'audit'=>$audit],'create',$audit['recordId'],17)['metadata']['available']);
    }
    public function testPaymentRejectsEveryMalformedOrForgedEnvelopeWithoutPartialEvents(): void
    {
        foreach(['key','hash','key-field','title','title-string','cid','estab','missing-field','extra-field','master','master-missing','ledger-type10','ledger-type6','ledger-operation','ledger-item','ledger-purchase','ledger-supplier','ledger-title','ledger-transaction','child-cid','child-resource','change-extra','source','success','actor-shape'] as $case){
            $a=$this->audit();$body=[];
            switch($case){
                case 'key':$a['recordId']='free-text';break;case 'hash':$a['recordId']=$a['after']['pagamento_key']='p_'.str_repeat('A',64);break;
                case 'key-field':$a['after']['pagamento_key']=$this->key(18);break;case 'title':$a['after']['num_id_tit_ap']=8;break;case 'title-string':$a['after']['num_id_tit_ap']='7';break;
                case 'cid':$a['after']['container_id']=18;break;case 'estab':$a['after']['cod_estab']='501';break;case 'missing-field':unset($a['after']['dt_pagto']);break;case 'extra-field':$a['after']['source']='forged';break;
                case 'master':$a['master']['after']['container_id']=18;break;case 'master-missing':unset($a['master']);break;
                case 'ledger-type10':$a['changes'][0]['after']['cod_tipo_movto']=10;break;case 'ledger-type6':$a['changes'][0]['after']['cod_tipo_movto']=6;break;
                case 'ledger-operation':$a['changes'][0]['operation']='update';$a['changes'][0]['before']=$a['changes'][0]['after'];break;
                case 'ledger-item':$a['changes'][0]['after']['item_container_id']=0;break;case 'ledger-purchase':$a['changes'][0]['after']['item_proc_compra_id']=0;break;case 'ledger-supplier':$a['changes'][0]['after']['fornecedor_id']=0;break;
                case 'ledger-title':$a['changes'][0]['after']['num_id_tit_ap']=0;break;case 'ledger-transaction':$a['changes'][0]['after']['transacao_id']=7;break;
                case 'child-cid':$a['changes'][0]['after']['container_id']=18;break;case 'child-resource':$a['changes'][0]['resource']='container_payment_links';break;
                case 'change-extra':$a['changes'][0]['paymentKey']=$this->key();break;case 'source':$a['source']='client';break;case 'success':$body['success']=false;break;case 'actor-shape':$a['actor']['source']='claimed';break;
            }
            $r=$this->inspect($a,'create',[],[],$body);self::assertFalse($r['metadata']['available'],$case);self::assertSame([],$r['events'],$case);
        }
        foreach([['entityCode'=>'other'],['primaryKey'=>'num_id_tit_ap'],['apiBinding'=>['sourceCode'=>'wrong']],['apiSource'=>['responseContract'=>'generic']]] as $d)self::assertFalse($this->inspect($this->audit(),'create',$d)['metadata']['available']);
        foreach([['url'=>'/SursumPrograms/other'],['method'=>'GET'],['bodyTemplate'=>['resource'=>'container-pagamentos','operation'=>'create','policy'=>'forged']]] as $e)self::assertFalse($this->inspect($this->audit(),'create',[],$e)['metadata']['available']);
        $a=$this->audit();$a['operation']='update';self::assertFalse($this->inspect($a,'update')['metadata']['available']);
    }
    public function testRawHeaderPaymentDeleteRetainsItsOriginalNumericPhysicalIdentity(): void
    {
        $a=['version'=>1,'source'=>'progress-transaction','resource'=>'containers','operation'=>'delete','recordId'=>17,'before'=>['container_id'=>17],'after'=>[],'changes'=>[['resource'=>'pagtos_container','operation'=>'delete','recordId'=>7,'before'=>['container_id'=>17,'num_id_tit_ap'=>7],'after'=>[]]]];
        $d=['entityCode'=>'container_painel_progress','primaryKey'=>'container_id','apiBinding'=>['sourceCode'=>'motor_progress_container_cabecalho'],'apiSource'=>['responseContract'=>'sursum-programs']];
        $e=['method'=>'POST','url'=>'/SursumPrograms/containers-cabecalho','bodyTemplate'=>['resource'=>'containers','operation'=>'delete']];
        self::assertTrue(RuntimeContainerAudit::inspect($d,$e,['success'=>true,'audit'=>$a],'delete',17)['metadata']['available']);
        $a['changes'][0]['recordId']=$this->key();self::assertFalse(RuntimeContainerAudit::inspect($d,$e,['success'=>true,'audit'=>$a],'delete',17)['metadata']['available']);
    }
    private function imported(string $op): array
    {
        $a=$this->audit($op);$endpoint=$this->endpoint($op)+['requestContextFields'=>['container_id']];
        $api=['responseContract'=>'sursum-programs','authHeaders'=>[],'listEndpoint'=>$endpoint,'listResponse'=>['itemsPath'=>'data'],$op.'Endpoint'=>$endpoint,$op.'Response'=>['itemPath'=>'data']];
        $entity=(new BuilderEntity())->setCode('container_pagamentos_progress')->setEntityType('api')->setMetadata(['apiSource'=>$api,'apiBinding'=>['sourceCode'=>'motor_progress_container_pagamentos']]);
        foreach(['pagamento_key'=>'string','container_id'=>'integer','num_id_tit_ap'=>'integer'] as $code=>$type){$entity->addField((new BuilderField())->setCode($code)->setLabel($code)->setDataType($type)->setPrimaryKey($code==='pagamento_key')->setOptions($code==='container_id'?['readonly'=>true]:[]));}
        $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($entity);
        $logs=[];$manager=$this->createStub(EntityManagerInterface::class);$manager->method('persist')->willReturnCallback(static function($item)use(&$logs){if($item instanceof RuntimeTransactionLog)$logs[]=$item;});
        $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturn(true);
        $transactions=new RuntimeTransactionService($manager,$permissions,new RuntimeExecutionContext(),$this->createStub(RuntimeEntityDefinitionResolver::class),$this->createStub(RuntimeEnvironmentIdentityResolver::class));
        $tx=(new RuntimeTransaction())->setTenantId('tenant-a')->setRequestContext(['actor'=>['source'=>'authenticated-session','version'=>1,'username'=>'real-user']]);(new \ReflectionProperty($transactions,'current'))->setValue($transactions,$tx);
        $client=$this->createMock(RuntimeExternalJsonClient::class);$client->expects(self::once())->method('request')->willReturn(['status'=>200,'body'=>['success'=>true,'data'=>$a[$op==='create'?'after':'before'],'audit'=>$a]]);
        $service=new RuntimeApiEntityActionService($repo,$transactions,$client,$permissions);
        $result=$service->handle('ciw.container-pagamentos',$op,['entityCode'=>'container_pagamentos_progress','operation'=>$op],['id'=>$this->key(),'record'=>$op==='create'?['num_id_tit_ap'=>7]:['pagamento_key'=>$this->key()],'context'=>['container_id'=>17]]);
        return [$result,$logs,$tx];
    }
    public function testNativeCreateDeleteImportFullSnapshotsWithPhpSessionActorAndOpaqueTransactionIdentity(): void
    {
        foreach(['create','delete'] as $op){[$result,$logs,$tx]=$this->imported($op);self::assertSame($this->key(),$tx->getRecordId());
            $confirmed=array_values(array_filter($logs,fn($l)=>($l->getMetadata()['audit']['available']??false)===true));self::assertCount(3,$confirmed);
            foreach($confirmed as $log){self::assertSame('real-user',$log->getMetadata()['actor']['username']);self::assertSame($this->key(),$log->getMetadata()['audit']['paymentKey']);}
            self::assertStringNotContainsString('forged-response-user',json_encode(array_map(fn($l)=>$l->getMetadata(),$logs)));
            $main=$confirmed[2];self::assertSame('container_payment_links',$main->getMetadata()['audit']['resource']);self::assertSame($this->audit($op)['before'],$main->getBeforeData());self::assertSame($this->audit($op)['after'],$main->getAfterData());
        }
    }
}
