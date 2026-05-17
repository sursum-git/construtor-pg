<?php

namespace App\Command;

use App\Runtime\SystemUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:update:run-pending', description: 'Aplica releases pendentes conforme politica automatica ou anuencias registradas.')]
class RunPendingSystemUpdatesCommand extends Command
{
    public function __construct(
        private readonly SystemUpdateService $updates,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Fonte opcional do manifesto.')
            ->addOption('auto-only', null, InputOption::VALUE_NONE, 'Aplica apenas releases autoaplicaveis.')
            ->addOption('disallow-consented', null, InputOption::VALUE_NONE, 'Ignora releases com anuencia registrada.')
            ->addOption('fail-on-pending-critical', null, InputOption::VALUE_NONE, 'Falha se restarem releases criticas pendentes ao final.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->updates->runPending(
            $input->getOption('source') ? trim((string) $input->getOption('source')) : null,
            $input->getOption('auto-only') === true,
            $input->getOption('disallow-consented') !== true
        );

        $applied = is_array($result['applied'] ?? null) ? $result['applied'] : [];
        foreach ($applied as $item) {
            $output->writeln(sprintf('- %s => %s', (string) ($item['releaseVersion'] ?? '-'), (string) ($item['status'] ?? 'unknown')));
        }

        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        $output->writeln('Pendentes: ' . (int) ($summary['pendingCount'] ?? 0));
        $output->writeln('Criticas pendentes: ' . (int) ($summary['criticalPendingCount'] ?? 0));
        if (($summary['criticalActionRequired'] ?? false) === true) {
            $output->writeln('Acao critica requerida: ' . implode(', ', (array) ($summary['pendingCriticalVersions'] ?? [])));
        }

        if ($input->getOption('fail-on-pending-critical') === true && (int) ($summary['criticalPendingCount'] ?? 0) > 0) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
