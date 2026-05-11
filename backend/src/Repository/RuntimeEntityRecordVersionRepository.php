<?php

namespace App\Repository;

use App\Entity\RuntimeEntityRecordVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeEntityRecordVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeEntityRecordVersion::class);
    }

    public function findLatest(string $entityCode, string $recordId): ?RuntimeEntityRecordVersion
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.entityCode = :entityCode')
            ->andWhere('v.recordId = :recordId')
            ->setParameter('entityCode', $entityCode)
            ->setParameter('recordId', $recordId)
            ->orderBy('v.revision', 'DESC')
            ->addOrderBy('v.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<int> $ids
     * @return array<int, RuntimeEntityRecordVersion>
     */
    public function findIndexedByIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $items = $this->createQueryBuilder('v')
            ->andWhere('v.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($items as $item) {
            $indexed[$item->getId() ?? 0] = $item;
        }

        return $indexed;
    }
}
