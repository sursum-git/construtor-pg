<?php

namespace App\Repository;

use App\Entity\BuilderEntity;
use App\Entity\BuilderEntitySituationTransition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BuilderEntitySituationTransitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderEntitySituationTransition::class);
    }

    /**
     * @return BuilderEntitySituationTransition[]
     */
    public function findEnabledByEntity(BuilderEntity $entity): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.builderEntity = :entity')
            ->andWhere('t.enabled = true')
            ->setParameter('entity', $entity)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
