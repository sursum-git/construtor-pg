<?php

namespace App\Repository;

use App\Entity\RuntimeEndpoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RuntimeEndpointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuntimeEndpoint::class);
    }

    public function findEnabled(string $screenId, string $endpointId): ?RuntimeEndpoint
    {
        return $this->findOneBy([
            'screenId' => $screenId,
            'endpointId' => $endpointId,
            'enabled' => true,
        ]);
    }
}
