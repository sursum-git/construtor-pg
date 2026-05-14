<?php

namespace App\Repository;

use App\Entity\BuilderEditorLock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BuilderEditorLockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderEditorLock::class);
    }

    public function findActiveByScope(string $scopeType, string $scopeCode, string $tenantId): ?BuilderEditorLock
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.scopeType = :scopeType')
            ->andWhere('l.scopeCode = :scopeCode')
            ->andWhere('l.tenantId = :tenantId')
            ->andWhere('l.status = :status')
            ->setParameter('scopeType', $scopeType)
            ->setParameter('scopeCode', $scopeCode)
            ->setParameter('tenantId', $tenantId)
            ->setParameter('status', 'active')
            ->orderBy('l.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveByToken(string $token, string $tenantId): ?BuilderEditorLock
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'lockToken' => $token,
            'status' => 'active',
        ]);
    }
}
