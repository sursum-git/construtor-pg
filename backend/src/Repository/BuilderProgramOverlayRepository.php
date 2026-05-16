<?php

namespace App\Repository;

use App\Entity\BuilderProgramOverlay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BuilderProgramOverlayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderProgramOverlay::class);
    }

    public function findOneByIdentity(string $programCode, string $subscriberId, string $customizationKind): ?BuilderProgramOverlay
    {
        return $this->findOneBy([
            'programCode' => $programCode,
            'subscriberId' => $subscriberId,
            'customizationKind' => $customizationKind,
        ]);
    }
}
