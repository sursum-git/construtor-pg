<?php

namespace App\Repository;

use App\Entity\SystemUpdateConsent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemUpdateConsentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemUpdateConsent::class);
    }

    /**
     * @return list<SystemUpdateConsent>
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function findLatestByVersion(string $version): ?SystemUpdateConsent
    {
        return $this->findLatestByVersionAndSubscriber($version, null);
    }

    public function findLatestByVersionAndSubscriber(string $version, ?string $subscriberCode): ?SystemUpdateConsent
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.releaseVersion = :version')
            ->setParameter('version', trim($version))
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(1);

        $normalizedSubscriber = trim((string) $subscriberCode);
        if ($normalizedSubscriber !== '') {
            $query
                ->andWhere('c.targetSubscriberCode = :subscriberCode')
                ->setParameter('subscriberCode', $normalizedSubscriber);
        } else {
            $query->andWhere('c.targetSubscriberCode IS NULL');
        }

        return $query->getQuery()->getOneOrNullResult();
    }
}
