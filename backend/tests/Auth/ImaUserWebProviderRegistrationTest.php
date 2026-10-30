<?php
namespace App\Tests\Auth;
use App\Auth\{ImaUserWebProviderRegistration,ImaUserWebProviderConfiguration};
use App\Entity\AuthProviderConfig;
use App\Repository\AuthProviderConfigRepository;
use App\Runtime\{StructuralIntegrityService,RuntimeTransactionService,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
class ImaUserWebProviderRegistrationTest extends TestCase
{
    private function registration(?AuthProviderConfig $existing,bool $write): ImaUserWebProviderRegistration
    {
        $repo=$this->createStub(AuthProviderConfigRepository::class);$repo->method('findOneBy')->willReturn($existing);
        $em=$this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn($callback)=>$callback());
        $em->expects($write?self::once():self::never())->method('persist')->with(self::isInstanceOf(AuthProviderConfig::class));
        $integrity=$this->createMock(StructuralIntegrityService::class);
        $integrity->expects($write?self::once():self::never())->method('signAuthProvider');
        $tx=$this->createMock(RuntimeTransactionService::class);
        $tx->expects($write?self::once():self::never())->method('beginOperational')->with(self::callback(fn($c)=>$c['source']==='cli'&&!isset($c['password'],$c['token'])));
        return new ImaUserWebProviderRegistration($repo,$em,$integrity,$tx,new ImaUserWebProviderConfiguration('NOT_READ_FOR_DISABLED_REGISTRATION'));
    }
    public function testCreatesOnlyDisabledProvider(): void
    {
        $cfg=$this->registration(null,true)->register();
        self::assertSame('ima-user-web',$cfg->getCode());self::assertSame('ima-user-web',$cfg->getType());
        self::assertFalse($cfg->isEnabled());self::assertSame([],$cfg->getConfig());
    }
    public function testIdempotentRegistrationDoesNotDisableOrRewrite(): void
    {
        $cfg=(new AuthProviderConfig())->setCode('ima-user-web')->setType('ima-user-web')->setEnabled(true);
        self::assertSame($cfg,$this->registration($cfg,false)->register());self::assertTrue($cfg->isEnabled());
    }
    public function testConflictingTypeCannotBeReplaced(): void
    {
        $cfg=(new AuthProviderConfig())->setCode('ima-user-web')->setType('ldap');
        $this->expectException(RuntimeHttpException::class);
        $this->registration($cfg,false)->register();
    }
}
