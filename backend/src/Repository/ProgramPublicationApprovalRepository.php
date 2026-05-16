<?php

namespace App\Repository;

use App\Entity\ProgramPublicationApproval;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProgramPublicationApprovalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProgramPublicationApproval::class);
    }

    public function findActiveApproval(string $programCode, int $builderProgramVersionId): ?ProgramPublicationApproval
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.programCode = :programCode')
            ->andWhere('a.builderProgramVersionId = :builderProgramVersionId')
            ->andWhere('a.status = :status')
            ->setParameter('programCode', $programCode)
            ->setParameter('builderProgramVersionId', $builderProgramVersionId)
            ->setParameter('status', 'approved')
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
