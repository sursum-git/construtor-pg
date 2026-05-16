<?php

namespace App\Repository;

use App\Entity\ImportExportMapping;
use App\Entity\ImportExportMappingVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ImportExportMappingVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportExportMappingVersion::class);
    }

    public function findLatestVersionNumber(ImportExportMapping $mapping): int
    {
        $value = $this->createQueryBuilder('v')
            ->select('MAX(v.versionNumber)')
            ->andWhere('v.mapping = :mapping')
            ->setParameter('mapping', $mapping)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($value ?: 0);
    }

    public function findByMapping(ImportExportMapping $mapping, int $limit = 50): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.mapping = :mapping')
            ->setParameter('mapping', $mapping)
            ->orderBy('v.versionNumber', 'DESC')
            ->setMaxResults(max(1, min(200, $limit)))
            ->getQuery()
            ->getResult();
    }
}
