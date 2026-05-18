<?php

namespace App\Repository;

use App\Entity\SystemUpdateExecution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemUpdateExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemUpdateExecution::class);
    }

    /**
     * @return list<SystemUpdateExecution>
     */
    public function findRecent(int $limit = 30): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<SystemUpdateExecution>
     */
    public function findRecentBySubscriber(?string $subscriberCode, int $limit = 50): array
    {
        $query = $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(max(1, $limit));

        $normalizedSubscriber = trim((string) $subscriberCode);
        if ($normalizedSubscriber !== '') {
            $query
                ->andWhere('e.targetSubscriberCode = :subscriberCode')
                ->setParameter('subscriberCode', $normalizedSubscriber);
        } else {
            $query->andWhere('e.targetSubscriberCode IS NOT NULL');
        }

        return $query->getQuery()->getResult();
    }

    public function findLatestSuccessfulVersion(): ?string
    {
        return $this->findLatestSuccessfulVersionBySubscriber(null);
    }

    public function findLatestSuccessfulVersionBySubscriber(?string $subscriberCode): ?string
    {
        $query = $this->createQueryBuilder('e')
            ->select('e.releaseVersion')
            ->andWhere('e.status = :status')
            ->setParameter('status', 'succeeded')
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(1);

        $normalizedSubscriber = trim((string) $subscriberCode);
        if ($normalizedSubscriber !== '') {
            $query
                ->andWhere('e.targetSubscriberCode = :subscriberCode')
                ->setParameter('subscriberCode', $normalizedSubscriber);
        }

        $value = $query->getQuery()->getOneOrNullResult();

        if (!is_array($value) || !isset($value['releaseVersion'])) {
            return null;
        }

        return (string) $value['releaseVersion'];
    }

    /**
     * @return list<string>
     */
    public function findSuccessfulVersionsBySubscriber(?string $subscriberCode, int $limit = 200): array
    {
        $query = $this->createQueryBuilder('e')
            ->select('e.releaseVersion')
            ->andWhere('e.status = :status')
            ->setParameter('status', 'succeeded')
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(max(1, $limit));

        $normalizedSubscriber = trim((string) $subscriberCode);
        if ($normalizedSubscriber !== '') {
            $query
                ->andWhere('e.targetSubscriberCode = :subscriberCode')
                ->setParameter('subscriberCode', $normalizedSubscriber);
        }

        $rows = $query->getQuery()->getArrayResult();
        $versions = [];
        foreach ($rows as $row) {
            $version = trim((string) ($row['releaseVersion'] ?? ''));
            if ($version === '') {
                continue;
            }
            $versions[] = $version;
        }

        return array_values(array_unique($versions));
    }

    public function hasExecutionInStatuses(string $version, array $statuses): bool
    {
        $count = (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.releaseVersion = :version')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('version', trim($version))
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
