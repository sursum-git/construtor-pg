<?php

namespace App\Repository;

use App\Entity\SystemParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemParameterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemParameter::class);
    }

    public function findEnabledByCode(string $code): ?SystemParameter
    {
        return $this->findOneBy([
            'code' => $code,
            'enabled' => true,
        ]);
    }
}
