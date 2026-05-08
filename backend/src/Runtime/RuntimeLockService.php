<?php

namespace App\Runtime;

use App\Entity\RuntimeLockPolicy;
use App\Entity\RuntimeRecordLock;
use App\Repository\RuntimeLockPolicyRepository;
use App\Repository\RuntimeRecordLockRepository;
use Doctrine\ORM\EntityManagerInterface;

class RuntimeLockService
{
    public function __construct(
        private readonly RuntimeLockPolicyRepository $policies,
        private readonly RuntimeRecordLockRepository $locks,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeTransactionService $transactions,
    ) {
    }

    public function acquire(string $screenId, array $payload): array
    {
        [$entityCode, $recordId, $programId, $actionId] = $this->resolveScope($screenId, $payload);
        if (!$entityCode || !$recordId) {
            throw new RuntimeHttpException('LOCK_TARGET_REQUIRED', 'Informe entidade e registro para controlar o semaforo.', 422);
        }

        $policy = $this->resolvePolicy($programId, $entityCode, $actionId);
        if ($policy['mode'] === 'none') {
            return [
                'lock' => [
                    'status' => 'none',
                    'mode' => 'none',
                    'policy' => $policy,
                ],
            ];
        }

        $this->expireStaleLocks();
        $active = $this->locks->findActive($this->permissions->getTenantId(), $entityCode, $recordId);
        if ($active && $this->isOwnedByCurrentSession($active)) {
            $this->refreshLock($active, $policy);
            $this->transactions->log('lock.reused', 'Semaforo reutilizado pela sessao atual.', metadata: [
                'lockToken' => $active->getLockToken(),
                'entityCode' => $entityCode,
                'recordId' => (string) $recordId,
            ]);

            return $this->formatLockResponse($active, 'acquired', $policy);
        }

        if ($active && $policy['mode'] === 'block') {
            throw new RuntimeHttpException('RECORD_LOCKED', 'Este registro esta sendo alterado por outro usuario.', 409, [
                'ownerName' => $active->getLockedByUserName() ?: $active->getLockedByUserId(),
                'ownerUserId' => $active->getLockedByUserId(),
                'expiresAt' => $active->getExpiresAt()->format(DATE_ATOM),
            ]);
        }

        if ($active && $policy['mode'] === 'warn') {
            return [
                'lock' => [
                    'status' => 'warn',
                    'mode' => 'warn',
                    'owner' => $this->formatOwner($active),
                    'expiresAt' => $active->getExpiresAt()->format(DATE_ATOM),
                    'heartbeatIntervalSeconds' => $policy['heartbeatIntervalSeconds'],
                    'policy' => $policy,
                ],
            ];
        }

        $token = bin2hex(random_bytes(24));
        $user = $this->permissions->getCurrentUserPayload();
        $lock = (new RuntimeRecordLock())
            ->setTenantId($this->permissions->getTenantId())
            ->setProgramId($programId)
            ->setEntityCode($entityCode)
            ->setRecordId($recordId)
            ->setActionId($actionId)
            ->setLockToken($token)
            ->setTransaction($this->transactions->getCurrent())
            ->setLockedByUserId($this->permissions->getUserId())
            ->setLockedByUserName($user['name'] ?? null)
            ->setSessionId($this->permissions->getSessionId())
            ->setMode($policy['mode'])
            ->setMetadata([
                'screenId' => $screenId,
                'expectedVersion' => $payload['expectedVersion'] ?? ($payload['_runtime']['version'] ?? null),
            ]);
        $this->refreshLock($lock, $policy);
        $this->entityManager->persist($lock);
        $this->transactions->log('lock.acquired', 'Semaforo adquirido.', metadata: [
            'lockToken' => $token,
            'entityCode' => $entityCode,
            'recordId' => (string) $recordId,
        ]);

        return $this->formatLockResponse($lock, 'acquired', $policy);
    }

    public function heartbeat(array $payload): array
    {
        $token = (string) ($payload['lockToken'] ?? $payload['_runtime']['lockToken'] ?? '');
        $lock = $token ? $this->locks->findActiveByToken($token) : null;
        if (!$lock || !$this->isOwnedByCurrentSession($lock)) {
            throw new RuntimeHttpException('LOCK_EXPIRED', 'O semaforo deste registro expirou.', 409);
        }

        $policy = $this->resolvePolicy($lock->getProgramId(), $lock->getEntityCode(), $lock->getActionId());
        $this->refreshLock($lock, $policy);
        $this->transactions->log('lock.heartbeat', 'Heartbeat do semaforo recebido.', metadata: [
            'lockToken' => $lock->getLockToken(),
        ]);

        return $this->formatLockResponse($lock, 'active', $policy);
    }

    public function release(array $payload, string $status = 'released'): array
    {
        $token = (string) ($payload['lockToken'] ?? $payload['_runtime']['lockToken'] ?? '');
        $lock = $token ? $this->locks->findActiveByToken($token) : null;
        if (!$lock) {
            return ['ok' => true, 'released' => false];
        }
        if (!$this->isOwnedByCurrentSession($lock)) {
            throw new RuntimeHttpException('LOCK_FORBIDDEN', 'Semaforo pertence a outra sessao.', 403);
        }

        $lock->release($status);
        $this->transactions->log('lock.released', 'Semaforo liberado.', metadata: [
            'lockToken' => $lock->getLockToken(),
            'status' => $status,
        ]);

        return ['ok' => true, 'released' => true];
    }

