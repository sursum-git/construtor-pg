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
            ->andWhere('m.type NOT IN (:excludedTypes)')
            ->andWhere('(m.targetUserId = :userId OR m.targetSessionId = :sessionId)')
            ->andWhere('(m.expiresAt IS NULL OR m.expiresAt > :now)')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('statuses', ['pending', 'delivered'])
            ->setParameter('excludedTypes', ['chat', 'support_chat'])
            ->setParameter('userId', $userId)
            ->setParameter('sessionId', $sessionId)
            ->setParameter('now', $now)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string[] $types
     *
     * @return RuntimeUserMessage[]
     */
    public function findConversation(string $tenantId, string $currentUserId, string $otherUserId, array $types, int $limit = 60): array
    {
        $allowedTypes = array_values(array_filter(array_map('strval', $types)));
        if ($otherUserId === '' || $allowedTypes === []) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.tenantId = :tenantId')
            ->andWhere('m.type IN (:types)')
            ->andWhere('((m.senderUserId = :currentUserId AND m.targetUserId = :otherUserId) OR (m.senderUserId = :otherUserId AND m.targetUserId = :currentUserId))')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('types', $allowedTypes)
            ->setParameter('currentUserId', $currentUserId)
            ->setParameter('otherUserId', $otherUserId)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string[] $types
     *
     * @return RuntimeUserMessage[]
     */
    public function findConversationAfterId(string $tenantId, string $currentUserId, string $otherUserId, array $types, int $afterId, int $limit = 40): array
    {
        $allowedTypes = array_values(array_filter(array_map('strval', $types)));
        if ($otherUserId === '' || $allowedTypes === []) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.tenantId = :tenantId')
            ->andWhere('m.type IN (:types)')
            ->andWhere('m.id > :afterId')
            ->andWhere('((m.senderUserId = :currentUserId AND m.targetUserId = :otherUserId) OR (m.senderUserId = :otherUserId AND m.targetUserId = :currentUserId))')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('types', $allowedTypes)
            ->setParameter('afterId', max(0, $afterId))
            ->setParameter('currentUserId', $currentUserId)
            ->setParameter('otherUserId', $otherUserId)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
}
