<?php

namespace App\Repository;

use App\Entity\SystemUpdateRelease;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemUpdateReleaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemUpdateRelease::class);
    }

    /**
     * @return list<SystemUpdateRelease>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.publishedAt', 'DESC')
            ->addOrderBy('r.checkedAt', 'DESC')
            ->addOrderBy('r.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByVersion(string $version): ?SystemUpdateRelease
    {
        return $this->findOneBy(['version' => trim($version)]);
    }
}
