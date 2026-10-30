<?php
namespace App\Tests\Auth;
use App\Auth\ImaUserWebAuthProvider;
use App\Auth\ImaUserWebClientInterface;
use App\Entity\AuthProviderConfig;
use App\Entity\AuthUser;
use App\Repository\AuthUserRepository;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;

class ImaUserWebAuthProviderTest extends TestCase
{
    private function config(): AuthProviderConfig
    { return (new AuthProviderConfig())->setCode('ima-user-web')->setType('ima-user-web')->setEnabled(true); }
    private function user(): AuthUser
    { return (new AuthUser())->setTenantId('ciw-test')->setUsername('ciw_test')->setAuthSource('ima-user-web')->setStatus('active')->setGroups(['ciw_read'])->setPermissions(['screen.ciw.read'=>true]); }
    public function testVerifiedIdentityPreservesLocalPermissionsAndNoPassword(): void
    {
        $u=$this->user(); $repo=$this->createMock(AuthUserRepository::class);
        $repo->expects(self::once())->method('findOneByTenantAndUsername')->with('ciw-test','ciw_test')->willReturn($u);
        $client=$this->createMock(ImaUserWebClientInterface::class);
        $client->expects(self::once())->method('authenticate')->with('ciw_test',"A'ç ")->willReturn('CIW_TEST');
        $p=new ImaUserWebAuthProvider($repo,$client);
        $result=$p->authenticate($this->config(),['tenantId'=>'ciw-test','username'=>'ciw_test','password'=>"A'ç ",'provider'=>'local']);
        self::assertSame('ciw_test',$result->getUserId());
        self::assertSame('ima-user-web',$result->getSource());
        self::assertSame(['ciw_read'],$result->getGroups());
        self::assertSame($u->getPermissions(),$result->getPermissions());
        self::assertStringNotContainsString("A'ç ",json_encode($result->toPayload()));
        self::assertSame('ima-user-web',$u->getAuthSource()); self::assertNull($u->getPasswordHash());
        self::assertTrue($p->supports($this->config()));
        self::assertFalse($p->supports((new AuthProviderConfig())->setType('local')));
    }
    public function testUnboundInactiveOrOtherTenantAccountsNeverContactProgress(): void
    {
        foreach (['absent','local','inactive','tenant'] as $case) {
            $u=$case==='absent'?null:$this->user();
            if ($case==='local') $u->setAuthSource('local');
            if ($case==='inactive') $u->setStatus('inactive');
            if ($case==='tenant') $u->setTenantId('another');
            $repo=$this->createStub(AuthUserRepository::class); $repo->method('findOneByTenantAndUsername')->willReturn($u);
            $client=$this->createMock(ImaUserWebClientInterface::class); $client->expects(self::never())->method('authenticate');
            try {(new ImaUserWebAuthProvider($repo,$client))->authenticate($this->config(),['tenantId'=>'ciw-test','username'=>'ciw_test','password'=>'synthetic']); self::fail('Must deny');}
            catch(RuntimeHttpException $e){self::assertSame('INVALID_CREDENTIALS',$e->getErrorCode());}
        }
    }
    public function testMismatchedProgressIdentityDenied(): void
    {
        $repo=$this->createStub(AuthUserRepository::class); $repo->method('findOneByTenantAndUsername')->willReturn($this->user());
        $client=$this->createStub(ImaUserWebClientInterface::class); $client->method('authenticate')->willReturn('OTHER_TEST');
        $this->expectException(RuntimeHttpException::class);
        (new ImaUserWebAuthProvider($repo,$client))->authenticate($this->config(),['tenantId'=>'ciw-test','username'=>'ciw_test','password'=>'synthetic']);
    }
}
