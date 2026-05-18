<?php

namespace App\Repository;

use App\Entity\SystemUpdateTenantActivation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemUpdateTenantActivationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemUpdateTenantActivation::class);
    }

    /**
     * @return list<SystemUpdateTenantActivation>
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function findLatestByVersionAndSubscriber(string $version, string $subscriberCode): ?SystemUpdateTenantActivation
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.releaseVersion = :version')
            ->andWhere('a.targetSubscriberCode = :subscriberCode')
            ->setParameter('version', trim($version))
            ->setParameter('subscriberCode', trim($subscriberCode))
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
