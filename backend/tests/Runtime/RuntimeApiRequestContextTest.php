<?php
namespace App\Tests\Runtime;

use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Repository\BuilderEntityRepository;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeExternalJsonClient;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeTransactionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeApiRequestContextTest extends TestCase
{
    private function service(callable $request, bool $configured = true): RuntimeApiEntityActionService
    {
        $context = $configured ? ['requestContextFields' => ['container_id']] : [];
        $entity = (new BuilderEntity())->setCode('filhos_api')->setName('Filhos')->setEntityType('api')->setMetadata([
            'apiSource' => ['authHeaders' => [], 'timeoutSeconds' => 5,
                'listEndpoint' => ['url' => '/panel', 'method' => 'POST', 'bodyTemplate' => ['operation' => 'read', 'resource' => 'itens']] + $context,
                'listResponse' => ['itemsPath' => 'data'],
                'detailEndpoint' => ['url' => '/panel', 'method' => 'POST', 'bodyTemplate' => ['operation' => 'get', 'resource' => 'itens']] + $context,
                'detailResponse' => ['itemPath' => 'data']]
        ]);
        foreach (['id', 'container_id', 'descricao'] as $i => $code) {
            $entity->addField((new BuilderField())->setCode($code)->setLabel($code)
                ->setDataType($code === 'descricao' ? 'string' : 'integer')->setPosition($i + 1)->setPrimaryKey($code === 'id')
                ->setOptions(['api' => ['jsonPath' => $code]]));
        }
        $repo = $this->createStub(BuilderEntityRepository::class);
        $repo->method('findOneBy')->willReturn($entity);
        $client = $this->createStub(RuntimeExternalJsonClient::class);
        $client->method('request')->willReturnCallback($request);
        return new RuntimeApiEntityActionService($repo, $this->createStub(RuntimeTransactionService::class), $client);
    }

    private function handle(RuntimeApiEntityActionService $service, string $operation, array $payload): array
    {
        return $service->handle('ciw.filhos', $operation, ['entityCode' => 'filhos_api', 'operation' => $operation], $payload);
    }

    public function testReadForwardsOnlyRegisteredContextExtractedFromConjunctiveEquality(): void
    {
        $calls = 0;
        $service = $this->service(function ($url, $method, $headers, $body) use (&$calls): array {
            ++$calls;
            self::assertSame(['operation' => 'read', 'resource' => 'itens', 'container_id' => 17], $body);
            return ['status' => 200, 'body' => ['data' => [['id' => 4, 'container_id' => 17, 'descricao' => 'A']]]];
        });
        $result = $this->handle($service, 'read', ['skip' => 0, 'take' => 30, 'operation' => 'delete', 'container_id' => 99,
            'filters' => [['field' => 'container_id', 'operator' => 'eq', 'value' => '17']],
            'filter' => ['logic' => 'and', 'filters' => [['field' => 'descricao', 'operator' => 'contains', 'value' => 'A']]]]);
        self::assertSame(1, $calls);
        self::assertSame(1, $result['total']);
    }

    public function testGetForwardsValidatedContextAndRecordId(): void
    {
        $service = $this->service(function ($url, $method, $headers, $body): array {
            self::assertSame(['operation' => 'get', 'resource' => 'itens', 'id' => 4, 'container_id' => 17], $body);
            return ['status' => 200, 'body' => ['data' => ['id' => 4, 'container_id' => 17, 'descricao' => 'A']]];
        });
        self::assertSame(4, $this->handle($service, 'get', ['id' => 4, 'context' => ['container_id' => '17']])['id']);
    }

    public static function invalidContext(): array
    {
        return [
            'missing' => [[]],
            'wrong operator' => [['filters' => [['field' => 'container_id', 'operator' => 'neq', 'value' => 17]]]],
            'or context' => [['filter' => ['logic' => 'or', 'filters' => [['field' => 'container_id', 'operator' => 'eq', 'value' => 17], ['field' => 'id', 'operator' => 'eq', 'value' => 4]]]]],
            'conflicting context' => [['context' => ['container_id' => 18], 'filters' => [['field' => 'container_id', 'operator' => 'eq', 'value' => 17]]]],
            'zero' => [['context' => ['container_id' => 0]]],
            'fractional' => [['context' => ['container_id' => 1.5]]],
            'boolean' => [['context' => ['container_id' => true]]],
            'overflow' => [['context' => ['container_id' => '2147483648']]],
            'sql text' => [['context' => ['container_id' => '17 OR true']]],
            'unknown context' => [['context' => ['container_id' => 17, 'table' => 'foo']]],
            'list context' => [['context' => [17]]],
        ];
    }

    #[DataProvider('invalidContext')]
    public function testInvalidContextFailsBeforeExternalRequest(array $payload): void
    {
        $service = $this->service(function (): array { self::fail('Nao deve chamar API sem contexto valido.'); });
        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Contexto');
        $this->handle($service, 'read', $payload);
    }

    public function testExternalResponseFromAnotherContainerIsRejected(): void
    {
        $service = $this->service(fn (): array => ['status' => 200, 'body' => ['data' => [['id' => 4, 'container_id' => 18]]]]);
        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('contexto');
        $this->handle($service, 'read', ['context' => ['container_id' => 17]]);
    }

    public function testLegacyUnconfiguredListKeepsExistingPayload(): void
    {
        $service = $this->service(function ($url, $method, $headers, $body): array {
            self::assertSame(['operation' => 'read', 'resource' => 'itens'], $body);
            return ['status' => 200, 'body' => ['data' => []]];
        }, false);
        self::assertSame(0, $this->handle($service, 'read', [])['total']);
    }
}
