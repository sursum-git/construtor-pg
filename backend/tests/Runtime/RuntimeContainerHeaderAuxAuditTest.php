<?php
namespace App\Tests\Runtime;

use App\Runtime\RuntimeContainerAudit;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerHeaderAuxAuditTest extends TestCase
{
    private function audit(): array
    {
        $rows = [
            'item_container_custo_despesas'=>['versao_id'=>5,'despesa_id'=>3,'vl_prev'=>null],
            'item_container_custo_nfs'=>['versao_id'=>5,'documento'=>'000123','serie'=>'1','estab'=>'101','nat_operacao'=>'31201M','cod_emitente'=>12,'item_container_custo_id'=>0],
            'item_container_custo_pgtos'=>['versao_id'=>5,'num_id_tit_ap'=>8,'vl_real'=>0],
            'item_container_custo_descontos'=>['versao_id'=>5,'desconto_importacao_id'=>9,'log_custo'=>0],
        ];
        $keys = ['[5,3]','[5,"000123","1","101","31201M",12]','[5,8]','[5,9]'];
        $changes = [];
        foreach ($rows as $resource=>$row) {
            $changes[] = ['resource'=>$resource,'operation'=>'delete','recordId'=>array_shift($keys),'before'=>['container_id'=>17]+$row,'after'=>[]];
        }
        $changes[] = ['resource'=>'versoes_item_custo_container','operation'=>'delete','recordId'=>5,'before'=>['container_id'=>17,'versao_id'=>5],'after'=>[]];
        return ['version'=>1,'source'=>'progress-transaction','resource'=>'containers','operation'=>'delete','recordId'=>17,'before'=>['container_id'=>17],'after'=>[],'changes'=>$changes];
    }

    private function inspect(array $audit, string $operation='delete'): array
    {
        return RuntimeContainerAudit::inspect([
            'entityCode'=>'container_painel_progress','primaryKey'=>'container_id',
            'apiBinding'=>['sourceCode'=>'motor_progress_container_cabecalho'],
            'apiSource'=>['responseContract'=>'sursum-programs'],
        ], ['method'=>'POST','url'=>'/SursumPrograms/containers-cabecalho','bodyTemplate'=>['resource'=>'containers','operation'=>$operation]],
            ['success'=>true,'audit'=>$audit], $operation, 17, 17);
    }

    public function testHeaderDeleteImportsAllFourAuxiliaryDeletesOnlyWithTheSameConfirmedDeletedVersion(): void
    {
        foreach ([false,true] as $reverse) {
            $audit=$this->audit();if($reverse){$audit['changes']=array_reverse($audit['changes']);}
            $result=$this->inspect($audit);
            self::assertTrue($result['metadata']['available']);self::assertCount(5,$result['events']);
            foreach($result['events'] as $event){
                self::assertSame('delete',$event['metadata']['operation']);self::assertSame(17,$event['metadata']['containerId']);
                self::assertSame(5,$event['metadata']['versionId']);self::assertSame([],$event['after']);
            }
        }
    }

    public function testHeaderAuxiliaryDeletesRejectForeignContainerWrongCompositeKeyOrUnprovenVersionAsAWhole(): void
    {
        foreach(range(0,3) as $index){foreach(['foreign-child','wrong-key','foreign-version','missing-version','unproven-version','version-update','extra'] as $case){
            $audit=$this->audit();
            switch($case){
                case 'foreign-child':$audit['changes'][$index]['before']['container_id']=18;break;
                case 'wrong-key':$keys=json_decode($audit['changes'][$index]['recordId'],true);$keys[1]=999;$audit['changes'][$index]['recordId']=json_encode($keys);break;
                case 'foreign-version':$audit['changes'][4]['before']['container_id']=18;break;
                case 'missing-version':array_pop($audit['changes']);break;
                case 'unproven-version':$audit['changes'][$index]['before']['versao_id']=6;$keys=json_decode($audit['changes'][$index]['recordId'],true);$keys[0]=6;$audit['changes'][$index]['recordId']=json_encode($keys);break;
                case 'version-update':$audit['changes'][4]['operation']='update';$audit['changes'][4]['after']=$audit['changes'][4]['before'];break;
                case 'extra':$audit['changes'][$index]['before']['unknown']='forged';break;
            }
            $result=$this->inspect($audit);self::assertFalse($result['metadata']['available'],$case.' resource '.$index);
            self::assertSame([],$result['before']);self::assertSame([],$result['after']);self::assertSame([],$result['events']);
        }}
    }

    public function testHeaderAuxiliariesCannotCreateOrUpdateAndCannotBeImportedThroughNonDeleteHeaderCommands(): void
    {
        foreach(range(0,3) as $index){foreach(['create','update'] as $operation){
            $audit=$this->audit();$audit['changes'][$index]['operation']=$operation;$audit['changes'][$index]['after']=$audit['changes'][$index]['before'];
            if($operation==='create'){$audit['changes'][$index]['before']=[];}
            $result=$this->inspect($audit);self::assertFalse($result['metadata']['available']);self::assertSame([],$result['events']);
        }}
        foreach(['create','update'] as $operation){
            $audit=$this->audit();$audit['operation']=$operation;$audit['after']=$audit['before'];if($operation==='create'){$audit['before']=[];}
            $result=$this->inspect($audit,$operation);self::assertFalse($result['metadata']['available']);self::assertSame([],$result['events']);
        }
    }
}
