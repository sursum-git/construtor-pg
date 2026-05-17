<?php

namespace App\Repository;

use App\Entity\ProgramGovernanceRetentionRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProgramGovernanceRetentionRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProgramGovernanceRetentionRun::class);
    }

    /**
     * @return list<ProgramGovernanceRetentionRun>
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function findPreviewById(int $id): ?ProgramGovernanceRetentionRun
    {
        $run = $this->find($id);
        if (!$run instanceof ProgramGovernanceRetentionRun) {
            return null;
        }

        return $run->getMode() === 'preview' ? $run : null;
    }

    /**
     * @return list<ProgramGovernanceRetentionRun>
     */
    public function findByExecutionGroup(string $executionGroup): array
    {
        $executionGroup = trim($executionGroup);
        if ($executionGroup === '') {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.executionGroup = :group')
            ->setParameter('group', $executionGroup)
            ->orderBy('r.createdAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
