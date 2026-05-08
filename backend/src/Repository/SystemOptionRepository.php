<?php

namespace App\Repository;

use App\Entity\SystemOption;
use App\Entity\SystemOptionList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemOption::class);
    }

    public function findActiveByCode(SystemOptionList $list, string $code): ?SystemOption
    {
        return $this->findOneBy([
            'optionList' => $list,
            'code' => $code,
            'enabled' => true,
        ]);
    }

    /**
     * @param string[] $codes
     * @return SystemOption[]
     */
    public function findActiveByCodes(SystemOptionList $list, array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map('strval', $codes))));
        if (!$codes) {
            return [];
        }

        return $this->createQueryBuilder('o')
            ->andWhere('o.optionList = :list')
            ->andWhere('o.enabled = true')
            ->andWhere('o.code IN (:codes)')
            ->setParameter('list', $list)
            ->setParameter('codes', $codes)
            ->getQuery()
            ->getResult();
    }
}
