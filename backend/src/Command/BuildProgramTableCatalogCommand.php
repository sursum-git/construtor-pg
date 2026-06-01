<?php

namespace App\Command;

use App\Catalog\ProgramTableCatalogService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'app:catalog:program-tables', description: 'Gera catalogo de programas e tabelas em SQLite, JSON e JS para busca local.')]
class BuildProgramTableCatalogCommand extends Command
{
    public function __construct(
        private readonly ProgramTableCatalogService $catalog,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = dirname($this->kernel->getProjectDir());

        $catalog = $this->catalog->buildCatalog();
        $artifacts = $this->catalog->writeArtifacts($projectRoot, $catalog);

        $io->success('Catalogo de programas e tabelas gerado.');
        $io->definitionList(
            ['Programas' => (string) ($catalog['stats']['programCount'] ?? 0)],
            ['Tabelas' => (string) ($catalog['stats']['tableCount'] ?? 0)],
            ['Relacoes' => (string) ($catalog['stats']['relationCount'] ?? 0)],
            ['SQLite' => $artifacts['sqlitePath']],
            ['JSON' => $artifacts['jsonPath']],
            ['JS' => $artifacts['jsPath']],
        );

        return Command::SUCCESS;
    }
}
