<?php

namespace App\Command;

use App\Runtime\SystemUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:update:download-pending-critical', description: 'Baixa localmente o primeiro pacote de release critica pendente no on-premise.')]
class DownloadPendingCriticalSystemUpdateCommand extends Command
{
    public function __construct(
        private readonly SystemUpdateService $updates,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Fonte opcional do manifesto.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $input->getOption('source') ? trim((string) $input->getOption('source')) : null;
        if ($source !== null && $source !== '') {
            $this->updates->check($source, true, false);
        }

        $result = $this->updates->downloadPendingCriticalRuntime();
        $package = is_array($result['package'] ?? null) ? $result['package'] : [];
        $output->writeln('Release critica baixada: ' . (string) ($result['releaseVersion'] ?? '-'));
        $output->writeln('Arquivo: ' . (string) ($package['savedPath'] ?? $package['fileName'] ?? '-'));
        $output->writeln('Hash: ' . (string) ($package['hash'] ?? '-'));

        return Command::SUCCESS;
    }
}
