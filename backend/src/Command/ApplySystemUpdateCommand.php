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

#[AsCommand(name: 'app:update:apply', description: 'Aplica uma release pendente do atualizador.')]
class ApplySystemUpdateCommand extends Command
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
            ->addOption('force-consent', null, InputOption::VALUE_NONE, 'Ignora a exigencia de anuencia para execucao via CLI.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->updates->applyRelease(
            trim((string) $input->getArgument('version')),
            null,
            $input->getOption('force-consent') === true,
            'manual',
            'cli'
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
