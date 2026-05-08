<?php

namespace App\Repository;

use App\Entity\BuilderEntity;
use App\Entity\BuilderEntitySituation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BuilderEntitySituationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderEntitySituation::class);
    }

    /**
     * @return BuilderEntitySituation[]
     */
    public function findEnabledByEntity(BuilderEntity $entity): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.builderEntity = :entity')
            ->andWhere('s.enabled = true')
            ->setParameter('entity', $entity)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
