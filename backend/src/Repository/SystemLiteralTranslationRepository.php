<?php

namespace App\Repository;

use App\Entity\SystemLiteralTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemLiteralTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemLiteralTranslation::class);
    }

    /**
     * @return list<SystemLiteralTranslation>
     */
    public function findEnabledByLocale(string $locale): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.locale = :locale')
            ->andWhere('l.enabled = true')
            ->setParameter('locale', trim($locale) ?: 'pt-BR')
            ->orderBy('l.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
