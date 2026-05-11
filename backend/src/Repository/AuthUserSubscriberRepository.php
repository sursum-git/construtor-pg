<?php

namespace App\Repository;

use App\Entity\AuthUserSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthUserSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthUserSubscriber::class);
    }

    /**
     * @return AuthUserSubscriber[]
     */
    public function findEnabledForUser(string $userTenantId, string $username): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.userTenantId = :tenantId')
            ->andWhere('a.username = :username')
            ->andWhere('a.enabled = true')
            ->setParameter('tenantId', $userTenantId)
            ->setParameter('username', mb_strtolower(trim($username)))
            ->addOrderBy('a.defaultSubscriber', 'DESC')
            ->addOrderBy('a.subscriberCode', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneEnabledForUserAndSubscriber(string $userTenantId, string $username, string $subscriberCode): ?AuthUserSubscriber
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.userTenantId = :tenantId')
            ->andWhere('a.username = :username')
            ->andWhere('a.subscriberCode = :subscriberCode')
            ->andWhere('a.enabled = true')
            ->setParameter('tenantId', $userTenantId)
            ->setParameter('username', mb_strtolower(trim($username)))
            ->setParameter('subscriberCode', $subscriberCode)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
