<?php

namespace App\Repository;

use App\Entity\BuilderProgramOverlayVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BuilderProgramOverlayVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderProgramOverlayVersion::class);
    }

    public function findPublishedVariant(string $programCode, string $subscriberId, array $kinds = ['customer_custom', 'customer_overlay']): ?BuilderProgramOverlayVersion
    {
        return $this->createQueryBuilder('v')
            ->innerJoin('v.overlay', 'o')
            ->andWhere('o.programCode = :programCode')
            ->andWhere('o.subscriberId = :subscriberId')
            ->andWhere('o.status = :overlayStatus')
            ->andWhere('v.status = :versionStatus')
            ->andWhere('o.customizationKind IN (:kinds)')
            ->setParameter('programCode', $programCode)
            ->setParameter('subscriberId', $subscriberId)
            ->setParameter('overlayStatus', 'published')
            ->setParameter('versionStatus', 'published')
            ->setParameter('kinds', $kinds)
            ->addOrderBy('o.customizationKind', 'ASC')
            ->addOrderBy('v.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestByOverlayId(int $overlayId): ?BuilderProgramOverlayVersion
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.overlay = :overlayId')
            ->setParameter('overlayId', $overlayId)
            ->orderBy('v.versionNumber', 'DESC')
            ->addOrderBy('v.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return BuilderProgramOverlayVersion[]
     */
    public function findByOverlayIdOrdered(int $overlayId): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.overlay = :overlayId')
            ->setParameter('overlayId', $overlayId)
            ->orderBy('v.versionNumber', 'DESC')
            ->addOrderBy('v.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
