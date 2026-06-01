<?php

namespace App\Repository;

use App\Entity\UserLookupUsage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserLookupUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLookupUsage::class);
    }

    public function findOneForUserValue(string $tenantId, string $userId, string $screenId, string $filterId, string $lookupValue): ?UserLookupUsage
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'userId' => $userId,
            'screenId' => $screenId,
            'filterId' => $filterId,
            'lookupValue' => $lookupValue,
        ]);
    }

    /**
     * @return UserLookupUsage[]
     */
    public function findFrequentForUser(string $tenantId, string $userId, string $screenId, string $filterId, ?string $fieldName, int $limit): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.tenantId = :tenantId')
            ->andWhere('u.userId = :userId')
            ->andWhere('u.screenId = :screenId')
            ->andWhere('u.filterId = :filterId')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('userId', $userId)
            ->setParameter('screenId', $screenId)
            ->setParameter('filterId', $filterId)
            ->orderBy('u.hits', 'DESC')
            ->addOrderBy('u.lastUsedAt', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($fieldName !== null && $fieldName !== '') {
            $qb
                ->andWhere('u.fieldName = :fieldName')
                ->setParameter('fieldName', $fieldName);
        }

        return $qb->getQuery()->getResult();
    }
}
