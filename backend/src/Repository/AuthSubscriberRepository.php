<?php

namespace App\Repository;

use App\Entity\AuthSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthSubscriber::class);
    }

    public function findEnabledByCode(string $code): ?AuthSubscriber
    {
        return $this->findOneBy([
            'code' => $code,
            'enabled' => true,
        ]);
    }

    /**
     * @return AuthSubscriber[]
     */
    public function findEnabledOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.enabled = true')
            ->addOrderBy('s.principal', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
