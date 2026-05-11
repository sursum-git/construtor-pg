<?php

namespace App\Repository;

use App\Entity\BuilderEntityVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BuilderEntityVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderEntityVersion::class);
    }

    /**
     * @return BuilderEntityVersion[]
     */
    public function findByEntityCodeOrdered(string $entityCode): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.builderEntityCode = :entityCode')
            ->setParameter('entityCode', $entityCode)
            ->orderBy('v.updatedAt', 'DESC')
            ->addOrderBy('v.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCurrentByEntityCode(string $entityCode): ?BuilderEntityVersion
    {
        return $this->findOneBy([
            'builderEntityCode' => $entityCode,
            'status' => 'current',
        ]);
    }

    public function nextRevision(string $entityCode): int
    {
        $value = $this->createQueryBuilder('v')
            ->select('MAX(v.revision)')
            ->andWhere('v.builderEntityCode = :entityCode')
            ->setParameter('entityCode', $entityCode)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $value) + 1;
    }
}
