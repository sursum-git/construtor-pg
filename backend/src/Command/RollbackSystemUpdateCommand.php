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

#[AsCommand(name: 'app:update:rollback', description: 'Executa o rollback formal de uma release.')]
class RollbackSystemUpdateCommand extends Command
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
            ->addOption('subscriber', null, InputOption::VALUE_REQUIRED, 'Assinante alvo.')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Motivo do rollback.')
            ->addOption('target-version', null, InputOption::VALUE_REQUIRED, 'Versao alvo do rollback.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->updates->rollbackRelease(
            trim((string) $input->getArgument('version')),
            trim((string) ($input->getOption('reason') ?? '')) ?: null,
            trim((string) ($input->getOption('subscriber') ?? '')) ?: null,
            trim((string) ($input->getOption('target-version') ?? '')) ?: null
        );

        $io->text('Release: ' . (string) ($result['releaseVersion'] ?? '-'));
        $io->text('Status: ' . (string) ($result['status'] ?? '-'));
        foreach ((array) ($result['steps'] ?? []) as $step) {
            $io->writeln(sprintf(
                '  - %s => %s',
                (string) ($step['step'] ?? '-'),
                (string) ($step['status'] ?? '-')
            ));
        }

        return (string) ($result['status'] ?? '') === 'succeeded' ? Command::SUCCESS : Command::FAILURE;
    }
}
