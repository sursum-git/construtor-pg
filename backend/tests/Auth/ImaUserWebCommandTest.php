<?php
namespace App\Tests\Auth;
use App\Auth\ImaUserWebProviderRegistration;
use App\Command\ConfigureImaUserWebProviderCommand;
use App\Entity\AuthProviderConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
class ImaUserWebCommandTest extends TestCase
{
    public function testDefaultDisabledAndEnableMustBeExplicit(): void
    {
        foreach([false,true] as $enabled) {
            $registration=$this->createMock(ImaUserWebProviderRegistration::class);
            $registration->expects(self::once())->method('register')->with($enabled)->willReturn((new AuthProviderConfig())->setCode('ima-user-web')->setType('ima-user-web')->setEnabled($enabled));
            $tester=new CommandTester(new ConfigureImaUserWebProviderCommand($registration));
            self::assertSame(0,$tester->execute($enabled?['--enable'=>true]:[]));
            self::assertStringContainsString($enabled?'habilitado':'desabilitado',$tester->getDisplay());
        }
    }
}
