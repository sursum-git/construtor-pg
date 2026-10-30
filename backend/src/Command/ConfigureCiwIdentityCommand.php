<?php
namespace App\Command;
use App\Ciw\CiwIdentityRegistration;
use App\Runtime\RuntimeHttpException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:auth:configure-ciw-identity',description:'Cadastra somente o vinculo CIW aprovado para tcassimiro.')]
class ConfigureCiwIdentityCommand extends Command
{
    public function __construct(private readonly CiwIdentityRegistration $registration){parent::__construct();}
    protected function configure():void{$this->addOption('apply',null,InputOption::VALUE_NONE,'Aplicar o cadastro aprovado.');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        if(!$input->getOption('apply')){$output->writeln(json_encode(['apply'=>false,'username'=>'tcassimiro','subscriber'=>'default','authSource'=>'ima-user-web','subject'=>'thiago.cassimiro','system'=>18]));return Command::SUCCESS;}
        try{$output->writeln(json_encode($this->registration->register()));return Command::SUCCESS;}
        catch(RuntimeHttpException $e){$output->writeln($e->getErrorCode().': '.$e->getMessage());return Command::FAILURE;}
        catch(\Throwable){$output->writeln('CIW_REGISTRATION_FAILED');return Command::FAILURE;}
    }
}
