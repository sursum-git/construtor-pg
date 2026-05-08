<?php

namespace App\Repository;

use App\Entity\RuntimeLockPolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeLockPolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeLockPolicy::class);
    }

    public function findBest(?string $tenantId, ?string $programId, ?string $entityCode, ?string $actionId): ?RuntimeLockPolicy
    {
        $policies = $this->createQueryBuilder('p')
            ->andWhere('p.enabled = true')
            ->andWhere('(p.tenantId = :tenantId OR p.tenantId IS NULL)')
            ->andWhere('(p.programId = :programId OR p.programId IS NULL)')
            ->andWhere('(p.entityCode = :entityCode OR p.entityCode IS NULL)')
            ->andWhere('(p.actionId = :actionId OR p.actionId IS NULL)')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('programId', $programId)
            ->setParameter('entityCode', $entityCode)
            ->setParameter('actionId', $actionId)
            ->getQuery()
            ->getResult();

        usort($policies, static function (RuntimeLockPolicy $left, RuntimeLockPolicy $right): int {
            return self::score($right) <=> self::score($left);
        });

        return $policies[0] ?? null;
    }

    private static function score(RuntimeLockPolicy $policy): int
    {
        return ($policy->getProgramId() ? 8 : 0)
            + ($policy->getEntityCode() ? 4 : 0)
            + ($policy->getActionId() ? 2 : 0)
            + ($policy->getTenantId() ? 1 : 0);
    }
}
