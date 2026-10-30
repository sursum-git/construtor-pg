<?php
namespace App\Command;
use App\Auth\ImaUserWebProviderRegistration;
use App\Runtime\RuntimeHttpException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:auth:configure-ima-user-web',description:'Cadastra somente o provedor IMA Progress, desabilitado por padrao.')]
class ConfigureImaUserWebProviderCommand extends Command
{
    public function __construct(private readonly ImaUserWebProviderRegistration $registration) {parent::__construct();}
    protected function configure(): void
    {
        $this->addOption('enable',null,InputOption::VALUE_NONE,'Habilitar apos publicacao e validacao do endpoint privado.');
    }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        try {
            $provider=$this->registration->register((bool)$input->getOption('enable'));
            $output->writeln('Provedor ima-user-web '.($provider->isEnabled()?'habilitado':'desabilitado').'. Nenhuma conta alterada.');
            return Command::SUCCESS;
        } catch (RuntimeHttpException $e) {
            $output->writeln('<error>'.$e->getErrorCode().': '.$e->getMessage().'</error>');
            return Command::FAILURE;
        } catch (\Throwable) {
            $output->writeln('<error>Nao foi possivel configurar o provedor IMA.</error>');
            return Command::FAILURE;
        }
    }
}
