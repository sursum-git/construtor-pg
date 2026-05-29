<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:regulated-document:bootstrap-demo', description: 'Prepara o modulo regulado e gera emissoes de exemplo no ambiente atual.')]
class BootstrapRegulatedDocumentDemoCommand extends Command
{
    public function __construct(
        private readonly SetupRegulatedDocumentModuleCommand $setupCommand,
        private readonly SeedRegulatedDocumentSamplesCommand $seedSamplesCommand,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Bootstrap documental do modulo regulado');

        $setupResult = $this->setupCommand->run(new ArrayInput([]), $output);
        if ($setupResult !== Command::SUCCESS) {
            $io->error('Falha no setup do modulo regulado.');
            return $setupResult;
        }

        $samplesResult = $this->seedSamplesCommand->run(new ArrayInput([]), $output);
        if ($samplesResult !== Command::SUCCESS) {
            $io->error('Falha ao gerar emissoes de exemplo do modulo regulado.');
            return $samplesResult;
        }

        $io->success('Bootstrap documental concluido com setup, seed runtime e emissoes de exemplo.');

        return Command::SUCCESS;
    }
}
