<?php

namespace App\Repository;

use App\Entity\SystemParameter;
use App\Entity\SystemParameterValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemParameterValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemParameterValue::class);
    }

    public function findBestValue(SystemParameter $parameter, ?string $establishmentCode = null, ?\DateTimeImmutable $at = null): ?SystemParameterValue
    {
        $at ??= new \DateTimeImmutable();
        $establishmentCode = $establishmentCode === null ? null : trim($establishmentCode);
        if ($establishmentCode !== null && $establishmentCode !== '') {
            $specific = $this->findCurrentValue($parameter, $establishmentCode, $at);
            if ($specific) {
                return $specific;
            }
        }

        return $this->findCurrentValue($parameter, null, $at);
    }

    private function findCurrentValue(SystemParameter $parameter, ?string $establishmentCode, \DateTimeImmutable $at): ?SystemParameterValue
    {
        $builder = $this->createQueryBuilder('v')
            ->andWhere('v.parameter = :parameter')
            ->andWhere('v.enabled = true')
            ->andWhere('v.startsAt <= :at')
            ->andWhere('(v.endsAt IS NULL OR v.endsAt >= :at)')
            ->setParameter('parameter', $parameter)
            ->setParameter('at', $at)
            ->orderBy('v.startsAt', 'DESC')
            ->addOrderBy('v.id', 'DESC')
            ->setMaxResults(1);

        if ($establishmentCode === null || $establishmentCode === '') {
            $builder->andWhere('(v.establishmentCode IS NULL OR v.establishmentCode = :emptyEstablishmentCode)')
                ->setParameter('emptyEstablishmentCode', '');
        } else {
            $builder
                ->andWhere('v.establishmentCode = :establishmentCode')
                ->setParameter('establishmentCode', $establishmentCode);
        }

        return $builder->getQuery()->getOneOrNullResult();
    }
}
