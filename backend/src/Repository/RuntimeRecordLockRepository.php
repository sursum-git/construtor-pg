<?php

namespace App\Repository;

use App\Entity\RuntimeRecordLock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeRecordLockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeRecordLock::class);
    }

    public function findActive(string $tenantId, string $entityCode, string|int $recordId): ?RuntimeRecordLock
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'entityCode' => $entityCode,
            'recordId' => (string) $recordId,
            'status' => 'active',
        ]);
    }

    public function findActiveByToken(string $token): ?RuntimeRecordLock
    {
        return $this->findOneBy([
            'lockToken' => $token,
            'status' => 'active',
        ]);
    }

    /**
     * @return RuntimeRecordLock[]
     */
    public function findActiveByUserOrSession(string $tenantId, string $userId, ?string $sessionId = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.tenantId = :tenantId')
            ->andWhere('l.status = :status')
            ->andWhere($sessionId ? '(l.lockedByUserId = :userId OR l.sessionId = :sessionId)' : 'l.lockedByUserId = :userId')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('status', 'active')
            ->setParameter('userId', $userId);

        if ($sessionId) {
            $qb->setParameter('sessionId', $sessionId);
        }

        return $qb->getQuery()->getResult();
    }
}
