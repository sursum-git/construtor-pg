<?php
namespace App\Tests\Auth;

use App\Auth\AuthenticatedUser;
use App\Auth\AuthService;
use App\Entity\AuthSubscriber;
use App\Entity\AuthUserSubscriber;
use App\Entity\RuntimeUserSession;
use App\Repository\AuthSubscriberRepository;
use App\Repository\AuthUserSubscriberRepository;
use App\Repository\RuntimeUserSessionRepository;
use App\System\SystemParameterResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

if ($source = getenv('AUTH_SERVICE_SOURCE')) { require_once $source; }

final class AuthSubscriberPermissionsTest extends TestCase
{
    private function login(bool $enabled, ?array $overrides, array $base = [], bool $rememberPath = false): array
    {
        $reflection = new \ReflectionClass(AuthService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $parameters = $this->createMock(SystemParameterResolver::class);
        $parameters->expects(self::once())->method('getBoolean')->with('subscriber.enabled')->willReturn($enabled);
        $links = $this->createMock(AuthUserSubscriberRepository::class);
        $link = $overrides === null ? null : (new AuthUserSubscriber())
            ->setUserTenantId('default')->setUsername('usuario-teste')
            ->setSubscriberCode('default')->setEnabled(true)->setDefaultSubscriber(true)
            ->setPermissionOverrides($overrides);
        $links->method('findEnabledForUser')->willReturn($link ? [$link] : []);
        $links->expects(self::once())->method('findOneEnabledForUserAndSubscriber')
            ->with('default', 'usuario-teste', 'default')->willReturn($link);
        $subscribers = $this->createMock(AuthSubscriberRepository::class);
        $subscribers->expects($enabled ? self::atLeastOnce() : self::never())->method('findEnabledByCode')->with('default')->willReturn(
            (new AuthSubscriber())->setCode('default')->setName('Principal')->setEnabled(true)
        );
        $sessions = $this->createStub(RuntimeUserSessionRepository::class);
        $sessions->method('findByTenantAndSession')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $captured = null;
        $em->expects(self::once())->method('persist')->willReturnCallback(function ($session) use (&$captured) {
            self::assertInstanceOf(RuntimeUserSession::class, $session); $captured = $session;
        });
        $em->expects(self::once())->method('flush');
        foreach (['parameters'=>$parameters,'userSubscribers'=>$links,'subscribers'=>$subscribers,'sessions'=>$sessions,'entityManager'=>$em] as $name=>$value) {
            $reflection->getProperty($name)->setValue($service,$value);
        }
        $user = new AuthenticatedUser('default','usuario-teste','usuario-teste','Teste',null,[],$base,'ldap');
        if ($rememberPath) {
            $remember = $this->createMock(\App\Repository\AuthRememberTokenRepository::class);
            $remember->expects(self::once())->method('findActiveByToken')->with('fixture-only')->willReturn(
                (new \App\Entity\AuthRememberToken())->setTenantId('default')->setUsername('usuario-teste')->setUserId('usuario-teste')
            );
            $users = $this->createMock(\App\Repository\AuthUserRepository::class);
            $users->expects(self::once())->method('findOneByTenantAndUsername')->with('default','usuario-teste')->willReturn(
                (new \App\Entity\AuthUser())->setTenantId('default')->setUsername('usuario-teste')->setStatus('active')->setAuthSource('ldap')->setGroups([])->setPermissions($base)
            );
            $reflection->getProperty('rememberTokens')->setValue($service,$remember);
            $reflection->getProperty('users')->setValue($service,$users);
            $response = $service->remember(['rememberToken'=>'fixture-only'],new Request());
            unset($response['rememberToken']);
        } else {
            $response = $reflection->getMethod('completeAuthenticatedLogin')->invoke($service,$user,'ldap','ldap',new Request(),false);
        }
        unset($response['token']); // Token sintetico de teste nao e impresso nem persistido.
        return [$response,$captured->getPermissionSnapshot()];
    }

    public function testLdapLoginWithoutSubscriberSelectionIncludesOwnTenantOverrides(): void
    {
        [$response,$snapshot] = $this->login(false,['empresas_progress.read'=>true]);
        self::assertTrue($response['authenticated']);
        self::assertTrue($response['user']['permissions']['empresas_progress.read'] ?? false);
        self::assertTrue($snapshot['permissions']['empresas_progress.read'] ?? false);
        self::assertSame($snapshot['permissions'],$snapshot['user']['permissions']);
        self::assertFalse($snapshot['subscriber']['enabled']);
    }

    public function testExistingBasePermissionsAndExplicitDenialArePreserved(): void
    {
        [$response] = $this->login(false,['empresas_progress.read'=>true,'despesas_progress.read'=>false],['legado.read'=>true,'despesas_progress.read'=>true]);
        self::assertTrue($response['user']['permissions']['legado.read']);
        self::assertTrue($response['user']['permissions']['empresas_progress.read'] ?? false);
        self::assertFalse($response['user']['permissions']['despesas_progress.read']);
    }

    public function testMissingLinkDoesNotCreatePermissions(): void
    {
        [$response] = $this->login(false,null,['legado.read'=>true]);
        self::assertSame(['legado.read'=>true],$response['user']['permissions']);
    }

    public function testEmptyOverridesDoNotChangeBasePermissions(): void
    {
        [$response] = $this->login(false,[],['legado.read'=>true]);
        self::assertSame(['legado.read'=>true],$response['user']['permissions']);
    }

    public function testRememberLoginAlsoIncludesOwnTenantOverrides(): void
    {
        [$response,$snapshot] = $this->login(false,['empresas_progress.read'=>true],[],true);
        self::assertTrue($response['user']['permissions']['empresas_progress.read'] ?? false);
        self::assertTrue($snapshot['permissions']['empresas_progress.read'] ?? false);
    }

    public function testEnabledSingleSubscriberStillAppliesOverrides(): void
    {
        [$response,$snapshot] = $this->login(true,['empresas_progress.read'=>true]);
        self::assertTrue($response['user']['permissions']['empresas_progress.read'] ?? false);
        self::assertTrue($snapshot['subscriber']['enabled']);
        self::assertSame('default',$response['currentSubscriber']['id']);
    }
}
