<?php

namespace App\Repository;

use App\Entity\RuntimeAsyncJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeAsyncJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeAsyncJob::class);
    }

    /**
     * @return RuntimeAsyncJob[]
     */
    public function findRecentByJobType(string $jobType, int $limit = 20): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.jobType = :jobType')
            ->setParameter('jobType', $jobType)
            ->addOrderBy('j.createdAt', 'DESC')
            ->setMaxResults(max(1, min(200, $limit)))
            ->getQuery()
            ->getResult();
    }
}
