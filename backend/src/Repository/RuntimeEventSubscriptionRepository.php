<?php

namespace App\Repository;

use App\Entity\RuntimeEventSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeEventSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeEventSubscription::class);
    }

    /**
     * @return RuntimeEventSubscription[]
     */
    public function findEnabledForEvent(string $tenantId, string $eventCode): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.eventCode = :eventCode')
            ->andWhere('s.enabled = true')
            ->andWhere('s.status = :status')
            ->andWhere('s.tenantId IN (:tenants)')
            ->setParameter('eventCode', $eventCode)
            ->setParameter('status', 'active')
            ->setParameter('tenants', ['default', $tenantId])
            ->addOrderBy('s.priority', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
