<?php

namespace App\Repository;

use App\Entity\ImportExportSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ImportExportScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportExportSchedule::class);
    }

    public function findDue(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.enabled = true')
            ->andWhere('s.nextRunAt IS NOT NULL')
            ->andWhere('s.nextRunAt <= :now')
            ->setParameter('now', $now)
            ->orderBy('s.nextRunAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
