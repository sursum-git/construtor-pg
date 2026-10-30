<?php
namespace App\Tests\Runtime;

use App\Entity\{RuntimeTransaction,RuntimeTransactionLog};
use App\Repository\BuilderEntityRepository;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeExecutionContext,RuntimeEntityDefinitionResolver,RuntimeEnvironmentIdentityResolver,PermissionResolver,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerAllocationProcessTest extends TestCase
{
    private function invoke(array $body, array &$sent, array &$logs, array $payload=[], bool $allowed=true, string $actor='real', bool $binding=true, ?string $lookup=null): array
    {
        $em=$this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function($row) use (&$logs) { if ($row instanceof RuntimeTransactionLog) $logs[]=$row; });
        $permissions=$this->createStub(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn($allowed);
        $tx=new RuntimeTransactionService($em,$permissions,new RuntimeExecutionContext(),$this->createStub(RuntimeEntityDefinitionResolver::class),$this->createStub(RuntimeEnvironmentIdentityResolver::class));
        (new \ReflectionProperty($tx,'current'))->setValue($tx,(new RuntimeTransaction())->setTenantId('default')->setRequestContext(['actor'=>['source'=>'authenticated-session','version'=>1,'username'=>$actor]]));
        $client=$this->createStub(RuntimeExternalJsonClient::class);
        $client->method('request')->willReturnCallback(static function($url,$method,$headers,$request) use (&$sent,$body) { $sent[]=$request; return ['status'=>200,'body'=>$body]; });
        $service=new RuntimeApiEntityActionService($this->createStub(BuilderEntityRepository::class),$tx,$client,$permissions);
        $d=RuntimeContainerAllocationAuditTest::definition(); $e=RuntimeContainerAllocationAuditTest::endpoint();
        if (!$binding) $e['bodyTemplate']['resource']='other';
        $d['apiSource']['authHeaders']=[];
        $d['fields']=['container_id'=>['code'=>'container_id','label'=>'Container','dataType'=>'integer','required'=>true], 'item_ids'=>['code'=>'item_ids','label'=>'Itens','dataType'=>'json','required'=>true,'collection'=>['itemType'=>'integer']]];
        $d['contextFields']=[['field'=>'container_id','required'=>true,'readonly'=>true]];
        $d['apiSource']['operations']=[['code'=>'allocate','type'=>'custom','method'=>'POST','path'=>$e['url'],'allowProcess'=>true,'requestContextFields'=>['container_id'],'bodyTemplate'=>$e['bodyTemplate'],'itemPath'=>'data']];
        if ($lookup !== null) {
            $d['fields']['item_ids']['optionsSource']=['operationCode'=>'eligible','valueField'=>'id','textField'=>'label'];
            $d['apiSource']['operations'][]=['code'=>'eligible','type'=>'list','method'=>'POST','path'=>$e['url'],'requestContextFields'=>$lookup==='context'?[]:['container_id'],'bodyTemplate'=>['resource'=>'itens-alocacao-disponiveis','operation'=>$lookup==='write'?'allocate':'read'],'itemsPath'=>'data'];
            return (new \ReflectionMethod($service,'options'))->invoke($service,$d,['operation'=>'options','fieldCode'=>'item_ids','processParameterOptions'=>true,'contextFields'=>[['field'=>'container_id']]],['context'=>['container_id'=>17]]);
        }
        return (new \ReflectionMethod($service,'process'))->invoke($service,$d,['operation'=>'process','allowProcess'=>true,'permissionPrefix'=>'op1025','contextFields'=>[['field'=>'container_id']],'processOptions'=>['operationCode'=>'allocate','parameterFields'=>['item_ids']]],$payload+['context'=>['container_id'=>17],'parameters'=>['item_ids'=>[11,12]]]);
    }

    public function testLookupCannotDispatchMislabelledWriteOrDivergentContext(): void
    {
        foreach (['write','context'] as $case) {
            $sent=[]; $logs=[];
            try { $this->invoke(['success'=>true,'data'=>[]],$sent,$logs,[],true,'real',true,$case); self::fail($case); }
            catch (RuntimeHttpException $e) { self::assertSame(422,$e->getStatusCode()); }
            self::assertSame([],$sent);
        }
    }

    public function testRootAndEffectsUseTrustedActorAndBeforeAfter(): void
    {
        $sent=[]; $logs=[]; $r=$this->invoke(RuntimeContainerAllocationAuditTest::body(),$sent,$logs);
        self::assertTrue($r['audit']['available']??false);
        self::assertSame([['resource'=>'container-alocacoes','operation'=>'allocate','item_ids'=>[11,12],'container_id'=>17]],$sent);
        $confirmed=array_values(array_filter($logs,fn($row)=>$row->getMetadata()['audit']['available']??false));
        self::assertCount(2,$confirmed);
        self::assertFalse($confirmed[0]->getBeforeData()['log_versao_custo_desatualizada']);
        self::assertTrue($confirmed[0]->getAfterData()['log_versao_custo_desatualizada']);
        self::assertSame(40,$confirmed[1]->getAfterData()['qt_alocada']);
        self::assertSame('real',$confirmed[1]->getMetadata()['actor']['username']);
    }

    public function testPermissionBindingIdentityAndBatchLimitFailBeforeProgress(): void
    {
        foreach (['permission','binding','identity','limit','quantity','owner'] as $case) {
            $sent=[]; $logs=[];
            $payload=match($case) {
                'limit'=>['parameters'=>['item_ids'=>range(1,26)]],
                'quantity'=>['parameters'=>['item_ids'=>[11,12],'qt_alocada'=>1]],
                'owner'=>['context'=>['container_id'=>17,'proc_compra_id'=>1]], default=>[]
            };
            try { $this->invoke(RuntimeContainerAllocationAuditTest::body(),$sent,$logs,$payload,$case!=='permission',$case==='identity'?'':'real',$case!=='binding'); self::fail($case); }
            catch (RuntimeHttpException $e) { self::assertContains($e->getStatusCode(),[401,403,422]); }
            self::assertSame([],$sent,$case);
        }
    }

    public function testMissingCommitIsUnconfirmedAndCommittedBadProofNeverSuggestsRetry(): void
    {
        foreach (['commit','actor','presentation','afterCommit'] as $case) {
            $b=RuntimeContainerAllocationAuditTest::body(); $sent=[]; $logs=[];
            if ($case==='commit') unset($b['committed']);
            elseif ($case==='afterCommit') { $b['success']=false; $b['error']=['code'=>'OUTPUT_FAILED']; }
            elseif ($case==='actor') $b['audit']['actor']['login']='fake';
            else $b['data']=['type'=>'script','script'=>'bad'];
            try { $r=$this->invoke($b,$sent,$logs); self::assertNotSame('commit',$case); self::assertStringContainsString('Nao repita',$r['result']['message']); }
            catch (RuntimeHttpException $e) { self::assertSame('commit',$case); self::assertSame(502,$e->getStatusCode()); }
            self::assertCount(1,$sent);
        }
    }
}
