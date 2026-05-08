<?php

namespace App\Repository;

use App\Entity\AuthProviderConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthProviderConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthProviderConfig::class);
    }

    public function findEnabledByCode(string $code): ?AuthProviderConfig
    {
        return $this->findOneBy([
            'code' => $code,
            'enabled' => true,
        ]);
    }

    /**
     * @return AuthProviderConfig[]
     */
    public function findEnabledOrdered(): array
    {
        return $this->findBy(['enabled' => true], ['priority' => 'ASC', 'name' => 'ASC']);
    }
}
