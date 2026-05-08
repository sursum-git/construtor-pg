<?php

namespace App\Repository;

use App\Entity\UserFilterPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserFilterPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFilterPreference::class);
    }

    /**
     * @return UserFilterPreference[]
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

    public function findOneForUser(string $tenantId, string $userId, string $screenId, string $filterId): ?UserFilterPreference
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'userId' => $userId,
            'screenId' => $screenId,
            'filterId' => $filterId,
        ]);
    }
}
