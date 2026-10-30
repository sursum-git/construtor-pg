<?php
namespace App\Tests\Runtime;
use App\Entity\{BuilderEntity,BuilderField,RuntimeTransaction,RuntimeTransactionLog};
use App\Repository\BuilderEntityRepository;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeExecutionContext,RuntimeEntityDefinitionResolver,RuntimeEnvironmentIdentityResolver,PermissionResolver,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RuntimePurchaseExpenseTransportTest extends TestCase
{
    private static function row():array{return RuntimePurchaseExpenseAuditTest::image();}
    private static function data():array{return RuntimePurchaseExpenseAuditTest::data();}
    private static function body(string $op):array{$b=RuntimePurchaseExpenseAuditTest::body($op);$b['audit']['actor']['login']='real-user';return $b;}
    private function service(string $op,array $body,array &$sent,array &$logs):RuntimeApiEntityActionService
    {
        $endpoint=['method'=>'POST','url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','requestContextFields'=>['proc_compra_id'],'bodyTemplate'=>['resource'=>'proc_compra_despesas','operation'=>$op]];
        $api=['responseContract'=>'sursum-programs','authHeaders'=>[],'listEndpoint'=>$endpoint,'listResponse'=>['itemsPath'=>'data'],$op.'Endpoint'=>$endpoint,$op.'Response'=>['itemPath'=>'data']];
        if($op==='get'){$api['detailEndpoint']=$endpoint;$api['detailResponse']=['itemPath'=>'data'];}
        $entity=(new BuilderEntity())->setCode('proc_compra_despesas_progress')->setEntityType('api')->setMetadata(['apiSource'=>$api,'apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros']]);
        foreach(self::data() as $name=>$value){$readonly=!in_array($name,['despesa_id','vl_prev'],true);$type=$name==='vl_prev'?'decimal':(in_array($name,['row_stamp','despesa_descricao'],true)?'string':'integer');$entity->addField((new BuilderField())->setCode($name)->setLabel($name)->setDataType($type)->setRequired($name==='despesa_id')->setPrimaryKey($name==='despesa_proc_compra_id')->setOptions(['readonly'=>$readonly,'api'=>['writePath'=>$readonly?$name:'values.'.$name]]));}
        $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($entity);
        $manager=$this->createStub(EntityManagerInterface::class);$manager->method('persist')->willReturnCallback(static function($item)use(&$logs){if($item instanceof RuntimeTransactionLog)$logs[]=$item;});
        $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturn(true);
        $tx=new RuntimeTransactionService($manager,$permissions,new RuntimeExecutionContext(),$this->createStub(RuntimeEntityDefinitionResolver::class),$this->createStub(RuntimeEnvironmentIdentityResolver::class));
        $current=(new RuntimeTransaction())->setTenantId('default')->setRequestContext(['actor'=>['source'=>'authenticated-session','version'=>1,'username'=>'real-user']]);(new \ReflectionProperty($tx,'current'))->setValue($tx,$current);
        $client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturnCallback(static function($url,$method,$headers,$request)use($body,&$sent){$sent[]=$request;return ['status'=>200,'body'=>$body];});
        return new RuntimeApiEntityActionService($repo,$tx,$client,$permissions);
    }
    private function invoke(string $op,array $body,array $payload,array &$sent,array &$logs):array
    {return $this->service($op,$body,$sent,$logs)->handle('ciw.proc-compra-despesas',$op,['entityCode'=>'proc_compra_despesas_progress','operation'=>$op],$payload);}
    public function testNativeMutationsShareExistingTransportAndImportOnlyAuthenticatedImages():void
    {
        foreach(['create','update','delete'] as $op){$sent=[];$logs=[];$payload=self::data();if($op==='create'){unset($payload['despesa_proc_compra_id'],$payload['row_stamp']);}$payload['_runtime']=['actor'=>['username'=>'forged'],'before'=>['cod_rep'=>999]];
            $this->invoke($op,self::body($op),$payload,$sent,$logs);self::assertCount(1,$sent);self::assertSame(10,$sent[0]['proc_compra_id']);self::assertArrayNotHasKey('item_proc_compra_id',$sent[0]);self::assertArrayNotHasKey('despesa_proc_compra_id',$sent[0]);self::assertArrayNotHasKey('_runtime',$sent[0]);
            if($op!=='delete'){self::assertSame(['despesa_id'=>7,'vl_prev'=>12.5],$sent[0]['values']);}else{self::assertArrayNotHasKey('values',$sent[0]);}
            $native=array_values(array_filter($logs,fn($log)=>$log->getMetadata()['audit']['available']??false));self::assertCount(1,$native);self::assertSame('real-user',$native[0]->getMetadata()['actor']['username']);self::assertSame($op==='create'?[]:self::row(),$native[0]->getBeforeData());self::assertSame($op==='delete'?[]:self::row(),$native[0]->getAfterData());
        }
    }
    public function testNativeGetAliasesKeepTheSelectedItemContext():void
    {
        $sent=[];$logs=[];$result=$this->invoke('get',['success'=>true,'data'=>self::data()],['id'=>101,'despesa_proc_compra_id'=>'101','context'=>['proc_compra_id'=>10]],$sent,$logs);
        self::assertSame(101,$result['despesa_proc_compra_id']);self::assertCount(7,$result);self::assertSame([['resource'=>'proc_compra_despesas','operation'=>'get','id'=>101,'proc_compra_id'=>10]],$sent);
    }
    public function testContradictoryContextAndIdentityNeverReachTheWriter():void
    {
        foreach(['owner','identity','missing-owner','stamp'] as $case){$sent=[];$logs=[];$payload=self::data();if($case==='owner'){$payload['context']=['proc_compra_id'=>20];}if($case==='identity')$payload['id']=102;if($case==='missing-owner')unset($payload['proc_compra_id']);if($case==='stamp')$payload['row_stamp']='invalid';try{$this->invoke('update',self::body('update'),$payload,$sent,$logs);self::fail($case);}catch(RuntimeHttpException $e){self::assertSame(422,$e->getStatusCode());}self::assertSame([],$sent);}
    }
    public function testCommittedForgedImagesWarnWithoutImportOrSecondPost():void
    {
        $sent=[];$logs=[];$body=self::body('update');$body['audit']['actor']['login']='forged';$result=$this->invoke('update',$body,self::data(),$sent,$logs);
        self::assertCount(1,$sent);self::assertSame('warning',$result['effects'][0]['type']);foreach($logs as $log){self::assertFalse($log->getMetadata()['audit']['available']??false);self::assertSame([],$log->getBeforeData());}
    }
}
