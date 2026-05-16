<?php

namespace App\Repository;

use App\Entity\ProgramChangeGrant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProgramChangeGrantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProgramChangeGrant::class);
    }

    public function findActiveForUser(string $programCode, ?string $builderEntityCode, string $userId): ?ProgramChangeGrant
    {
        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.request', 'r')
            ->andWhere('g.programCode = :programCode')
            ->andWhere('g.grantedToUserId = :userId')
            ->andWhere('g.status = :status')
            ->setParameter('programCode', $programCode)
            ->setParameter('userId', $userId)
            ->setParameter('status', 'active')
            ->orderBy('g.updatedAt', 'DESC')
            ->setMaxResults(1);

        if ($builderEntityCode !== null && $builderEntityCode !== '') {
            $qb->andWhere('(g.builderEntityCode = :builderEntityCode OR g.builderEntityCode IS NULL)')
                ->setParameter('builderEntityCode', $builderEntityCode);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneForUserById(int $id, string $userId): ?ProgramChangeGrant
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.id = :id')
            ->andWhere('g.grantedToUserId = :userId')
            ->setParameter('id', $id)
            ->setParameter('userId', $userId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
