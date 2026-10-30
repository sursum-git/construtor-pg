<?php
namespace App\Tests\Runtime;
use App\Runtime\RuntimeContainerAudit;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerItemsAuditTest extends TestCase
{
    private function audit(string $operation='update'): array
    {
        $before=['container_id'=>17,'item_container_id'=>71,'item_proc_compra_id'=>11,'qt_alocada'=>1,'qt_confirm_emb'=>0];
        $after=array_replace($before,['qt_alocada'=>2]);
        return ['version'=>1,'source'=>'progress-transaction','resource'=>'container-itens','operation'=>$operation,'recordId'=>71,'container_id'=>17,
            'before'=>$operation==='create'?[]:$before,'after'=>$operation==='delete'?[]:$after,
            'master'=>['before'=>['container_id'=>17,'log_versao_custo_desatualizada'=>false],'after'=>['container_id'=>17,'log_versao_custo_desatualizada'=>true]],
            'changes'=>[['resource'=>'cta_corren_fornec_imp','operation'=>'create','recordId'=>91,'before'=>[],
                'after'=>['container_id'=>17,'cta_corren_fornec_imp_id'=>91,'item_container_id'=>71,'item_proc_compra_id'=>11,'fornecedor_id'=>7,'cod_tipo_movto'=>10,'cod_acao'=>2,'vl_lancto'=>1.25,'login'=>'real-user','transacao_id'=>0]]]];
    }
    private function inspect(array $audit, string $operation='update', array $overrides=[]): array
    {
        $definition=array_replace_recursive(['entityCode'=>'container_itens_progress','primaryKey'=>'item_container_id','apiBinding'=>['sourceCode'=>'motor_progress_container_itens'],'apiSource'=>['responseContract'=>'sursum-programs']],$overrides);
        return RuntimeContainerAudit::inspect($definition,['method'=>'POST','url'=>'/SursumPrograms/containers-itens','bodyTemplate'=>['resource'=>'container-itens','operation'=>$operation]],['success'=>true,'audit'=>$audit],$operation,71,17);
    }
    public function testItemSnapshotsAndDerivedMovementsPreserveRecordParentAndBeforeAfter(): void
    {
        foreach(['create','update','delete'] as $operation){
            $audit=$this->audit($operation);$result=$this->inspect($audit,$operation);
            self::assertTrue($result['metadata']['available']);self::assertSame('itens_container',$result['metadata']['resource']);
            self::assertSame(71,$result['metadata']['itemContainerId']);self::assertSame(17,$result['metadata']['containerId']);
            self::assertSame($audit['before'],$result['before']);self::assertSame($audit['after'],$result['after']);
            self::assertCount(2,$result['events']);self::assertSame('containers',$result['events'][0]['metadata']['resource']);
            self::assertSame(1.25,$result['events'][1]['after']['vl_lancto']);
        }
    }
    public function testInvalidOrForeignSnapshotsRejectTheWholeEnvelope(): void
    {
        foreach(['parent','record','purchase-change','master','missing-master','resource','ledger-type','ledger-update','ledger-item','ledger-parent','ledger-purchase','extra'] as $case){
            $audit=$this->audit();switch($case){
                case 'parent':$audit['after']['container_id']=18;break;
                case 'record':$audit['recordId']=72;break;
                case 'purchase-change':$audit['after']['item_proc_compra_id']=12;break;
                case 'master':$audit['master']['after']['container_id']=18;break;
                case 'missing-master':unset($audit['master']);break;
                case 'resource':$audit['changes'][0]['resource']='user';break;
                case 'ledger-type':$audit['changes'][0]['after']['cod_tipo_movto']=3;break;
                case 'ledger-update':$audit['changes'][0]['operation']='update';$audit['changes'][0]['before']=$audit['changes'][0]['after'];break;
                case 'ledger-item':$audit['changes'][0]['after']['item_container_id']=0;break;
                case 'ledger-parent':$audit['changes'][0]['after']['container_id']=18;break;
                case 'ledger-purchase':$audit['changes'][0]['after']['item_proc_compra_id']=0;break;
                case 'extra':$audit['changes'][0]['after']['secret']='bad';break;
            }
            $result=$this->inspect($audit);self::assertFalse($result['metadata']['available'],$case);self::assertSame([],$result['events'],$case);
        }
        self::assertFalse($this->inspect($this->audit(),'update',['apiBinding'=>['sourceCode'=>'untrusted']])['metadata']['available']);
    }
    public function testOrderReallocationMovementHasNoContainerItemFkAndFiscalChildrenAreAllowed(): void
    {
        $audit=$this->audit();$audit['changes'][0]['after']['cod_tipo_movto']=4;$audit['changes'][0]['after']['item_container_id']=0;
        $audit['changes'][]=['resource'=>'despesas_container','operation'=>'update','recordId'=>81,'before'=>['container_id'=>17,'despesa_container_id'=>81,'vl_prev'=>null],'after'=>['container_id'=>17,'despesa_container_id'=>81,'vl_prev'=>0]];
        self::assertTrue($this->inspect($audit)['metadata']['available']);
        $audit['changes'][]=['resource'=>'itens_container','operation'=>'update','recordId'=>71,'before'=>$audit['before'],'after'=>$audit['after']];
        self::assertCount(3,$this->inspect($audit)['events']);
        $audit['changes'][2]['after']['qt_alocada']=99;self::assertFalse($this->inspect($audit)['metadata']['available']);
    }
}
