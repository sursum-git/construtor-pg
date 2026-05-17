<?php

namespace App\Command;

use App\Runtime\ProgramGovernanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:governance:monitor', description: 'Verifica pendencias operacionais da governanca e emite notificacoes administrativas.')]
class MonitorProgramGovernanceCommand extends Command
{
    public function __construct(
        private readonly ProgramGovernanceService $governance,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('program', null, InputOption::VALUE_REQUIRED, 'Restringe o monitoramento a um programa padrao especifico.');
        $this->addOption('fail-on-alert', null, InputOption::VALUE_NONE, 'Retorna erro quando houver pendencia operacional relevante.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $summary = $this->governance->emitOperationalAlerts(trim((string) $input->getOption('program')));
        $this->entityManager->flush();

        $io->table(
            ['Indicador', 'Total'],
            [
                ['Programas monitorados', (string) ($summary['programs'] ?? 0)],
                ['Publicacoes bloqueadas', (string) ($summary['publishBlocked'] ?? 0)],
                ['Overlays bloqueados', (string) ($summary['blockedOverlays'] ?? 0)],
                ['Grants congelados/revogados', (string) ($summary['frozenOrRevokedGrants'] ?? 0)],
                ['Integridade invalida', (string) ($summary['invalidIntegrity'] ?? 0)],
                ['Notificacoes emitidas', (string) ($summary['notifications'] ?? 0)],
            ]
        );

        $hasAlert = ((int) ($summary['publishBlocked'] ?? 0)) > 0
            || ((int) ($summary['blockedOverlays'] ?? 0)) > 0
            || ((int) ($summary['frozenOrRevokedGrants'] ?? 0)) > 0
            || ((int) ($summary['invalidIntegrity'] ?? 0)) > 0;

        if ($input->getOption('fail-on-alert') === true && $hasAlert) {
            $io->error('Monitoramento encontrou pendencias operacionais bloqueantes.');
            return Command::FAILURE;
        }

        $io->success('Monitoramento da governanca concluido.');
        return Command::SUCCESS;
    }
}
