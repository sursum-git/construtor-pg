<?php

namespace App\Repository;

use App\Entity\RuntimeUserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeUserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeUserSession::class);
    }

    public function findByTenantAndSession(string $tenantId, string $sessionId): ?RuntimeUserSession
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'sessionId' => $sessionId,
        ]);
    }

    /**
     * @return RuntimeUserSession[]
     */
    public function findActiveByTenant(string $tenantId, ?string $excludeUserId = null): array
    {
        $query = $this->createQueryBuilder('s')
            ->andWhere('s.tenantId = :tenantId')
            ->andWhere('s.status = :status')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('status', 'active')
            ->orderBy('s.lastSeenAt', 'DESC');

        if ($excludeUserId !== null && $excludeUserId !== '') {
            $query
                ->andWhere('s.userId <> :excludeUserId')
                ->setParameter('excludeUserId', $excludeUserId);
        }

        return $query->getQuery()->getResult();
    }
}
