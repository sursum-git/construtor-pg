<?php

namespace App\Command;

use App\Runtime\SystemUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:update:check', description: 'Consulta o manifesto de atualizacoes e registra as releases locais.')]
class CheckSystemUpdatesCommand extends Command
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
            ->addOption('auto-queue', null, InputOption::VALUE_NONE, 'Permite enfileirar automaticamente release critica aplicavel.')
            ->addOption('fail-on-pending-critical', null, InputOption::VALUE_NONE, 'Retorna falha quando houver release critica pendente.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->updates->check(
            $input->getOption('source') ? (string) $input->getOption('source') : null,
            true,
            $input->getOption('auto-queue') === true
        );

        $summary = (array) ($result['summary'] ?? []);
        $io->text('Versao atual: ' . (string) ($summary['currentVersion'] ?? '-'));
        $io->text('Modo de implantacao: ' . (string) ($summary['deploymentMode'] ?? '-'));
        $io->text('Manifesto: ' . (string) ($summary['manifestSignatureStatus'] ?? '-'));
        $io->text('Pendentes: ' . (string) ($summary['pendingCount'] ?? 0));
        $io->text('Criticas pendentes: ' . (string) ($summary['criticalPendingCount'] ?? 0));

        foreach ((array) ($result['releases'] ?? []) as $release) {
            $io->writeln(sprintf(
                '- %s [%s/%s] => %s',
                (string) ($release['version'] ?? '-'),
                (string) ($release['category'] ?? '-'),
                (string) ($release['severity'] ?? '-'),
                (string) ($release['status'] ?? '-')
            ));
        }

        if ($input->getOption('fail-on-pending-critical') && (int) ($summary['criticalPendingCount'] ?? 0) > 0) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
