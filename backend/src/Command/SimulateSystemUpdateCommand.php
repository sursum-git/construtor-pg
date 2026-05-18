<?php

namespace App\Command;

use App\Runtime\SystemUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:update:simulate', description: 'Simula a aplicacao de uma release para um assinante ou lote SaaS.')]
class SimulateSystemUpdateCommand extends Command
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
            ->addOption('subscriber', null, InputOption::VALUE_REQUIRED, 'Assinante alvo da simulacao.')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Lote SaaS alvo da simulacao.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->updates->simulateRelease(
            trim((string) $input->getArgument('version')),
            trim((string) ($input->getOption('subscriber') ?? '')) ?: null,
            trim((string) ($input->getOption('batch') ?? '')) ?: null
        );

        $release = (array) ($result['release'] ?? []);
        $precheck = (array) ($result['precheck'] ?? []);
        $io->text('Release: ' . (string) ($release['version'] ?? '-'));
        $io->text('Status: ' . (string) ($release['status'] ?? '-'));
        $io->text('Canal: ' . (string) ($release['targetChannel'] ?? 'stable'));
        $io->text('Pre-check: ' . (string) ($precheck['status'] ?? '-'));
        foreach ((array) ($precheck['checks'] ?? []) as $check) {
            $io->writeln(sprintf(
                '  - [%s] %s => %s',
                (string) ($check['status'] ?? '-'),
                (string) ($check['title'] ?? '-'),
                (string) ($check['message'] ?? '-')
            ));
        }

        return Command::SUCCESS;
    }
}