    public function validateWriteLock(string $entityCode, string|int $recordId, string $actionId, array $payload): void
    {
        $programId = (string) ($payload['programId'] ?? $payload['context']['programId'] ?? '');
        $policy = $this->resolvePolicy($programId ?: null, $entityCode, $actionId);
        if ($policy['mode'] !== 'block') {
            $active = $this->locks->findActive($this->permissions->getTenantId(), $entityCode, $recordId);
            if ($active && !$this->isOwnedByCurrentSession($active)) {
                $this->transactions->log('lock.warn.write', 'Gravacao com outro usuario no semaforo.', metadata: [
                    'ownerUserId' => $active->getLockedByUserId(),
                    'recordId' => (string) $recordId,
                ]);
            }
            return;
        }

        $token = (string) ($payload['_runtime']['lockToken'] ?? $payload['lockToken'] ?? '');
        if (!$token) {
            throw new RuntimeHttpException('LOCK_REQUIRED', 'Este registro exige semaforo ativo para gravar.', 409);
        }

        $lock = $this->locks->findActiveByToken($token);
        if (!$lock || !$this->isOwnedByCurrentSession($lock) || $lock->getEntityCode() !== $entityCode || $lock->getRecordId() !== (string) $recordId) {
            throw new RuntimeHttpException('LOCK_EXPIRED', 'O semaforo deste registro expirou ou pertence a outra sessao.', 409);
        }
    }

    public function releaseUserLocks(string $targetUserId, ?string $targetSessionId, string $status = 'revoked'): int
    {
        $locks = $this->locks->findActiveByUserOrSession($this->permissions->getTenantId(), $targetUserId, $targetSessionId);
        foreach ($locks as $lock) {
            $lock->release($status);
        }
        return count($locks);
    }

    public function resolvePolicy(?string $programId, ?string $entityCode, ?string $actionId): array
    {
        $policy = $this->policies->findBest($this->permissions->getTenantId(), $programId, $entityCode, $actionId);
        if (!$policy) {
            return [
                'source' => 'default',
                'mode' => in_array($actionId, ['edit', 'update', 'delete'], true) ? 'block' : 'none',
                'stalePolicy' => 'block',
                'lockTtlSeconds' => 300,
                'heartbeatIntervalSeconds' => 60,
            ];
        }

        return [
            'source' => $this->policySource($policy),
            'mode' => $policy->getMode(),
            'stalePolicy' => $policy->getStalePolicy(),
            'lockTtlSeconds' => $policy->getLockTtlSeconds(),
            'heartbeatIntervalSeconds' => $policy->getHeartbeatIntervalSeconds(),
            'handlerId' => $policy->getHandlerId(),
            'condition' => $policy->getConditionConfig(),
        ];
    }

    private function resolveScope(string $screenId, array $payload): array
    {
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $entityCode = (string) ($payload['entityCode'] ?? $record['entityCode'] ?? ($screenId === 'cadastros.clientes' ? 'cliente' : ''));
        $recordId = $payload['recordId'] ?? $payload['id'] ?? $record['id'] ?? null;
        $programId = (string) ($payload['programId'] ?? $context['programId'] ?? '');
        $actionId = (string) ($payload['actionId'] ?? $payload['action'] ?? $payload['mode'] ?? 'edit');

        return [$entityCode, $recordId, $programId ?: null, $actionId ?: null];
    }

    private function refreshLock(RuntimeRecordLock $lock, array $policy): void
    {
        $now = new \DateTimeImmutable();
        $lock
            ->setLastSeenAt($now)
            ->setExpiresAt($now->modify('+' . (int) $policy['lockTtlSeconds'] . ' seconds'));
    }

    private function expireStaleLocks(): void
    {
        $now = new \DateTimeImmutable();
        $items = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(RuntimeRecordLock::class, 'l')
            ->andWhere('l.status = :status')
            ->andWhere('l.expiresAt < :now')
            ->setParameter('status', 'active')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($items as $lock) {
            $lock->release('expired');
        }
    }

    private function isOwnedByCurrentSession(RuntimeRecordLock $lock): bool
    {
        return $lock->getSessionId() === $this->permissions->getSessionId()
            || $lock->getLockedByUserId() === $this->permissions->getUserId();
    }

    private function formatLockResponse(RuntimeRecordLock $lock, string $status, array $policy): array
    {
        return [
            'lock' => [
                'status' => $status,
                'mode' => $lock->getMode(),
                'token' => $lock->getLockToken(),
                'transactionId' => $this->transactions->getCurrent()?->getId(),
                'expiresAt' => $lock->getExpiresAt()->format(DATE_ATOM),
                'heartbeatIntervalSeconds' => $policy['heartbeatIntervalSeconds'],
                'owner' => null,
                'policy' => $policy,
            ],
            '_runtime' => [
                'lockToken' => $lock->getLockToken(),
                'transactionId' => $this->transactions->getCurrent()?->getId(),
            ],
        ];
    }

    private function formatOwner(RuntimeRecordLock $lock): array
    {
        return [
            'userId' => $lock->getLockedByUserId(),
            'name' => $lock->getLockedByUserName() ?: $lock->getLockedByUserId(),
            'sessionId' => $lock->getSessionId(),
        ];
    }

    private function policySource(RuntimeLockPolicy $policy): string
    {
        if ($policy->getProgramId() && $policy->getEntityCode() && $policy->getActionId()) {
            return 'program_entity_action';
        }
        if ($policy->getProgramId() && $policy->getEntityCode()) {
            return 'program_entity';
        }
        if ($policy->getEntityCode() && $policy->getActionId()) {
            return 'entity_action';
        }
        if ($policy->getEntityCode()) {
            return 'entity';
        }
        return 'global';
    }
}
