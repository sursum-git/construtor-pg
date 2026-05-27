<?php

namespace App\Repository;

use App\Entity\RuntimeAiMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeAiMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeAiMessage::class);
    }

    /**
     * @return RuntimeAiMessage[]
     */
    public function findRecentForSession(string $sessionId, int $limit = 20): array
    {
        $rows = $this->createQueryBuilder('m')
            ->andWhere('m.sessionId = :sessionId')
            ->setParameter('sessionId', trim($sessionId))
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return array_reverse($rows);
    }
}
