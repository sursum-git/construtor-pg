<?php

namespace App\Repository;

use App\Entity\AuthPasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthPasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthPasswordResetToken::class);
    }

    public function findActiveByToken(string $token): ?AuthPasswordResetToken
    {
        return $this->findOneBy([
            'tokenHash' => hash('sha256', $token),
            'status' => 'active',
        ]);
    }

    /**
     * @return AuthPasswordResetToken[]
     */
    public function findActiveForUser(string $tenantId, string $username): array
    {
        return $this->findBy([
            'userTenantId' => $tenantId,
            'username' => mb_strtolower(trim($username)),
            'status' => 'active',
        ]);
    }
}
