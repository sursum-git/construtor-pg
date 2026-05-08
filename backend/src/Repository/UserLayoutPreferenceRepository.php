<?php

namespace App\Repository;

use App\Entity\UserLayoutPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserLayoutPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLayoutPreference::class);
    }

    /**
     * @return UserLayoutPreference[]
     */
    public function findForUser(string $tenantId, string $userId, string $screenId, string $type): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.tenantId = :tenantId')
            ->andWhere('p.userId = :userId')
            ->andWhere('p.screenId = :screenId')
            ->andWhere('p.preferenceType = :type')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('userId', $userId)
            ->setParameter('screenId', $screenId)
            ->setParameter('type', $type)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
