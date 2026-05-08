<?php

namespace App\Repository;

use App\Entity\RuntimeUserMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeUserMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeUserMessage::class);
    }

    /**
     * @return RuntimeUserMessage[]
     */
    public function findPendingForTarget(string $tenantId, string $userId, string $sessionId): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->andWhere('m.tenantId = :tenantId')
            ->andWhere('m.status IN (:statuses)')
            ->andWhere('(m.targetUserId = :userId OR m.targetSessionId = :sessionId)')
            ->andWhere('(m.expiresAt IS NULL OR m.expiresAt > :now)')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('statuses', ['pending', 'delivered'])
            ->setParameter('userId', $userId)
            ->setParameter('sessionId', $sessionId)
            ->setParameter('now', $now)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }
}
