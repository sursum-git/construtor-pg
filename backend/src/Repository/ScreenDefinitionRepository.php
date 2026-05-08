<?php

namespace App\Repository;

use App\Entity\ScreenDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ScreenDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScreenDefinition::class);
    }

    public function findPublishedByScreenId(string $screenId): ?ScreenDefinition
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.screenId = :screenId')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('screenId', $screenId)
            ->setParameter('statuses', ['published', 'draft'])
            ->orderBy('s.status', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
