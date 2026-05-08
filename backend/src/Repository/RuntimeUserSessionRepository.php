<?php

namespace App\Repository;

use App\Entity\RuntimeUserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeUserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeUserSession::class);
    }

    public function findByTenantAndSession(string $tenantId, string $sessionId): ?RuntimeUserSession
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'sessionId' => $sessionId,
        ]);
    }
}
