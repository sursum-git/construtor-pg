<?php

namespace App\Command;

use App\Runtime\SystemUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:update:download', description: 'Baixa e valida o pacote associado a uma release de atualizacao.')]
class DownloadSystemUpdatePackageCommand extends Command
{
    public function __construct(
        private readonly SystemUpdateService $updates,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('version', InputArgument::REQUIRED, 'Versao da release.')
            ->addOption('subscriber-code', null, InputOption::VALUE_REQUIRED, 'Assinante alvo no sistema central.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->updates->downloadPackage(
            trim((string) $input->getArgument('version')),
            trim((string) ($input->getOption('subscriber-code') ?? '')) ?: null
        );

        $output->writeln('Release: ' . (string) ($result['releaseVersion'] ?? '-'));
        $output->writeln('Arquivo: ' . (string) (($result['package']['fileName'] ?? '-') ?: '-'));
        $output->writeln('Hash: ' . (string) (($result['package']['hash'] ?? '-') ?: '-'));
        $output->writeln('Assinatura: ' . (string) (($result['package']['signatureStatus'] ?? '-') ?: '-'));
        $output->writeln('Destino: ' . (string) (($result['package']['savedPath'] ?? '-') ?: '-'));

        return Command::SUCCESS;
    }
}
