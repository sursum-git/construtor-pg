<?php

namespace App\Repository;

use App\Entity\AuthUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthUser::class);
    }

    public function findOneByTenantAndUsername(string $tenantId, string $username): ?AuthUser
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'normalizedUsername' => AuthUser::normalizeUsername($username),
        ]);
    }

    public function findOneForPasswordReset(string $identity): ?AuthUser
    {
        $normalized = AuthUser::normalizeUsername($identity);

        return $this->createQueryBuilder('u')
            ->andWhere('u.status = :status')
            ->andWhere('u.normalizedUsername = :identity OR LOWER(u.email) = :identity')
            ->setParameter('status', 'active')
            ->setParameter('identity', $normalized)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $usernames
     *
     * @return AuthUser[]
     */
    public function findActiveByTenantAndUsernames(string $tenantId, array $usernames): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(static function ($value): string {
            return AuthUser::normalizeUsername((string) $value);
        }, $usernames))));
        if ($normalized === []) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->andWhere('u.tenantId = :tenantId')
            ->andWhere('u.status = :status')
            ->andWhere('u.normalizedUsername IN (:usernames)')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('status', 'active')
            ->setParameter('usernames', $normalized)
            ->getQuery()
            ->getResult();
    }
}
