<?php

namespace App\Repository;

use App\Entity\UserGroupPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserGroupPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserGroupPreference::class);
    }

    /**
     * @return UserGroupPreference[]
     */
    public function findForUser(string $tenantId, string $userId, string $screenId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.tenantId = :tenantId')
            ->andWhere('p.userId = :userId')
            ->andWhere('p.screenId = :screenId')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('userId', $userId)
            ->setParameter('screenId', $screenId)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUser(string $tenantId, string $userId, string $screenId, string $groupId): ?UserGroupPreference
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'userId' => $userId,
            'screenId' => $screenId,
            'groupId' => $groupId,
        ]);
    }
}
