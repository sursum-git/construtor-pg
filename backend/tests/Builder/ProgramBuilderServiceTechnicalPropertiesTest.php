<?php

namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Odoo\OdooClient;
use App\Repository\BuilderApiSourceRepository;
use App\Repository\BuilderEditorLockRepository;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Repository\BuilderFieldRepository;
use App\Repository\BuilderModuleRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ProgramRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\ProgramGovernanceService;
use App\Runtime\ProgramOverlayService;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProgramBuilderServiceTechnicalPropertiesTest extends TestCase
{
    public function testProgramFieldTechnicalPropertiesIncludeApiAndOdooMetadata(): void
    {
        $service = $this->service();
        $entity = (new BuilderEntity())
            ->setCode('parceiros_odoo')
            ->setName('Parceiros Odoo')
            ->setEntityType('api')
            ->setMetadata([
                'apiSourceCode' => 'odoo_mock',
                'apiListOperationCode' => 'odoo_list',
                'apiDetailOperationCode' => 'odoo_detail',
                'apiSource' => [
                    'providerType' => 'odoo',
                    'odoo' => [
                        'model' => 'res.partner',
                        'transport' => 'jsonrpc',
                    ],
                ],
            ]);

        $field = (new BuilderField())
            ->setCode('nome')
            ->setLabel('Nome')
            ->setDataType('string')
            ->setRequired(true)
            ->setPosition(1)
            ->setOptions([
                'readonly' => true,
                'api' => [
                    'jsonPath' => 'name',
                ],
            ]);

        $properties = $this->invokePrivate(
            $service,
            'programFieldTechnicalProperties',
            [$entity, $field, 'string', $field->getOptions()]
        );

        self::assertSame('name', $this->findPropertyValue($properties, 'JSON Path'));
        self::assertTrue($this->findPropertyCritical($properties, 'JSON Path'));
        self::assertSame('odoo_mock', $this->findPropertyValue($properties, 'API cadastrada'));
        self::assertSame('odoo_list', $this->findPropertyValue($properties, 'Operacao de lista'));
        self::assertSame('odoo_detail', $this->findPropertyValue($properties, 'Operacao de detalhe'));
        self::assertSame('res.partner', $this->findPropertyValue($properties, 'Modelo'));
        self::assertSame('JSONRPC', $this->findPropertyValue($properties, 'Transporte'));
        self::assertSame('Sim', $this->findPropertyValue($properties, 'Somente leitura'));
        self::assertTrue($this->findPropertyCritical($properties, 'Somente leitura'));
    }

    public function testGridAndFilterTechnicalPropertiesAddSurfaceMetadata(): void
    {
        $service = $this->service();
        $entity = (new BuilderEntity())
            ->setCode('cliente')
            ->setName('Cliente')
            ->setEntityType('persistence')
            ->setTableName('t_cliente');

        $field = (new BuilderField())
            ->setCode('nome')
            ->setLabel('Nome')
            ->setDataType('string')
            ->setDatabaseType('varchar')
            ->setLength(120)
            ->setPosition(1)
            ->setOptions([
                'columnName' => 'nome',
            ]);

        $gridProperties = $this->invokePrivate(
            $service,
            'programGridTechnicalProperties',
            [$entity, $field, 'string', $field->getOptions()]
        );
        $filterProperties = $this->invokePrivate(
            $service,
            'programFilterTechnicalProperties',
            [$entity, $field, 'string', $field->getOptions()]
        );

        self::assertSame('Grid', $this->findPropertyValue($gridProperties, 'Superficie'));
        self::assertSame('150', $this->findPropertyValue($gridProperties, 'Largura sugerida'));
        self::assertSame('Filtro', $this->findPropertyValue($filterProperties, 'Superficie'));
        self::assertSame('contains', $this->findPropertyValue($filterProperties, 'Operador inicial'));
        self::assertSame('t_cliente', $this->findPropertyValue($gridProperties, 'Tabela'));
        self::assertSame('nome', $this->findPropertyValue($filterProperties, 'Coluna'));
    }

    private function service(): ProgramBuilderService
    {
        return new ProgramBuilderService(
            $this->createStub(BuilderEntityRepository::class),
            $this->createStub(BuilderApiSourceRepository::class),
            $this->createStub(BuilderEditorLockRepository::class),
            $this->createStub(BuilderModuleRepository::class),
            $this->createStub(BuilderFieldRepository::class),
            $this->createStub(BuilderEntityVersionRepository::class),
            $this->createStub(BuilderProgramVersionRepository::class),
            $this->createStub(ProgramRepository::class),
            $this->createStub(ScreenDefinitionRepository::class),
            $this->createStub(RuntimeEndpointRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(StructuralIntegrityService::class),
            $this->createStub(ProgramGovernanceService::class),
            $this->createStub(ProgramOverlayService::class),
            $this->createStub(RuntimeNotificationService::class),
            $this->createStub(RuntimeEnvironmentIdentityResolver::class),
            $this->createStub(PermissionResolver::class),
            $this->createStub(RuntimeSessionGuard::class),
            $this->createStub(OdooClient::class),
        );
    }

    /**
     * @param array<int, mixed> $arguments
     * @return array<int, array<string, mixed>>
     */
    private function invokePrivate(object $target, string $method, array $arguments): array
    {
        $reflection = new \ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        /** @var array<int, array<string, mixed>> $result */
        $result = $reflection->invokeArgs($target, $arguments);

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $properties
     */
    private function findPropertyValue(array $properties, string $label): ?string
    {
        foreach ($properties as $property) {
            if (($property['label'] ?? null) === $label) {
                return isset($property['value']) ? (string) $property['value'] : null;
            }
        }

        self::fail(sprintf('Property "%s" not found.', $label));
    }

    /**
     * @param array<int, array<string, mixed>> $properties
     */
    private function findPropertyCritical(array $properties, string $label): bool
    {
        foreach ($properties as $property) {
            if (($property['label'] ?? null) === $label) {
                return ($property['critical'] ?? false) === true;
            }
        }

        self::fail(sprintf('Property "%s" not found.', $label));
    }
}
