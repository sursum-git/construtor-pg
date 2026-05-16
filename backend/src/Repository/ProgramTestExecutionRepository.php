<?php

namespace App\Repository;

use App\Entity\ProgramTestExecution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProgramTestExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProgramTestExecution::class);
    }

    /**
     * @return ProgramTestExecution[]
     */
    public function findByBundle(string $programCode, int $builderProgramVersionId, string $bundleId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.programCode = :programCode')
            ->andWhere('t.builderProgramVersionId = :builderProgramVersionId')
            ->andWhere('t.bundleId = :bundleId')
            ->setParameter('programCode', $programCode)
            ->setParameter('builderProgramVersionId', $builderProgramVersionId)
            ->setParameter('bundleId', $bundleId)
            ->orderBy('t.executedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
