<?php
namespace App\Tests\Runtime;
use App\Entity\{BuilderEntity,BuilderField,RuntimeTransaction,RuntimeTransactionLog};
use App\Repository\BuilderEntityRepository;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeExecutionContext,RuntimeEntityDefinitionResolver,RuntimeEnvironmentIdentityResolver,PermissionResolver,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/RuntimePurchaseOrderAuditTest.php';
final class RuntimePurchaseOrderTransportTest extends TestCase
{
    private function service(string $op,array $body,array &$sent,array &$logs,bool $badPresentationMapping=false): RuntimeApiEntityActionService
    {
        $d=RuntimePurchaseOrderAuditTest::definition();$endpoint=RuntimePurchaseOrderAuditTest::endpoint($op);
        $api=['responseContract'=>'sursum-programs','authHeaders'=>[],'listEndpoint'=>$endpoint,'listResponse'=>['itemsPath'=>'data'],$op.'Endpoint'=>$endpoint,$op.'Response'=>['itemPath'=>'data']];
        if($op==='get'){$api['detailEndpoint']=$endpoint;$api['detailResponse']=['itemPath'=>'data'];}
        $entity=(new BuilderEntity())->setCode($d['entityCode'])->setEntityType('api')->setMetadata(['apiSource'=>$api,'apiBinding'=>$d['apiBinding']]);
        foreach(RuntimePurchaseOrderAuditTest::data() as $name=>$v){$type=match($name){'proc_compra_id','empresa_id','fornecedor_id','qt_container','cod_situacao','qt_dias_translado'=>'integer','cotacao_prevista'=>'decimal','dt_registro','dt_limite'=>'date','log_facilid_contato_fornec','log_considerar_previsao'=>'boolean',default=>'string'};$readonly=in_array($name,['proc_compra_id','empresa_id','qt_container','cotacao_prevista','row_stamp'],true);
            $apiField=['writePath'=>$readonly?$name:'values.'.$name];if($badPresentationMapping&&$name==='observacao')$apiField['collection']='integer';
            $entity->addField((new BuilderField())->setCode($name)->setLabel($name)->setDataType($type)->setPrimaryKey($name==='proc_compra_id')->setOptions(['readonly'=>$readonly,'api'=>$apiField]));}
        $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($entity);
        $manager=$this->createStub(EntityManagerInterface::class);$manager->method('persist')->willReturnCallback(static function($item)use(&$logs){if($item instanceof RuntimeTransactionLog)$logs[]=$item;});
        $p=$this->createStub(PermissionResolver::class);$p->method('hasPermission')->willReturn(true);
        $tx=new RuntimeTransactionService($manager,$p,new RuntimeExecutionContext(),$this->createStub(RuntimeEntityDefinitionResolver::class),$this->createStub(RuntimeEnvironmentIdentityResolver::class));
        $current=(new RuntimeTransaction())->setTenantId('a')->setRequestContext(['actor'=>['source'=>'authenticated-session','version'=>1,'username'=>'real-user']]);(new \ReflectionProperty($tx,'current'))->setValue($tx,$current);
        $client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturnCallback(static function($url,$method,$headers,$request)use($body,&$sent){$sent[]=$request;return ['status'=>200,'body'=>$body];});
        return new RuntimeApiEntityActionService($repo,$tx,$client,$p);
    }
    private function invoke(RuntimeApiEntityActionService $s,string $op,array $record,mixed $id=null): array
    { return $s->handle('ciw.processos-compra-med',$op,['entityCode'=>'procs_compra_med_progress','operation'=>$op],['record'=>$record]+($id===null?[]:['id'=>$id])); }
    public function testCreateUpdateDeleteUseReadonlyRootStampAndNativeFullAuditImages(): void
    {
        foreach(['create','update','delete'] as $op){$sent=[];$logs=[];$body=RuntimePurchaseOrderAuditTest::body($op);if($op==='update')$body['audit']['changes']=[['resource'=>'cta_corren_fornec_imp','operation'=>'create','recordId'=>91,'before'=>[],'after'=>RuntimePurchaseOrderAuditTest::ledger(91,18,5)]];
            $service=$this->service($op,$body,$sent,$logs);$record=RuntimePurchaseOrderAuditTest::data();$record['empresa_id']=999;$record['qt_container']=77;$record['cotacao_prevista']=123.45;
            if($op==='create'){$record['proc_compra_id']=null;$record['row_stamp']='';}
            $this->invoke($service,$op,$record,$op==='create'?null:'17');self::assertCount(1,$sent);$request=$sent[0];
            if($op==='create'){self::assertArrayNotHasKey('row_stamp',$request);self::assertArrayNotHasKey('proc_compra_id',$request);}else{self::assertSame(str_repeat('A',64),$request['row_stamp']);self::assertSame(17,$request['id']);self::assertSame(17,$request['proc_compra_id']);}
            if($op==='delete')self::assertArrayNotHasKey('values',$request);else{foreach(['empresa_id','qt_container','cotacao_prevista','row_stamp','proc_compra_id']as $name)self::assertArrayNotHasKey($name,$request['values']);self::assertSame('Observação',$request['values']['observacao']);}
            $confirmed=array_values(array_filter($logs,static fn($l)=>($l->getMetadata()['audit']['available']??false)===true));self::assertCount($op==='update'?2:1,$confirmed);$main=end($confirmed);self::assertSame($body['audit']['before'],$main->getBeforeData());self::assertSame($body['audit']['after'],$main->getAfterData());foreach($confirmed as $log)self::assertSame('real-user',$log->getMetadata()['actor']['username']);
            if($op==='update'){self::assertSame(18,$confirmed[0]->getMetadata()['audit']['procCompraId']);self::assertSame(17,$confirmed[0]->getMetadata()['audit']['originProcCompraId']);}
        }
    }
    public function testBadClientIdentityOrStampNeverReachesTheExternalWriter(): void
    {
        foreach(['create-id','create-stamp','update-stamp-missing','update-stamp-lower','update-id-alias','delete-stamp','delete-id-fraction']as $case){$op=str_starts_with($case,'create')?'create':(str_starts_with($case,'update')?'update':'delete');$sent=[];$logs=[];$s=$this->service($op,RuntimePurchaseOrderAuditTest::body($op),$sent,$logs);$record=RuntimePurchaseOrderAuditTest::data();$id=$op==='create'?null:'17';
            switch($case){case 'create-id':unset($record['row_stamp']);break;case 'create-stamp':unset($record['proc_compra_id']);break;case 'update-stamp-missing':unset($record['row_stamp']);break;case 'update-stamp-lower':$record['row_stamp']=str_repeat('a',64);break;case 'update-id-alias':$record['proc_compra_id']=18;break;case 'delete-stamp':$record['row_stamp']=null;break;case 'delete-id-fraction':$id='17.5';break;}
            try{$this->invoke($s,$op,$record,$id);self::fail('Invalid write reached API: '.$case);}catch(RuntimeHttpException $e){self::assertSame(422,$e->getStatusCode(),$case);}self::assertSame([],$sent,$case);
        }
    }
    public function testCommittedMissingOrMalformedImagesWarnOnceWithoutReplaying(): void
    {
        foreach(['missing','actor','data']as $case){$body=RuntimePurchaseOrderAuditTest::body('create');switch($case){case 'missing':unset($body['audit']);break;case 'actor':$body['audit']['actor']['login']='spoof';break;case 'data':$body['data']='invalid';break;}$sent=[];$logs=[];$s=$this->service('create',$body,$sent,$logs);$record=RuntimePurchaseOrderAuditTest::row();unset($record['proc_compra_id']);$result=$this->invoke($s,'create',$record);self::assertCount(1,$sent);self::assertSame('warning',$result['effects'][0]['type']);foreach($logs as $log){self::assertFalse($log->getMetadata()['audit']['available']??false);self::assertSame([],$log->getBeforeData());self::assertSame([],$log->getAfterData());}}
    }
    public function testCommittedPresentationFailureNeverLosesTheValidatedStampOrRepeatsTheWriter(): void
    {
        foreach(['create','update']as $op){$sent=[];$logs=[];$s=$this->service($op,RuntimePurchaseOrderAuditTest::body($op),$sent,$logs,true);$record=RuntimePurchaseOrderAuditTest::data();unset($record['observacao']);if($op==='create')unset($record['proc_compra_id'],$record['row_stamp']);$result=$this->invoke($s,$op,$record,$op==='update'?'17':null);self::assertCount(1,$sent);self::assertSame(str_repeat('A',64),$result['row_stamp']);self::assertSame('Observação',$result['observacao']);}
    }
    public function testGetNormalizesIntegerIdentityAndValidatesItsFullReadonlyRow(): void
    {
        $sent=[];$logs=[];$s=$this->service('get',['success'=>true,'data'=>RuntimePurchaseOrderAuditTest::data()],$sent,$logs);$out=$this->invoke($s,'get',['proc_compra_id'=>'17'],'17');self::assertSame(RuntimePurchaseOrderAuditTest::data(),$out);self::assertSame(17,$sent[0]['id']);self::assertSame(17,$sent[0]['proc_compra_id']);self::assertArrayNotHasKey('row_stamp',$sent[0]);
        $bad=RuntimePurchaseOrderAuditTest::data();$bad['row_stamp']='invalid';$sent=[];$logs=[];$s=$this->service('get',['success'=>true,'data'=>$bad],$sent,$logs);try{$this->invoke($s,'get',['proc_compra_id'=>17],17);self::fail('Invalid get row accepted');}catch(RuntimeHttpException $e){self::assertSame(502,$e->getStatusCode());}self::assertCount(1,$sent);
    }
}
