<?php
namespace App\Tests\Runtime;
use App\Entity\{BuilderEntity,BuilderField,RuntimeTransaction,RuntimeTransactionLog};
use App\Repository\BuilderEntityRepository;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeExecutionContext,RuntimeEntityDefinitionResolver,RuntimeEnvironmentIdentityResolver,PermissionResolver};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/RuntimePurchasePaymentAuditTest.php';
final class RuntimePurchasePaymentImportTest extends TestCase
{
    private function imported(string $op,?array $body=null): array
    {
        $body??=RuntimePurchasePaymentAuditTest::body($op);$d=RuntimePurchasePaymentAuditTest::definition();$endpoint=RuntimePurchasePaymentAuditTest::endpoint($op)+['requestContextFields'=>['proc_compra_id']];
        $api=['responseContract'=>'sursum-programs','authHeaders'=>[],'listEndpoint'=>$endpoint,'listResponse'=>['itemsPath'=>'data'],$op.'Endpoint'=>$endpoint,$op.'Response'=>['itemPath'=>'data']];
        $entity=(new BuilderEntity())->setCode($d['entityCode'])->setEntityType('api')->setMetadata(['apiSource'=>$api,'apiBinding'=>$d['apiBinding']]);
        foreach(RuntimePurchasePaymentAuditTest::row() as $name=>$value){$type=is_int($value)?'integer':'string';$options=['readonly'=>$name!=='titulos'];if($name==='titulos')$options['api']=['collection'=>'integer','writePath'=>'values.titulos'];$entity->addField((new BuilderField())->setCode($name)->setLabel($name)->setDataType($type)->setPrimaryKey($name==='pagamento_key')->setOptions($options));}
        $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($entity);
        $logs=[];$manager=$this->createStub(EntityManagerInterface::class);$manager->method('persist')->willReturnCallback(static function($item)use(&$logs){if($item instanceof RuntimeTransactionLog)$logs[]=$item;});
        $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturn(true);
        $transactions=new RuntimeTransactionService($manager,$permissions,new RuntimeExecutionContext(),$this->createStub(RuntimeEntityDefinitionResolver::class),$this->createStub(RuntimeEnvironmentIdentityResolver::class));
        $tx=(new RuntimeTransaction())->setTenantId('tenant-a')->setRequestContext(['actor'=>['source'=>'authenticated-session','version'=>1,'username'=>'real-user']]);(new \ReflectionProperty($transactions,'current'))->setValue($transactions,$tx);
        $sent=[];$client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturnCallback(static function($url,$method,$headers,$request)use($body,&$sent){$sent[]=$request;return ['status'=>200,'body'=>$body];});
        $service=new RuntimeApiEntityActionService($repo,$transactions,$client,$permissions);
        $out=$service->handle('ciw.proc-compra-pagamentos',$op,['entityCode'=>$d['entityCode'],'operation'=>$op],['id'=>RuntimePurchasePaymentAuditTest::key(),'record'=>$op==='create'?['titulos'=>[7]]:RuntimePurchasePaymentAuditTest::row(),'context'=>['proc_compra_id'=>17]]);
        return [$out,$logs,$tx,$sent];
    }
    public function testNativeCreateDeleteKeepCommittedImagesRealUserAndRequestShape(): void
    {
        foreach(['create','delete'] as $op){[$out,$logs,$tx,$sent]=$this->imported($op);self::assertSame(RuntimePurchasePaymentAuditTest::key(),$tx->getRecordId());self::assertCount(1,$sent);self::assertSame(17,$sent[0]['proc_compra_id']);if($op==='create'){self::assertSame(['titulos'=>[7]],$sent[0]['values']);}else{self::assertArrayNotHasKey('values',$sent[0]);self::assertSame(RuntimePurchasePaymentAuditTest::key(),$sent[0]['id']);}
            $confirmed=array_values(array_filter($logs,static fn($l)=>($l->getMetadata()['audit']['available']??false)===true));self::assertCount(2,$confirmed);
            foreach($confirmed as $log){self::assertSame('real-user',$log->getMetadata()['actor']['username']);self::assertSame(17,$log->getMetadata()['audit']['procCompraId']);self::assertSame(RuntimePurchasePaymentAuditTest::key(),$log->getMetadata()['audit']['paymentKey']);}
            $main=$confirmed[1];$a=RuntimePurchasePaymentAuditTest::body($op)['audit'];self::assertSame($a['before'],$main->getBeforeData());self::assertSame($a['after'],$main->getAfterData());
        }
    }
    public function testMissingInvalidOrUncommittedAuditWarnsWithoutRepeatingOrFailingTheSuccessfulWrite(): void
    {
        foreach(['missing','actor','parent','commit','presentation'] as $case){$body=RuntimePurchasePaymentAuditTest::body();switch($case){case 'missing':unset($body['audit']);break;case 'actor':$body['audit']['actor']['login']='forged-user';break;case 'parent':$body['audit']['proc_compra_id']=18;break;case 'commit':$body['committed']=false;break;case 'presentation':unset($body['audit']);$body['data']['titulos']='invalid';break;}[$out,$logs,,$sent]=$this->imported('create',$body);self::assertCount(1,$sent);self::assertSame('warning',$out['effects'][0]['type']);foreach($logs as $log){self::assertFalse($log->getMetadata()['audit']['available']??false);self::assertSame([],$log->getBeforeData());self::assertSame([],$log->getAfterData());}self::assertStringNotContainsString('forged-user',json_encode(array_map(fn($l)=>$l->getMetadata(),$logs)));}
    }
}
