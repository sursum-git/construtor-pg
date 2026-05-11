<?php

namespace App\Repository;

use App\Entity\BuilderProgramVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BuilderProgramVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderProgramVersion::class);
    }

    /**
     * @return BuilderProgramVersion[]
     */
    public function findByProgramCodeOrdered(string $programCode): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.programCode = :programCode')
            ->setParameter('programCode', $programCode)
            ->orderBy('v.updatedAt', 'DESC')
            ->addOrderBy('v.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublishedByProgramCode(string $programCode): ?BuilderProgramVersion
    {
        return $this->findOneBy([
            'programCode' => $programCode,
            'status' => 'published',
        ]);
    }
}
