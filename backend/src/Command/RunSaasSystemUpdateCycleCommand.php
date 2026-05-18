<?php

namespace App\Command;

use App\Runtime\SystemUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:update:saas-cycle', description: 'Detecta releases no sistema central SaaS e enfileira automaticamente a proxima release aplicavel.')]
class RunSaasSystemUpdateCycleCommand extends Command
{
    public function __construct(
        private readonly SystemUpdateService $updates,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'URL ou arquivo do manifesto.')
            ->addOption('no-auto-queue', null, InputOption::VALUE_NONE, 'Executa apenas a deteccao, sem enfileirar automaticamente a release aplicavel.')
            ->addOption('fail-on-pending-critical', null, InputOption::VALUE_NONE, 'Falha quando houver release critica pendente ao final do ciclo.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->updates->runSaasOperationalCycle(
            $input->getOption('source') ? trim((string) $input->getOption('source')) : null,
            $input->getOption('no-auto-queue') !== true
        );

        $summary = (array) ($result['summary'] ?? []);
        $queued = is_array($result['queuedRelease'] ?? null) ? $result['queuedRelease'] : [];
        $io->text('Versao atual: ' . (string) ($summary['currentVersion'] ?? '-'));
        $io->text('Pendentes: ' . (int) ($summary['pendingCount'] ?? 0));
        $io->text('Criticas pendentes: ' . (int) ($summary['criticalPendingCount'] ?? 0));
        if ($queued) {
            $io->success(sprintf(
                'Release %s enfileirada automaticamente no job %s.',
                (string) ($queued['version'] ?? '-'),
                (string) ($queued['jobId'] ?? '-')
            ));
        } else {
            $io->text('Nenhuma release foi enfileirada automaticamente neste ciclo.');
        }

        foreach ((array) ($result['releases'] ?? []) as $release) {
            $io->writeln(sprintf(
                '- %s [%s/%s] => %s',
                (string) ($release['version'] ?? '-'),
                (string) ($release['category'] ?? '-'),
                (string) ($release['severity'] ?? '-'),
                (string) ($release['status'] ?? '-')
            ));
        }

        if ($input->getOption('fail-on-pending-critical') === true && (int) ($summary['criticalPendingCount'] ?? 0) > 0) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
