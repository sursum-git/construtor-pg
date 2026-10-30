<?php
namespace App\Tests\Ciw;
use App\Ciw\CiwProgressIdentityClient;
use App\Entity\RuntimeUserSession;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
final class CiwProgressIdentityClientTest extends TestCase
{
    public function testBuildsIdentityOnlyFromSessionAndParsesEnvelope():void
    {
        $f=tempnam(sys_get_temp_dir(),'ciw-');file_put_contents($f,json_encode(['url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-identity','token'=>str_repeat('a',64)]));
        $s=(new RuntimeUserSession())->setUserId('tcassimiro')->setSessionId('real-session')->setSessionProperties(['authentication'=>['provider'=>'ima-user-web']]);
        try{
            $calls=0;$client=new CiwProgressIdentityClient($f,function($url,$headers,$body,$timeout)use(&$calls){$calls++;self::assertSame('tcassimiro',$headers['X-Sursum-User']);self::assertSame(['operation'=>'identify'],$body);self::assertLessThanOrEqual(10,$timeout);return ['status'=>200,'body'=>['success'=>true,'data'=>[['login'=>'TCASSIMIRO']]]];});
            $client->assertActive($s);self::assertSame(1,$calls);
            $bad=new CiwProgressIdentityClient($f,fn()=>['status'=>200,'body'=>['success'=>false,'error'=>['code'=>'CIW_IDENTITY_INACTIVE','status'=>403]]]);
            try{$bad->assertActive($s);self::fail('accepted');}catch(RuntimeHttpException $e){self::assertSame(403,$e->getStatusCode());}
            $mismatch=new CiwProgressIdentityClient($f,fn()=>['status'=>200,'body'=>['success'=>true,'data'=>[['login'=>'other']]]]);
            $this->expectException(RuntimeHttpException::class);$mismatch->assertActive($s);
        }finally{unlink($f);}
    }
}
