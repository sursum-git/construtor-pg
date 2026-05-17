<?php

namespace App\Runtime;

use App\Entity\ProgramGovernanceRetentionRun;
use App\Repository\ProgramGovernanceRetentionRunRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProgramGovernanceRetentionHistoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProgramGovernanceRetentionRunRepository $runs,
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
    ) {
    }

    public function recordRun(
        array $report,
        string $mode,
        string $executedBy,
        string $source,
        ?string $executionGroup = null,
        ?int $relatedRunId = null,
    ): ProgramGovernanceRetentionRun
    {
        $environment = $this->environmentIdentity->resolve();
        $run = (new ProgramGovernanceRetentionRun())
            ->setMode($mode)
            ->setSource($source)
            ->setExecutionGroup($executionGroup ?: ('ret-' . (new \DateTimeImmutable())->format('YmdHis')))
            ->setRelatedRunId($relatedRunId)
            ->setExecutedBy($executedBy)
            ->setDatabaseEnvironment((string) ($environment['databaseEnvironment'] ?? 'dev'))
            ->setDatabaseIdentity((string) ($environment['databaseIdentity'] ?? 'db:dev'))
            ->setTotalRecords((int) ($report['totalRecords'] ?? 0))
            ->setReport($report);

        $this->entityManager->persist($run);
        return $run;
    }

    public function findPreviewRun(int $id): ?ProgramGovernanceRetentionRun
    {
        return $this->runs->findPreviewById($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listExecutionGroup(string $executionGroup): array
    {
        return array_map(fn (ProgramGovernanceRetentionRun $run): array => $this->toPayload($run), $this->runs->findByExecutionGroup($executionGroup));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 20): array
    {
        $runs = $this->runs->findRecent($limit);
        $items = [];
        foreach ($runs as $index => $run) {
            $items[] = $this->toPayload($run, $runs[$index + 1] ?? null);
        }

        return $items;
    }

    private function toPayload(ProgramGovernanceRetentionRun $run, ?ProgramGovernanceRetentionRun $previous = null): array
    {
        $report = $run->getReport();
        $previousForDelta = $previous;
        $relatedRun = $run->getRelatedRunId() ? $this->runs->find($run->getRelatedRunId()) : null;
        if ($run->getMode() === 'apply' && $relatedRun instanceof ProgramGovernanceRetentionRun) {
            $previousForDelta = $relatedRun;
        }
        $deltaByTable = $this->buildDeltaByTable(
            is_array($report['items'] ?? null) ? $report['items'] : [],
            $previousForDelta instanceof ProgramGovernanceRetentionRun && is_array($previousForDelta->getReport()['items'] ?? null) ? $previousForDelta->getReport()['items'] : []
        );

        return [
            'id' => $run->getId(),
            'mode' => $run->getMode(),
            'source' => $run->getSource(),
            'executionGroup' => $run->getExecutionGroup(),
            'relatedRunId' => $run->getRelatedRunId(),
            'executedBy' => $run->getExecutedBy(),
            'databaseEnvironment' => $run->getDatabaseEnvironment(),
            'databaseIdentity' => $run->getDatabaseIdentity(),
            'totalRecords' => $run->getTotalRecords(),
            'createdAt' => $run->getCreatedAt()->format(DATE_ATOM),
            'items' => is_array($report['items'] ?? null) ? $report['items'] : [],
            'policy' => is_array($report['policy'] ?? null) ? $report['policy'] : [],
            'deltaTotalRecords' => array_sum(array_map(static fn (array $item): int => (int) ($item['deltaRecords'] ?? 0), $deltaByTable)),
            'deltaByTable' => $deltaByTable,
            'previousRunId' => $previous?->getId(),
            'previousMode' => $previous?->getMode(),
            'beforeAfterLabel' => $this->buildBeforeAfterLabel($run->getMode(), $run->getRelatedRunId(), $previous?->getMode()),
            'pairedRun' => $relatedRun instanceof ProgramGovernanceRetentionRun ? [
                'id' => $relatedRun->getId(),
                'mode' => $relatedRun->getMode(),
                'createdAt' => $relatedRun->getCreatedAt()->format(DATE_ATOM),
                'totalRecords' => $relatedRun->getTotalRecords(),
            ] : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $currentItems
     * @param list<array<string, mixed>> $previousItems
     * @return list<array<string, mixed>>
     */
    private function buildDeltaByTable(array $currentItems, array $previousItems): array
    {
        $previousMap = [];
        foreach ($previousItems as $item) {
            $previousMap[(string) ($item['table'] ?? '')] = (int) ($item['records'] ?? 0);
        }

        $rows = [];
        foreach ($currentItems as $item) {
            $table = (string) ($item['table'] ?? '');
            $current = (int) ($item['records'] ?? 0);
            $previous = (int) ($previousMap[$table] ?? 0);
            $rows[] = [
                'table' => $table,
                'label' => (string) ($item['label'] ?? $table),
                'records' => $current,
                'previousRecords' => $previous,
                'deltaRecords' => $current - $previous,
            ];
        }

        return $rows;
    }

    private function buildBeforeAfterLabel(string $mode, ?int $relatedRunId, ?string $previousMode): string
    {
        if ($mode === 'apply' && $relatedRunId) {
            return 'Aplicacao relacionada ao preview #' . $relatedRunId;
        }
        if ($mode === 'preview' && $previousMode === 'apply') {
            return 'Preview apos execucao anterior';
        }
        if ($mode === 'preview') {
            return 'Preview da politica atual';
        }

        return 'Execucao aplicada';
    }
}
