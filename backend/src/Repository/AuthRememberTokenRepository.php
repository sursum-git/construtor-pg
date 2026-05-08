<?php

namespace App\Repository;

use App\Entity\AuthRememberToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthRememberTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthRememberToken::class);
    }

    public function findActiveByToken(string $token): ?AuthRememberToken
    {
        return $this->findOneBy([
            'tokenHash' => hash('sha256', $token),
            'status' => 'active',
        ]);
    }

    /**
     * @return AuthRememberToken[]
     */
    public function findActiveByTenantAndUser(string $tenantId, string $userId): array
    {
        return $this->findBy([
            'tenantId' => $tenantId,
            'userId' => $userId,
            'status' => 'active',
        ]);
    }
}
