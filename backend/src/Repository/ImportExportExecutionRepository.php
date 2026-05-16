<?php

namespace App\Repository;

use App\Entity\ImportExportExecution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ImportExportExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportExportExecution::class);
    }

    public function findRecent(int $limit = 50, ?string $mappingCode = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(max(1, min(200, $limit)));
        if ($mappingCode !== null && $mappingCode !== '') {
            $qb->andWhere('e.mappingCode = :mappingCode')->setParameter('mappingCode', $mappingCode);
        }

        return $qb->getQuery()->getResult();
    }
}
