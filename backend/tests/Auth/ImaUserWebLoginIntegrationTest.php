<?php
namespace App\Tests\Auth;
use App\Auth\{AuthService,AuthProviderRegistry,AuthenticatedSessionResolver,ImaUserWebAuthProvider,ImaUserWebClientInterface};
use App\Entity\{AuthProviderConfig,AuthUser,RuntimeUserSession};
use App\Repository\{AuthProviderConfigRepository,AuthUserRepository,AuthRememberTokenRepository,RuntimeUserSessionRepository,AuthSubscriberRepository,AuthUserSubscriberRepository,AuthLoginChallengeRepository,AuthPasswordResetTokenRepository};
use App\System\SystemParameterResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
class ImaUserWebLoginIntegrationTest extends TestCase
{
    public function testAuthServiceIssuesVerifiedProviderAndIgnoresPayloadProvider(): void
    {
        $user=(new AuthUser())->setTenantId('ciw-test')->setUsername('ciw_test')->setAuthSource('ima-user-web')->setStatus('active')->setGroups(['ciw_read'])->setPermissions([]);
        $users=$this->createStub(AuthUserRepository::class); $users->method('findOneByTenantAndUsername')->willReturn($user);
        $cfg=(new AuthProviderConfig())->setCode('ima-user-web')->setType('ima-user-web')->setEnabled(true);
        $configs=$this->createMock(AuthProviderConfigRepository::class);
        $configs->expects(self::once())->method('findEnabledByCode')->with('ima-user-web')->willReturn($cfg);
        $client=$this->createMock(ImaUserWebClientInterface::class);
        $client->expects(self::once())->method('authenticate')->willReturn('CIW_TEST');
        $parameters=$this->createStub(SystemParameterResolver::class);$parameters->method('getBoolean')->willReturn(false);
        $em=$this->createMock(EntityManagerInterface::class);$session=null;
        $em->expects(self::once())->method('persist')->willReturnCallback(function($value)use(&$session){self::assertInstanceOf(RuntimeUserSession::class,$value);$session=$value;});
        $auth=new AuthService($configs,$users,$this->createStub(AuthRememberTokenRepository::class),
            $this->createStub(RuntimeUserSessionRepository::class),$this->createStub(AuthSubscriberRepository::class),
            $this->createStub(AuthUserSubscriberRepository::class),$this->createStub(AuthLoginChallengeRepository::class),
            $this->createStub(AuthPasswordResetTokenRepository::class),$em,
            new AuthProviderRegistry([new ImaUserWebAuthProvider($users,$client)]),
            $this->createStub(AuthenticatedSessionResolver::class),$parameters,$this->createStub(MailerInterface::class));
        $result=$auth->login(['tenantId'=>'ciw-test','username'=>'ciw_test','password'=>'SYNTHETIC_DO_NOT_PERSIST','provider'=>'local'],Request::create('/api/auth/login','POST'));
        self::assertTrue($result['authenticated']);
        self::assertSame('ima-user-web',$session->getSessionProperties()['authentication']['provider']);
        self::assertSame('ciw-test',$session->getTenantId());
        self::assertStringNotContainsString('SYNTHETIC_DO_NOT_PERSIST',json_encode($session->getSessionProperties()));
        self::assertStringNotContainsString('SYNTHETIC_DO_NOT_PERSIST',json_encode($result));
        self::assertContains('ima-user-web',$auth->listProviders()['supportedTypes']);
    }
}
