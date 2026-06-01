<?php

namespace App\Tests\Runtime;

use App\Entity\UserLookupUsage;
use App\Repository\UserFilterPreferenceRepository;
use App\Repository\UserGridLayoutPreferenceRepository;
use App\Repository\UserGroupPreferenceRepository;
use App\Repository\UserLookupUsageRepository;
use App\Repository\UserMobileGridTemplatePreferenceRepository;
use App\Repository\UserSortPreferenceRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\UserLayoutService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class UserLayoutServiceLookupUsageTest extends TestCase
{
    public function testRecordLookupUsageCreatesAndIncrementsHits(): void
    {
        $lookupRepository = $this->createStub(UserLookupUsageRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('tenant-a');
        $permissions->method('getUserId')->willReturn('user-a');

        $existing = (new UserLookupUsage())
            ->setTenantId('tenant-a')
            ->setUserId('user-a')
            ->setScreenId('cadastros.clientes')
            ->setFilterId('clienteId')
            ->setFieldName('clienteId')
            ->setLookupValue('456')
            ->setLookupText('Cliente Beta')
            ->setHits(3);

        $lookupRepository
            ->method('findOneForUserValue')
            ->willReturnCallback(static function (string $tenantId, string $userId, string $screenId, string $filterId, string $lookupValue) use ($existing): ?UserLookupUsage {
                if ($tenantId === 'tenant-a' && $userId === 'user-a' && $screenId === 'cadastros.clientes' && $filterId === 'clienteId' && $lookupValue === '456') {
                    return $existing;
                }

                return null;
            });

        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService($lookupRepository, $entityManager, $permissions);
        $result = $service->recordLookupUsage('cadastros.clientes', [
            'filterId' => 'clienteId',
            'field' => 'clienteId',
            'items' => [
                ['value' => '123', 'text' => 'Cliente Acme'],
                ['value' => '456', 'text' => 'Cliente Beta'],
            ],
        ]);

        self::assertTrue($result['ok']);
        self::assertSame(2, $result['recorded']);
        self::assertSame('123', $result['items'][0]['value']);
        self::assertSame(1, $result['items'][0]['hits']);
        self::assertSame('456', $result['items'][1]['value']);
        self::assertSame(4, $result['items'][1]['hits']);
    }

    public function testLookupFrequentReturnsNormalizedItems(): void
    {
        $lookupRepository = $this->createMock(UserLookupUsageRepository::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('tenant-a');
        $permissions->method('getUserId')->willReturn('user-a');

        $top = (new UserLookupUsage())
            ->setTenantId('tenant-a')
            ->setUserId('user-a')
            ->setScreenId('cadastros.clientes')
            ->setFilterId('clienteId')
            ->setFieldName('clienteId')
            ->setLookupValue('123')
            ->setLookupText('Cliente Acme')
            ->setHits(7);
        $second = (new UserLookupUsage())
            ->setTenantId('tenant-a')
            ->setUserId('user-a')
            ->setScreenId('cadastros.clientes')
            ->setFilterId('clienteId')
            ->setFieldName('clienteId')
            ->setLookupValue('456')
            ->setLookupText('Cliente Beta')
            ->setHits(4);

        $lookupRepository
            ->expects(self::once())
            ->method('findFrequentForUser')
            ->with('tenant-a', 'user-a', 'cadastros.clientes', 'clienteId', 'clienteId', 5)
            ->willReturn([$top, $second]);

        $service = $this->createService($lookupRepository, $entityManager, $permissions);
        $result = $service->lookupFrequent('cadastros.clientes', [
            'filterId' => 'clienteId',
            'field' => 'clienteId',
            'limit' => 5,
        ]);

        self::assertTrue($result['ok']);
        self::assertSame(2, $result['total']);
        self::assertSame('123', $result['items'][0]['value']);
        self::assertSame('Cliente Acme', $result['items'][0]['text']);
        self::assertSame(7, $result['items'][0]['hits']);
        self::assertNotSame('', $result['items'][0]['lastUsedAt']);
    }

    private function createService(
        UserLookupUsageRepository $lookupRepository,
        EntityManagerInterface $entityManager,
        PermissionResolver $permissions,
    ): UserLayoutService {
        return new UserLayoutService(
            $this->createStub(UserGridLayoutPreferenceRepository::class),
            $this->createStub(UserSortPreferenceRepository::class),
            $this->createStub(UserGroupPreferenceRepository::class),
            $this->createStub(UserFilterPreferenceRepository::class),
            $lookupRepository,
            $this->createStub(UserMobileGridTemplatePreferenceRepository::class),
            $entityManager,
            $permissions,
        );
    }
}
