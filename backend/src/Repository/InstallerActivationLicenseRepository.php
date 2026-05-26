<?php

namespace App\Repository;

use App\Entity\InstallerActivationLicense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InstallerActivationLicenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstallerActivationLicense::class);
    }

    public function findOneBySubscriberCode(string $subscriberCode): ?InstallerActivationLicense
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.subscriberCode = :subscriberCode')
            ->setParameter('subscriberCode', trim($subscriberCode))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
