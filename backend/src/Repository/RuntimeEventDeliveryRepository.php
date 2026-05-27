<?php

namespace App\Repository;

use App\Entity\RuntimeEventDelivery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeEventDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeEventDelivery::class);
    }

    public function findOneByIdempotencyKey(string $idempotencyKey): ?RuntimeEventDelivery
    {
        return $this->findOneBy(['idempotencyKey' => $idempotencyKey]);
    }
}
