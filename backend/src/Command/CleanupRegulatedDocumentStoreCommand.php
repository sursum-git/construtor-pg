<?php

namespace App\Command;

use App\Runtime\RuntimeRegulatedDocumentStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:regulated-document:cleanup', description: 'Aplica a politica de retencao do modulo regulado.')]
class CleanupRegulatedDocumentStoreCommand extends Command
{
    public function __construct(
        private readonly RuntimeRegulatedDocumentStore $store,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Aplica a limpeza. Sem a opcao, apenas mostra o impacto.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->store->isEnabled()) {
            $io->warning('Modulo regulado desabilitado. Configure REGULATED_DOCUMENT_ENABLED=1 e REGULATED_DOCUMENT_DATABASE_URL.');
            return Command::SUCCESS;
        }

        $report = $this->store->cleanupExpiredData($input->getOption('apply') === true);
        $io->table(
            ['Registros lidos', 'Payloads limpos', 'Artefatos limpos', 'Eventos removidos', 'IssueIds afetados'],
            [[
                (string) ($report['recordsScanned'] ?? 0),
                (string) ($report['payloadsCleared'] ?? 0),
                (string) ($report['artifactsCleared'] ?? 0),
                (string) ($report['eventsDeleted'] ?? 0),
                implode(', ', (array) ($report['affectedIssueIds'] ?? [])),
            ]]
        );

        $io->success($input->getOption('apply') === true
            ? 'Retencao do modulo regulado aplicada.'
            : 'Analise de retencao concluida sem aplicar exclusoes.');

        return Command::SUCCESS;
    }
}
