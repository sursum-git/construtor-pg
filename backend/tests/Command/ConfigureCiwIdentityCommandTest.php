<?php
namespace App\Tests\Command;
use App\Command\ConfigureCiwIdentityCommand;
use App\Ciw\CiwIdentityRegistration;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;
final class ConfigureCiwIdentityCommandTest extends TestCase
{
    public function testPreviewDoesNotWriteAndApplyCallsGovernedRegistration():void
    {
        $r=$this->createMock(CiwIdentityRegistration::class);$r->expects(self::once())->method('register')->willReturn(['created'=>true,'username'=>'tcassimiro']);
        $t=new CommandTester(new ConfigureCiwIdentityCommand($r));self::assertSame(0,$t->execute([]));self::assertStringContainsString('"apply":false',$t->getDisplay());self::assertSame(0,$t->execute(['--apply'=>true]));self::assertStringContainsString('"created":true',$t->getDisplay());
    }
    public function testUnexpectedErrorDoesNotLeakDetails():void
    {
        $r=$this->createStub(CiwIdentityRegistration::class);$r->method('register')->willThrowException(new \RuntimeException('password=synthetic_secret'));
        $t=new CommandTester(new ConfigureCiwIdentityCommand($r));self::assertSame(1,$t->execute(['--apply'=>true]));self::assertStringNotContainsString('synthetic_secret',$t->getDisplay());
    }
}
