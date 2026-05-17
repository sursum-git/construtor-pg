<?php

namespace App\Command;

use App\Runtime\ProgramGovernanceService;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:governance:operations', description: 'Executa a rotina operacional de governanca com integridade, monitoramento e limpeza.')]
class RunGovernanceOperationsCommand extends Command
{
    public function __construct(
        private readonly StructuralIntegrityService $integrity,
        private readonly ProgramGovernanceService $governance,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('cleanup-apply', null, InputOption::VALUE_NONE, 'Aplica a limpeza da retencao depois do preview.');
        $this->addOption('fail-on-issue', null, InputOption::VALUE_NONE, 'Retorna erro quando houver violacao de integridade ou alerta operacional.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $integrityResults = $this->integrity->verifyAll();
        $invalidIntegrity = count(array_filter($integrityResults, static fn (array $item): bool => ($item['status'] ?? '') !== 'valid'));

        $monitorSummary = $this->governance->emitOperationalAlerts();
        $retentionPreview = $this->governance->previewRetentionCleanup();
        $retentionApply = null;
        if ($input->getOption('cleanup-apply') === true) {
            $previewRunId = (int) ($retentionPreview['retentionRunId'] ?? 0);
            $retentionApply = $this->governance->executeRetentionCleanup($previewRunId > 0 ? $previewRunId : null);
        }

        $this->entityManager->flush();

        $io->table(
            ['Indicador', 'Total'],
            [
                ['Integridade invalida', (string) $invalidIntegrity],
                ['Programas monitorados', (string) ($monitorSummary['programs'] ?? 0)],
                ['Publicacoes bloqueadas', (string) ($monitorSummary['publishBlocked'] ?? 0)],
                ['Overlays bloqueados', (string) ($monitorSummary['blockedOverlays'] ?? 0)],
                ['Grants congelados/revogados', (string) ($monitorSummary['frozenOrRevokedGrants'] ?? 0)],
                ['Notificacoes emitidas', (string) ($monitorSummary['notifications'] ?? 0)],
                ['Retencao elegivel (preview)', (string) ($retentionPreview['totalRecords'] ?? 0)],
                ['Retencao aplicada', (string) ($retentionApply['totalRecords'] ?? 0)],
            ]
        );

        $hasIssue = $invalidIntegrity > 0
            || ((int) ($monitorSummary['publishBlocked'] ?? 0)) > 0
            || ((int) ($monitorSummary['blockedOverlays'] ?? 0)) > 0
            || ((int) ($monitorSummary['frozenOrRevokedGrants'] ?? 0)) > 0
            || ((int) ($monitorSummary['invalidIntegrity'] ?? 0)) > 0;

        if ($input->getOption('fail-on-issue') === true && $hasIssue) {
            $io->error('A rotina operacional encontrou pendencias bloqueantes.');
            return Command::FAILURE;
        }

        $io->success('Rotina operacional de governanca concluida.');
        return Command::SUCCESS;
    }
}
