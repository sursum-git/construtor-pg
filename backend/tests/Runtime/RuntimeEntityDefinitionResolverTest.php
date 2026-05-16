<?php

namespace App\Tests\Runtime;

use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Repository\BuilderEntityRepository;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeHttpException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;

class RuntimeEntityDefinitionResolverTest extends TestCase
{
    public function testMissingBuilderEntityReturnsConfigurationError(): void
    {
        $repository = $this->createMock(BuilderEntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['code' => 'produto'])
            ->willReturn(null);

        $resolver = new RuntimeEntityDefinitionResolver($repository, $this->createStub(Connection::class));

        try {
            $resolver->resolve('produto');
            self::fail('Expected runtime configuration error.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('ENTITY_METADATA_NOT_CONFIGURED', $error->getErrorCode());
            self::assertSame(422, $error->getStatusCode());
            self::assertArrayHasKey('minimumRequired', $error->getDetails());
            self::assertSame('persistence', $error->getDetails()['minimumRequired']['builder_entity']['entityType']);
            self::assertSame('entity.crud', $error->getDetails()['minimumRequired']['runtime_endpoint']['handler']);
        }
    }

    public function testResolvedFieldsExposeTechnicalProperties(): void
    {
        $entity = (new BuilderEntity())
            ->setCode('cliente')
            ->setName('Cliente')
            ->setEntityType('persistence')
            ->setTableName('t_cliente')
            ->setMetadata([
                'versioning' => ['enabled' => true],
            ]);

        $idField = (new BuilderField())
            ->setCode('id')
            ->setLabel('ID')
            ->setDataType('integer')
            ->setDatabaseType('integer')
            ->setRequired(true)
            ->setPrimaryKey(true)
            ->setPosition(1)
            ->setOptions([
                'columnName' => 'id',
            ]);
        $entity->addField($idField);

        $nameField = (new BuilderField())
            ->setCode('nome')
            ->setLabel('Nome')
            ->setDataType('string')
            ->setDatabaseType('varchar')
            ->setLength(120)
            ->setPosition(2)
            ->setOptions([
                'columnName' => 'nome',
                'unique' => true,
                'foreignKey' => [
                    'entityCode' => 'grupo_cliente',
                    'fieldCode' => 'id',
                ],
            ]);
        $entity->addField($nameField);

        $repository = $this->createMock(BuilderEntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['code' => 'cliente'])
            ->willReturn($entity);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects(self::once())
            ->method('listTableColumns')
            ->with('t_cliente')
            ->willReturn([
                'id' => null,
                'nome' => null,
            ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);
        $connection->expects(self::once())
            ->method('quoteSingleIdentifier')
            ->with('t_cliente')
            ->willReturn('"t_cliente"');

        $resolver = new RuntimeEntityDefinitionResolver($repository, $connection);
        $definition = $resolver->resolve('cliente');

        self::assertSame('id', $definition['primaryKey']);
        self::assertSame('"t_cliente"', $definition['quotedTableName']);

        $idProperties = $definition['fields']['id']['technicalProperties'];
        self::assertSame('Sim', $this->findPropertyValue($idProperties, 'Chave primaria'));
        self::assertTrue($this->findPropertyCritical($idProperties, 'Chave primaria'));
        self::assertSame('t_cliente', $this->findPropertyValue($idProperties, 'Tabela'));
        self::assertSame('id', $this->findPropertyValue($idProperties, 'Coluna'));

        $nameProperties = $definition['fields']['nome']['technicalProperties'];
        self::assertSame('Sim', $this->findPropertyValue($nameProperties, 'Valor unico'));
        self::assertTrue($this->findPropertyCritical($nameProperties, 'Valor unico'));
        self::assertSame('grupo_cliente', $this->findPropertyValue($nameProperties, 'FK entidade'));
        self::assertSame('id', $this->findPropertyValue($nameProperties, 'FK campo'));
        self::assertSame('Sim', $this->findPropertyValue($nameProperties, 'Entidade versionada'));
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
