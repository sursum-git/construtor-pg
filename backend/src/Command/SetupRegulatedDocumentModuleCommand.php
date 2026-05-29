<?php

namespace App\Command;

use App\Runtime\RuntimeRegulatedDocumentStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:regulated-document:setup', description: 'Prepara o modulo regulado no ambiente atual.')]
class SetupRegulatedDocumentModuleCommand extends Command
{
    public function __construct(
        private readonly RuntimeRegulatedDocumentStore $store,
        private readonly SeedRuntimeMetadataCommand $seedRuntimeMetadataCommand,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('skip-seed', null, InputOption::VALUE_NONE, 'Nao executa o seed de metadados runtime.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->store->isEnabled()) {
            $io->warning('Modulo regulado desabilitado. Configure REGULATED_DOCUMENT_ENABLED=1 e REGULATED_DOCUMENT_DATABASE_URL antes do setup.');
            return Command::SUCCESS;
        }

        $this->store->initializeSchema();
        $io->success('Storage do modulo regulado inicializado.');

        if ($input->getOption('skip-seed') === true) {
            $io->note('Seed do runtime ignorado por opcao.');
            return Command::SUCCESS;
        }

        $result = $this->seedRuntimeMetadataCommand->run(new ArrayInput([]), $output);
        if ($result !== Command::SUCCESS) {
            $io->error('Falha ao executar o seed do runtime depois do setup do modulo regulado.');
            return $result;
        }

        $io->success('Setup do modulo regulado concluido com seed do runtime.');

        return Command::SUCCESS;
    }
}
