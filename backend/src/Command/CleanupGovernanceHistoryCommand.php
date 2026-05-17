<?php

namespace App\Command;

use App\Runtime\GovernanceRetentionPolicyService;
use App\Runtime\ProgramGovernanceRetentionHistoryService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:governance:cleanup-history', description: 'Aplica a politica de retencao do historico de governanca.')]
class CleanupGovernanceHistoryCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly GovernanceRetentionPolicyService $retention,
        private readonly ProgramGovernanceRetentionHistoryService $history,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Aplica a limpeza. Sem a opcao, apenas mostra o impacto.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = $input->getOption('apply') === true;
        $policy = $this->retention->getPolicy();

        $targets = [
            [
                'label' => 'Solicitacoes resolvidas',
                'table' => 'program_change_request',
                'dateField' => 'updated_at',
                'where' => 'status IN (:statuses)',
                'params' => ['statuses' => ['rejected', 'revoked', 'consumed', 'expired']],
                'types' => ['statuses' => ArrayParameterType::STRING],
                'cutoffKey' => 'changeRequestsDays',
            ],
            [
                'label' => 'Grants encerrados',
                'table' => 'program_change_grant',
                'dateField' => 'updated_at',
                'where' => 'status IN (:statuses)',
                'params' => ['statuses' => ['consumed', 'revoked', 'expired']],
                'types' => ['statuses' => ArrayParameterType::STRING],
                'cutoffKey' => 'grantsDays',
            ],
            [
                'label' => 'Aprovacoes encerradas',
                'table' => 'program_publication_approval',
                'dateField' => 'updated_at',
                'where' => 'status IN (:statuses)',
                'params' => ['statuses' => ['approved', 'rejected', 'revoked', 'frozen']],
                'types' => ['statuses' => ArrayParameterType::STRING],
                'cutoffKey' => 'approvalsDays',
            ],
            [
                'label' => 'Execucoes de teste',
                'table' => 'program_test_execution',
                'dateField' => 'executed_at',
                'where' => '1 = 1',
                'params' => [],
                'types' => [],
                'cutoffKey' => 'testExecutionsDays',
            ],
            [
                'label' => 'Notificacoes administrativas',
                'table' => 'runtime_notification',
                'dateField' => 'created_at',
                'where' => 'category IN (:categories)',
                'params' => ['categories' => ['governanca', 'integridade']],
                'types' => ['categories' => ArrayParameterType::STRING],
                'cutoffKey' => 'administrativeNotificationsDays',
            ],
        ];

        $summary = [];
        foreach ($targets as $target) {
            $cutoff = $this->retention->cutoff($target['cutoffKey'])->format('Y-m-d H:i:s');
            $params = array_merge($target['params'], ['cutoff' => $cutoff]);
            $types = $target['types'];
            $count = (int) $this->connection->fetchOne(
                sprintf('SELECT COUNT(*) FROM %s WHERE %s AND %s < :cutoff', $target['table'], $target['where'], $target['dateField']),
                $params,
                $types
            );

            if ($apply && $count > 0) {
                if ($target['table'] === 'runtime_notification') {
                    $ids = $this->connection->fetchFirstColumn(
                        sprintf('SELECT id FROM %s WHERE %s AND %s < :cutoff', $target['table'], $target['where'], $target['dateField']),
                        $params,
                        $types
                    );
                    if ($ids) {
                        $normalizedIds = array_map('intval', $ids);
                        $this->connection->executeStatement(
                            'DELETE FROM runtime_notification_recipient WHERE notification_id IN (:ids)',
                            ['ids' => $normalizedIds],
                            ['ids' => ArrayParameterType::INTEGER]
                        );
                        $this->connection->executeStatement(
                            'DELETE FROM runtime_notification WHERE id IN (:ids)',
                            ['ids' => $normalizedIds],
                            ['ids' => ArrayParameterType::INTEGER]
                        );
                    }
                } else {
                    $this->connection->executeStatement(
                        sprintf('DELETE FROM %s WHERE %s AND %s < :cutoff', $target['table'], $target['where'], $target['dateField']),
                        $params,
                        $types
                    );
                }
            }

            $summary[] = [
                'alvo' => $target['label'],
                'tabela' => $target['table'],
                'dias' => (string) $policy[$target['cutoffKey']],
                'corte' => $cutoff,
                'registros' => (string) $count,
            ];
        }

        $report = [
            'mode' => $apply ? 'apply' : 'preview',
            'policy' => $this->retention->describePolicy(),
            'items' => array_map(static function (array $item): array {
                return [
                    'label' => $item['alvo'],
                    'table' => $item['tabela'],
                    'days' => (int) $item['dias'],
                    'cutoff' => $item['corte'],
                    'records' => (int) $item['registros'],
                ];
            }, $summary),
            'totalRecords' => array_sum(array_map(static fn (array $item): int => (int) $item['registros'], $summary)),
        ];
        $this->history->recordRun($report, $apply ? 'apply' : 'preview', 'system', 'cli');
        $this->entityManager->flush();

        $io->table(['Alvo', 'Tabela', 'Dias', 'Data de corte', 'Registros'], $summary);
        $io->success($apply ? 'Limpeza aplicada conforme a politica de retencao.' : 'Analise concluida sem aplicar exclusoes.');

        return Command::SUCCESS;
    }
}
