<?php

namespace App\Repository;

use App\Entity\RuntimeAiSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeAiSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeAiSession::class);
    }

    public function findOneBySessionId(string $sessionId): ?RuntimeAiSession
    {
        return $this->findOneBy(['sessionId' => trim($sessionId)]);
    }

    public function findOwned(string $sessionId, string $tenantId, string $userId, ?string $subscriberCode, string $purpose): ?RuntimeAiSession
    {
        $criteria = [
            'sessionId' => trim($sessionId),
            'tenantId' => trim($tenantId),
            'userId' => trim($userId),
            'purpose' => trim($purpose),
        ];

        if (trim((string) $subscriberCode) !== '') {
            $criteria['subscriberCode'] = trim((string) $subscriberCode);
        }

        return $this->findOneBy($criteria);
    }
}
