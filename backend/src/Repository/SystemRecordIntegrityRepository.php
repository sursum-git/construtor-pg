<?php

namespace App\Repository;

use App\Entity\SystemRecordIntegrity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemRecordIntegrityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemRecordIntegrity::class);
    }

    public function findOneByTarget(string $tableName, int $recordId): ?SystemRecordIntegrity
    {
        return $this->findOneBy([
            'tableName' => $tableName,
            'recordId' => $recordId,
        ]);
    }

    public function removeByTarget(string $tableName, int $recordId): void
    {
        $entity = $this->findOneByTarget($tableName, $recordId);
        if ($entity) {
            $this->getEntityManager()->remove($entity);
        }
    }
}
