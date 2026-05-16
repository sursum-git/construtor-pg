<?php

namespace App\Repository;

use App\Entity\ProgramChangeRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProgramChangeRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProgramChangeRequest::class);
    }

    public function findLatestForUser(string $programCode, ?string $builderEntityCode, string $userId): ?ProgramChangeRequest
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.programCode = :programCode')
            ->andWhere('r.requestedBy = :userId')
            ->setParameter('programCode', $programCode)
            ->setParameter('userId', $userId)
            ->orderBy('r.updatedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(1);

        if ($builderEntityCode !== null && $builderEntityCode !== '') {
            $qb->andWhere('(r.builderEntityCode = :builderEntityCode OR r.builderEntityCode IS NULL)')
                ->setParameter('builderEntityCode', $builderEntityCode);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
