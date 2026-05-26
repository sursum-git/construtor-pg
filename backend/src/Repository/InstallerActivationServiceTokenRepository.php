<?php

namespace App\Repository;

use App\Entity\InstallerActivationServiceToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InstallerActivationServiceTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstallerActivationServiceToken::class);
    }

    /**
     * @return list<InstallerActivationServiceToken>
     */
    public function findActiveCandidates(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('t.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByCode(string $code): ?InstallerActivationServiceToken
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.code = :code')
            ->setParameter('code', trim($code))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
