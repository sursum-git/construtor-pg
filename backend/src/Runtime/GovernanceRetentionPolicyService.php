<?php

namespace App\Runtime;

use App\Entity\SystemParameterValue;
use App\Repository\SystemParameterRepository;
use App\Repository\SystemParameterValueRepository;
use App\System\SystemParameterResolver;
use Doctrine\ORM\EntityManagerInterface;

class GovernanceRetentionPolicyService
{
    private const DEFINITIONS = [
        'changeRequestsDays' => [
            'parameterCode' => 'governance.retention.change_requests_days',
            'environmentKey' => 'PROGRAM_GOVERNANCE_RETENTION_CHANGE_REQUEST_DAYS',
            'default' => 180,
            'label' => 'Solicitacoes',
        ],
        'grantsDays' => [
            'parameterCode' => 'governance.retention.grants_days',
            'environmentKey' => 'PROGRAM_GOVERNANCE_RETENTION_GRANT_DAYS',
            'default' => 180,
            'label' => 'Grants',
        ],
        'approvalsDays' => [
            'parameterCode' => 'governance.retention.approvals_days',
            'environmentKey' => 'PROGRAM_GOVERNANCE_RETENTION_APPROVAL_DAYS',
            'default' => 365,
            'label' => 'Aprovacoes',
        ],
        'testExecutionsDays' => [
            'parameterCode' => 'governance.retention.test_executions_days',
            'environmentKey' => 'PROGRAM_GOVERNANCE_RETENTION_TEST_EXECUTION_DAYS',
            'default' => 365,
            'label' => 'Testes',
        ],
        'administrativeNotificationsDays' => [
            'parameterCode' => 'governance.retention.notifications_days',
            'environmentKey' => 'PROGRAM_GOVERNANCE_RETENTION_NOTIFICATION_DAYS',
            'default' => 30,
            'label' => 'Notificacoes',
        ],
    ];

    public function __construct(
        private readonly ?SystemParameterResolver $parameters = null,
        private readonly ?SystemParameterRepository $parameterRepository = null,
        private readonly ?SystemParameterValueRepository $valueRepository = null,
        private readonly ?EntityManagerInterface $entityManager = null,
    ) {
    }

    public function getPolicy(): array
    {
        $policy = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $policy[$key] = $this->readDays(
                (string) $definition['parameterCode'],
                (string) $definition['environmentKey'],
                (int) $definition['default'],
            );
        }

        return $policy;
    }

    public function describePolicy(): array
    {
        $policy = $this->getPolicy();
        $items = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $items[] = [
                'key' => $key,
                'label' => $definition['label'],
                'parameterCode' => $definition['parameterCode'],
                'days' => (int) ($policy[$key] ?? $definition['default']),
                'defaultDays' => (int) $definition['default'],
            ];
        }

        return [
            'items' => $items,
            'policy' => $policy,
        ];
    }

    public function updatePolicy(array $values): array
    {
        if (
            !$this->parameterRepository instanceof SystemParameterRepository
            || !$this->valueRepository instanceof SystemParameterValueRepository
            || !$this->entityManager instanceof EntityManagerInterface
        ) {
            throw new RuntimeHttpException('GOVERNANCE_RETENTION_STORAGE_UNAVAILABLE', 'Persistencia da politica de retencao nao esta disponivel.', 503);
        }

        foreach (self::DEFINITIONS as $key => $definition) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $days = (int) $values[$key];
            if ($days <= 0) {
                throw new RuntimeHttpException('GOVERNANCE_RETENTION_DAYS_INVALID', 'A retencao deve ser um numero positivo de dias.', 422, [
                    'key' => $key,
                    'value' => $values[$key],
                ]);
            }

            $parameter = $this->parameterRepository->findEnabledByCode((string) $definition['parameterCode']);
            if (!$parameter) {
                throw new RuntimeHttpException('GOVERNANCE_RETENTION_PARAMETER_NOT_FOUND', 'Parametro de retencao nao encontrado.', 404, [
                    'parameterCode' => $definition['parameterCode'],
                ]);
            }

            $current = $this->valueRepository->findBestValue($parameter, null);
            if (!$current instanceof SystemParameterValue) {
                $current = (new SystemParameterValue())
                    ->setParameter($parameter)
                    ->setEstablishmentCode(null)
                    ->setStartsAt(new \DateTimeImmutable())
                    ->setEndsAt(null)
                    ->setEnabled(true);
            }
            $current->setValue($days)->setEnabled(true);
            $this->entityManager->persist($current);
        }

        $this->entityManager->flush();

        return $this->describePolicy();
    }

    public function cutoff(string $policyKey): \DateTimeImmutable
    {
        $policy = $this->getPolicy();
        $days = max(1, (int) ($policy[$policyKey] ?? 180));

        return (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));
    }

    private function readDays(string $parameterCode, string $environmentKey, int $default): int
    {
        $days = null;
        if ($this->parameters instanceof SystemParameterResolver) {
            try {
                $resolved = $this->parameters->get($parameterCode);
                if (is_int($resolved)) {
                    $days = $resolved;
                } elseif (is_string($resolved) && preg_match('/^\d+$/', trim($resolved))) {
                    $days = (int) trim($resolved);
                }
            } catch (\Throwable) {
                $days = null;
            }
        }
        if ($days === null) {
            $value = (string) ($_ENV[$environmentKey] ?? $_SERVER[$environmentKey] ?? $default);
            $days = (int) trim($value);
        }

        return $days > 0 ? $days : $default;
    }
}
