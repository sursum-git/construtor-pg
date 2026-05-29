<?php

namespace App\Command;

use App\Runtime\RuntimeRegulatedDocumentStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:regulated-document:init', description: 'Inicializa a estrutura do modulo regulado no banco separado.')]
class InitializeRegulatedDocumentStoreCommand extends Command
{
    public function __construct(
        private readonly RuntimeRegulatedDocumentStore $store,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->store->isEnabled()) {
            $io->warning('Modulo regulado desabilitado. Configure REGULATED_DOCUMENT_ENABLED=1 e REGULATED_DOCUMENT_DATABASE_URL.');
            return Command::SUCCESS;
        }

        $this->store->initializeSchema();
        $io->success('Estrutura do modulo regulado pronta no banco separado.');

        return Command::SUCCESS;
    }
}
