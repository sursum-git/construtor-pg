<?php
namespace App\Tests\Ciw;
use App\Ciw\CiwSubjectResolver;
use App\Entity\{AuthUser,AuthSubscriber,AuthUserSubscriber,RuntimeUserSession};
use App\Repository\{AuthUserRepository,AuthSubscriberRepository,AuthUserSubscriberRepository};
use App\Runtime\{StructuralIntegrityService,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
final class CiwSubjectResolverTest extends TestCase
{
    private function build(string $case):array
    {
        $session=(new RuntimeUserSession())->setTenantId('default')->setUserId('tcassimiro')->setSessionId('test-session')->setSessionProperties(['authentication'=>['provider'=>'ima-user-web'],'subscriber'=>['current'=>['code'=>'default']]]);
        $user=(new AuthUser())->setTenantId('default')->setUsername('tcassimiro')->setAuthSource('ima-user-web');
        $link=(new AuthUserSubscriber())->setUserTenantId('default')->setUsername('tcassimiro')->setSubscriberCode('default')->setMetadata(['ciwAuthorization'=>['version'=>1,'subject'=>'thiago.cassimiro','system'=>18]]);
        $subscriber=(new AuthSubscriber())->setCode('default')->setEnabled(true);
        if($case==='local')$session->setSessionProperties(['authentication'=>['provider'=>'local']]);
        if($case==='inactive')$user->setStatus('inactive');
        if($case==='disabled')$link->setEnabled(false);
        if($case==='missing')$link->setMetadata([]);
        if($case==='system')$link->setMetadata(['ciwAuthorization'=>['version'=>1,'subject'=>'thiago.cassimiro','system'=>1]]);
        if($case==='tenant')$link->setSubscriberCode('other');
        $users=$this->createStub(AuthUserRepository::class);$users->method('findOneByTenantAndUsername')->willReturn($user);
        $links=$this->createStub(AuthUserSubscriberRepository::class);$links->method('findOneEnabledForUserAndSubscriber')->willReturn($link);
        $subs=$this->createStub(AuthSubscriberRepository::class);$subs->method('findOneBy')->willReturn($subscriber);
        $integrity=$this->createStub(StructuralIntegrityService::class);
        if($case==='tampered')$integrity->method('assertAuthUserSubscriber')->willThrowException(new RuntimeHttpException('STRUCTURAL_INTEGRITY','invalid',409));
        return [new CiwSubjectResolver($users,$links,$subs,$integrity),$session];
    }
    public function testUsesExplicitSubjectInsteadOfProgressName():void {[$r,$s]=$this->build('valid');self::assertSame('thiago.cassimiro',$r->resolve($s));}
    public function testNoMissingOrForgedBindingCanAuthorize():void
    {
        foreach(['local','inactive','disabled','missing','system','tenant','tampered'] as $case){[$r,$s]=$this->build($case);try{$r->resolve($s);self::fail($case);}catch(RuntimeHttpException $e){self::assertGreaterThanOrEqual(400,$e->getStatusCode());}}
    }
}
