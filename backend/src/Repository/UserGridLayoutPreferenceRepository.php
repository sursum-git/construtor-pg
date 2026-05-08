<?php

namespace App\Repository;

use App\Entity\UserGridLayoutPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserGridLayoutPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserGridLayoutPreference::class);
    }

    /**
     * @return UserGridLayoutPreference[]
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

    public function findOneForUser(string $tenantId, string $userId, string $screenId, string $layoutId): ?UserGridLayoutPreference
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'userId' => $userId,
            'screenId' => $screenId,
            'layoutId' => $layoutId,
        ]);
    }
}
