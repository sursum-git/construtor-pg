<?php

namespace App\Repository;

use App\Entity\AuthLoginChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthLoginChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthLoginChallenge::class);
    }

    public function findActiveByToken(string $token): ?AuthLoginChallenge
    {
        return $this->findOneBy([
            'tokenHash' => hash('sha256', $token),
            'status' => 'pending',
        ]);
    }
}
