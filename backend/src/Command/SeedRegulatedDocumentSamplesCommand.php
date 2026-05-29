<?php

namespace App\Command;

use App\Runtime\RuntimeRegulatedDocumentService;
use App\Runtime\RuntimeRegulatedDocumentStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:regulated-document:seed-samples', description: 'Gera emissoes reais de exemplo no storage do modulo regulado.')]
class SeedRegulatedDocumentSamplesCommand extends Command
{
    public function __construct(
        private readonly RuntimeRegulatedDocumentStore $store,
        private readonly RuntimeRegulatedDocumentService $documents,
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

        $samples = [
            ['screenId' => 'documentos.regulados-fiscal-base', 'parameters' => ['status' => 'ATIVO', 'uf' => 'CE'], 'format' => 'pdf'],
            ['screenId' => 'documentos.regulados-bancario-base', 'parameters' => ['status' => 'ATIVO', 'uf' => 'SP'], 'format' => 'html'],
            ['screenId' => 'documentos.regulados-logistico-base', 'parameters' => ['status' => 'ATIVO'], 'format' => 'pdf'],
        ];

        $rows = [];
        foreach ($samples as $sample) {
            $issued = $this->documents->issue($sample['screenId'], [
                'parameters' => $sample['parameters'],
                'format' => $sample['format'],
            ]);
            $verified = $this->documents->verify($sample['screenId'], [
                'issueId' => $issued['issueId'],
                'hash' => $issued['hash'],
            ]);
            $rows[] = [
                $sample['screenId'],
                (string) $issued['issueId'],
                (string) $issued['format'],
                $verified['ok'] ? 'verificado' : 'falha',
            ];
        }

        $io->table(['ScreenId', 'IssueId', 'Formato', 'Conferencia'], $rows);
        $io->success('Emissoes reais de exemplo criadas no modulo regulado.');

        return Command::SUCCESS;
    }
}
