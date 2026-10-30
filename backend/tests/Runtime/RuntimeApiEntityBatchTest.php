<?php
namespace App\Tests\Runtime;

use App\Auth\AuthenticatedSessionResolver;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Entity\RuntimeUserSession;
use App\Entity\RuntimeTransaction;
use App\Entity\RuntimeTransactionLog;
use App\Repository\BuilderEntityRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeExternalJsonClient;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeTransactionService;
use App\Runtime\RuntimeExecutionContext;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeApiEntityBatchTest extends TestCase
{
    private function setupService(array $permissions = ['tipos.create', 'tipos.edit', 'tipos.delete'], bool $authenticated = true, bool $audit = false, array $template = ['operation' => 'batch'], bool $canonical = false): array
    {
        $entityCode = $canonical ? 'tipos_anexo_progress' : 'tipos';
        $url = $canonical ? 'http://127.0.0.1:19910/med/web/SursumPrograms/tipos-anexo' : '/tipos';
        $entity = (new BuilderEntity())->setCode($entityCode)->setEntityType('api')->setMetadata(['apiBinding' => ['sourceCode' => 'motor_progress_tipos_anexo'], 'apiSource' => [
            'responseContract' => 'sursum-programs', 'authHeaders' => [],
            'listEndpoint' => ['url' => '/tipos'], 'listResponse' => ['itemsPath' => 'data'],
            'batchEndpoint' => ['url' => $url, 'method' => 'POST', 'bodyTemplate' => $template],
            'batchResponse' => ['itemsPath' => 'data'],
        ]]);
        $entity->addField((new BuilderField())->setCode('tipo_anexo_id')->setPrimaryKey(true)->setDataType('integer')->setOptions(['readonly' => true]));
        $entity->addField((new BuilderField())->setCode('descricao')->setDataType('string')->setRequired(true)->setOptions(['maxLength' => 100, 'api' => ['jsonPath' => $canonical ? 'descricao' : 'description', 'writePath' => $canonical ? 'descricao' : 'description']]));
        $entities = $this->createMock(BuilderEntityRepository::class);
        $entities->expects(self::once())->method('findOneBy')->with(['code' => $entityCode])->willReturn($entity);
        $client = $this->createMock(RuntimeExternalJsonClient::class);
        $transactions = $audit ? $this->createMock(RuntimeTransactionService::class) : $this->createStub(RuntimeTransactionService::class);
        $logs = new \stdClass(); $logs->items = [];
        $transaction = new RuntimeTransaction();
        if ($canonical) {
            $manager = $this->createStub(EntityManagerInterface::class);
            $manager->method('persist')->willReturnCallback(static function ($item) use ($logs): void {
                if ($item instanceof RuntimeTransactionLog) { $logs->items[] = $item; }
            });
            $transactions = new RuntimeTransactionService($manager, $this->createStub(PermissionResolver::class), new RuntimeExecutionContext(),
                $this->createStub(RuntimeEntityDefinitionResolver::class), $this->createStub(RuntimeEnvironmentIdentityResolver::class));
            (new \ReflectionProperty($transactions, 'current'))->setValue($transactions, $transaction);
        }
        $resolver = $this->createStub(AuthenticatedSessionResolver::class);
        $resolver->method('resolve')->willReturn($authenticated ? (new RuntimeUserSession())->setUserId('admin') : null);
        $permissionResolver = $this->createStub(PermissionResolver::class);
        $permissionResolver->method('hasPermission')->willReturnCallback(fn (string $permission): bool => in_array($permission, $permissions, true));
        return [new RuntimeApiEntityActionService($entities, $transactions, $client, $permissionResolver, $resolver), $client, $transactions, $logs, $transaction];
    }

    private function config(array $overrides = []): array
    {
        return array_replace(['entityCode' => 'tipos', 'operation' => 'batch', 'permissionPrefix' => 'tipos', 'allowCreate' => true, 'allowUpdate' => true, 'allowDelete' => true], $overrides);
    }

    private function changes(): array
    {
        return [
            ['operation' => 'create', 'clientId' => 'new-1', 'data' => ['descricao' => 'Novo']],
            ['operation' => 'update', 'clientId' => 'row-2', 'id' => 2, 'data' => ['descricao' => 'Alterado'], 'original' => ['descricao' => null]],
            ['operation' => 'delete', 'clientId' => 'row-3', 'id' => 3, 'original' => ['descricao' => 'Antigo']],
        ];
    }

    public function testMixedBatchMakesOneRequestMapsWritePathsAndAudits(): void
    {
        [$service, $client, $transactions] = $this->setupService(audit: true);
        $client->expects(self::once())->method('request')->with('/tipos', 'POST', [], [
            'operation' => 'batch', 'changes' => [
                ['operation' => 'create', 'clientId' => 'new-1', 'data' => ['description' => 'Novo']],
                ['operation' => 'update', 'clientId' => 'row-2', 'id' => 2, 'data' => ['description' => 'Alterado'], 'original' => ['description' => null]],
                ['operation' => 'delete', 'clientId' => 'row-3', 'id' => 3, 'original' => ['description' => 'Antigo']],
            ],
        ], 20, ['entityCode' => 'tipos', 'responseContract' => 'sursum-programs'])->willReturn(['status' => 200, 'body' => ['success' => true, 'data' => [['tipo_anexo_id' => 4, 'description' => 'Novo']], 'results' => [['operation' => 'create', 'clientId' => 'new-1', 'id' => 4]]]]);
        $transactions->expects(self::once())->method('log')->with('tipos.api.batch', self::anything(), self::anything(), self::anything(), self::callback(fn ($metadata) => $metadata['changesCount'] === 3));
        $result = $service->handle('ciw.tipos', 'batch', $this->config(), ['changes' => $this->changes()]);
        self::assertSame([['tipo_anexo_id' => 4, 'descricao' => 'Novo']], $result['data']);
        self::assertSame(1, $result['total']);
        self::assertSame('new-1', $result['results'][0]['clientId']);
    }

    public function testStaticTemplateCannotAddUnvalidatedChangesToBatch(): void
    {
        [$service, $client] = $this->setupService(template: ['operation' => 'read', 'changes' => [['unvalidated' => true], ['unvalidated' => true]]]);
        $client->expects(self::once())->method('request')->with('/tipos', 'POST', [], [
            'operation' => 'batch', 'changes' => [['operation' => 'create', 'clientId' => 'new-1', 'data' => ['description' => 'Novo']]],
        ], self::anything(), self::anything())->willReturn(['status' => 200, 'body' => ['success' => true, 'data' => []]]);
        self::assertSame(0, $service->handle('ciw.tipos', 'batch', $this->config(), ['changes' => [$this->changes()[0]]])['total']);
    }

    public static function deniedActions(): array
    {
        return [['tipos.create'], ['tipos.edit'], ['tipos.delete']];
    }

    #[DataProvider('deniedActions')]
    public function testEveryActionRequiresPermissionBeforeAnyRequest(string $denied): void
    {
        [$service, $client] = $this->setupService(array_values(array_diff(['tipos.create', 'tipos.edit', 'tipos.delete'], [$denied])));
        $client->expects(self::never())->method('request');
        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('permissao');
        $service->handle('ciw.tipos', 'batch', $this->config(), ['changes' => $this->changes()]);
    }

    public function testBatchRequiresAuthenticatedSessionEvenWithDemoPermissions(): void
    {
        [$service, $client] = $this->setupService(authenticated: false);
        $client->expects(self::never())->method('request');
        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('autenticada');
        $service->handle('ciw.tipos', 'batch', $this->config(), ['changes' => $this->changes()]);
    }

    public function testScreenCanDisableDeleteEvenForAdministrator(): void
    {
        [$service, $client] = $this->setupService();
        $client->expects(self::never())->method('request');
        $this->expectException(RuntimeHttpException::class);
        $service->handle('ciw.tipos', 'batch', $this->config(['allowDelete' => false]), ['changes' => $this->changes()]);
    }

    public static function malformedBatches(): array
    {
        $create = ['operation' => 'create', 'clientId' => 'n', 'data' => ['descricao' => 'Novo']];
        return [
            'empty' => [['changes' => []]],
            'too many' => [['changes' => array_fill(0, 101, $create)]],
            'unknown operation' => [['changes' => [array_replace($create, ['operation' => 'drop'])]]],
            'no client id' => [['changes' => [['operation' => 'create', 'data' => ['descricao' => 'A']]]]],
            'chosen create id' => [['changes' => [$create + ['id' => 42]]]],
            'primary key' => [['changes' => [array_replace($create, ['data' => ['tipo_anexo_id' => 1, 'descricao' => 'A']])]]],
            'unknown field' => [['changes' => [array_replace($create, ['data' => ['descricao' => 'A', 'admin' => true]])]]],
            'bad field type' => [['changes' => [array_replace($create, ['data' => ['descricao' => ['A']]])]]],
            'description length' => [['changes' => [array_replace($create, ['data' => ['descricao' => str_repeat('a', 101)]])]]],
            'update id string' => [['changes' => [['operation' => 'update', 'clientId' => 'r', 'id' => '2', 'data' => ['descricao' => 'A'], 'original' => ['descricao' => 'B']]]]],
            'missing original' => [['changes' => [['operation' => 'update', 'clientId' => 'r', 'id' => 2, 'data' => ['descricao' => 'A']]]]],
            'bad original field' => [['changes' => [['operation' => 'delete', 'clientId' => 'r', 'id' => 2, 'original' => ['admin' => true]]]]],
            'spoof entity' => [['entityCode' => 'other', 'changes' => [$create]]],
            'unknown payload key' => [['userId' => 'other', 'changes' => [$create]]],
            'duplicate client id' => [['changes' => [$create, $create]]],
            'delete data' => [['changes' => [['operation' => 'delete', 'clientId' => 'r', 'id' => 2, 'data' => ['descricao' => 'A'], 'original' => ['descricao' => 'B']]]]],
        ];
    }

    #[DataProvider('malformedBatches')]
    public function testInvalidBatchNeverCallsExternalApi(array $payload): void
    {
        [$service, $client] = $this->setupService();
        $client->expects(self::never())->method('request');
        $this->expectException(RuntimeHttpException::class);
        $service->handle('ciw.tipos', 'batch', $this->config(), $payload);
    }

    public function testTransactionalFailureIsAuditedAndPropagatedWithoutCrudFallback(): void
    {
        [$service, $client, $transactions] = $this->setupService(audit: true);
        $client->expects(self::once())->method('request')->willThrowException(new RuntimeHttpException('BATCH_CONFLICT', 'Linha 2 (row-2): registro alterado.', 409));
        $transactions->expects(self::once())->method('log')->with('tipos.api.batch.failed', self::anything(), self::anything(), self::anything(), self::callback(fn ($metadata) => $metadata['changesCount'] === 3));
        $this->expectExceptionMessage('Linha 2 (row-2)');
        $service->handle('ciw.tipos', 'batch', $this->config(), ['changes' => $this->changes()]);
    }

    private function confirmedBatchBody(): array
    {
        return ['success' => true, 'data' => [['tipo_anexo_id' => 4, 'descricao' => 'Novo']], 'results' => [
            ['operation' => 'create', 'clientId' => 'new-1', 'id' => 4],
            ['operation' => 'update', 'clientId' => 'row-2', 'id' => 2],
            ['operation' => 'delete', 'clientId' => 'row-3', 'id' => 3],
        ], 'audit' => ['version' => 1, 'source' => 'progress-transaction', 'resource' => 'tipos_anexo', 'operation' => 'batch', 'changes' => [
            ['clientId' => 'new-1', 'operation' => 'create', 'recordId' => 4, 'before' => [], 'after' => ['tipo_anexo_id' => 4, 'descricao' => 'Novo']],
            ['clientId' => 'row-2', 'operation' => 'update', 'recordId' => 2, 'before' => ['tipo_anexo_id' => 2, 'descricao' => 'Real anterior'], 'after' => ['tipo_anexo_id' => 2, 'descricao' => 'Alterado']],
            ['clientId' => 'row-3', 'operation' => 'delete', 'recordId' => 3, 'before' => ['tipo_anexo_id' => 3, 'descricao' => null], 'after' => []],
        ]]];
    }

    private function executeConfirmedBatch(array $body, ?array $changes = null): array
    {
        [$service, $client, , $logs, $transaction] = $this->setupService(canonical: true);
        $client->expects(self::once())->method('request')->willReturn(['status' => 200, 'body' => $body]);
        $result = $service->handle('ciw.tipos-anexo', 'batch', $this->config(['entityCode' => 'tipos_anexo_progress']), ['changes' => $changes ?? $this->changes()]);
        return [$result, $logs->items, $transaction];
    }

    public function testConfirmedBatchAuditsEachRealSnapshotAfterAllRowsAreValidated(): void
    {
        $body = $this->confirmedBatchBody();
        // Correlation is by clientId, not ordering in the response.
        $body['results'] = array_reverse($body['results']);
        $body['audit']['changes'] = array_reverse($body['audit']['changes']);
        [$result, $logs, $transaction] = $this->executeConfirmedBatch($body);
        self::assertSame(1, $result['total']);
        self::assertCount(4, $logs);
        self::assertSame(['tipos_anexo_progress.api.create', 'tipos_anexo_progress.api.update', 'tipos_anexo_progress.api.delete', 'tipos_anexo_progress.api.batch'], array_map(static fn ($log) => $log->getEventType(), $logs));
        self::assertSame(['before' => 'Real anterior', 'after' => 'Alterado'], $logs[1]->getDiffData()['descricao']);
        self::assertSame(['tipo_anexo_id' => 3, 'descricao' => null], $logs[2]->getBeforeData());
        self::assertSame([], $logs[3]->getBeforeData());
        self::assertSame([], $logs[3]->getAfterData());
        self::assertSame(['available' => true, 'version' => 1, 'source' => 'progress-transaction', 'resource' => 'tipos_anexo', 'operation' => 'update', 'recordId' => 2], $logs[1]->getMetadata()['audit']);
        self::assertSame('row-2', $logs[1]->getMetadata()['clientId']);
        self::assertNull($transaction->getRecordId());
    }

    public function testAbsentOrInvalidBatchAuditNeverInventsBeforeOrEmitsPartialConfirmedEvents(): void
    {
        $mutations = [
            static function (&$b) { unset($b['audit']); },
            static function (&$b) { $b['audit'] = 'wrong'; },
            static function (&$b) { $b['audit']['version'] = 2; },
            static function (&$b) { $b['audit']['resource'] = 'empresas'; },
            static function (&$b) { $b['audit']['operation'] = 'update'; },
            static function (&$b) { $b['audit']['source'] = 'browser'; },
            static function (&$b) { array_pop($b['audit']['changes']); },
            static function (&$b) { $b['audit']['changes'] = ['wrong' => $b['audit']['changes'][0]]; },
            static function (&$b) { $b['audit']['changes'][2] = 'wrong'; },
            static function (&$b) { $b['audit']['changes'][2]['clientId'] = 'unknown'; },
            static function (&$b) { $b['audit']['changes'][2]['clientId'] = 'row-2'; },
            static function (&$b) { $b['audit']['changes'][2]['operation'] = 'update'; },
            static function (&$b) { $b['audit']['changes'][2]['recordId'] = 2; },
            static function (&$b) { $b['audit']['changes'][2]['before']['tipo_anexo_id'] = 99; },
            static function (&$b) { $b['audit']['changes'][2]['before']['descricao'] = false; },
            static function (&$b) { $b['audit']['changes'][2]['before']['descricao'] = str_repeat('a', 101); },
            static function (&$b) { $b['audit']['changes'][2]['before'] = null; },
            static function (&$b) { unset($b['audit']['changes'][2]['before']['descricao']); },
            static function (&$b) { $b['audit']['changes'][2]['after'] = ['tipo_anexo_id' => 3, 'descricao' => 'wrong']; },
            static function (&$b) { $b['audit']['changes'][2]['before']['unknown'] = 'private'; },
            static function (&$b) { $b['audit']['changes'][2]['extra'] = 'private'; },
            static function (&$b) { $b['audit']['changes'][0]['recordId'] = '4'; },
            static function (&$b) { $b['audit']['changes'][0]['before'] = ['tipo_anexo_id' => 4, 'descricao' => 'wrong']; },
            static function (&$b) { $b['audit']['changes'][0]['recordId'] = 2; $b['audit']['changes'][0]['after']['tipo_anexo_id'] = 2; $b['results'][0]['id'] = 2; },
            static function (&$b) { array_pop($b['results']); },
            static function (&$b) { $b['results'] = null; },
            static function (&$b) { $b['results'][2] = false; },
            static function (&$b) { $b['results'][2]['clientId'] = 'unknown'; },
            static function (&$b) { $b['results'][2]['clientId'] = 'row-2'; },
            static function (&$b) { $b['results'][2]['operation'] = 'update'; },
            static function (&$b) { $b['results'][2]['id'] = 99; },
            static function (&$b) { $b['results'][0]['id'] = 99; },
            static function (&$b) { $b['results'][0]['id'] = '4'; },
            static function (&$b) { $b['results'][0]['id'] = 0; },
            static function (&$b) { $b['results'][0]['id'] = 2147483648; },
            static function (&$b) { $b['audit']['extra'] = 'private'; },
        ];
        foreach ($mutations as $index => $mutate) {
            $body = $this->confirmedBatchBody(); $mutate($body);
            [$result, $logs] = $this->executeConfirmedBatch($body);
            self::assertSame(1, $result['total']);
            $confirmed = array_filter($logs, static fn ($log) => ($log->getMetadata()['audit']['available'] ?? false) === true);
            self::assertSame([], $confirmed, 'Mutation ' . $index . ' must not log partially trusted rows');
            $summary = $logs[array_key_last($logs)];
            self::assertSame('tipos_anexo_progress.api.batch', $summary->getEventType());
            self::assertSame([], $summary->getBeforeData());
            self::assertSame([], $summary->getAfterData());
            self::assertFalse($summary->getMetadata()['audit']['available']);
        }
    }

    public function testNumericClientIdRemainsAStringInConfirmedEventMetadata(): void
    {
        $body = $this->confirmedBatchBody();
        $body['results'] = [array_replace($body['results'][0], ['clientId' => '123'])];
        $body['audit']['changes'] = [array_replace($body['audit']['changes'][0], ['clientId' => '123'])];
        [, $logs] = $this->executeConfirmedBatch($body, [array_replace($this->changes()[0], ['clientId' => '123'])]);
        self::assertTrue($logs[0]->getMetadata()['audit']['available']);
        self::assertSame('123', $logs[0]->getMetadata()['clientId']);
    }

    public function testFailedBatchHasNoInventedSnapshotsOrConfirmedRowEvents(): void
    {
        [$service, $client, , $logs] = $this->setupService(canonical: true);
        $client->expects(self::once())->method('request')->willThrowException(new RuntimeHttpException('BATCH_CONFLICT', 'Linha 3 recusada.', 409));
        try {
            $service->handle('ciw.tipos-anexo', 'batch', $this->config(['entityCode' => 'tipos_anexo_progress']), ['changes' => $this->changes()]);
            self::fail('Expected transactional rejection');
        } catch (RuntimeHttpException $error) {
            self::assertSame('BATCH_CONFLICT', $error->getErrorCode());
        }
        self::assertCount(1, $logs->items);
        self::assertSame('tipos_anexo_progress.api.batch.failed', $logs->items[0]->getEventType());
        self::assertSame([], $logs->items[0]->getBeforeData());
        self::assertSame([], $logs->items[0]->getAfterData());
        self::assertSame([], $logs->items[0]->getDiffData());
        self::assertFalse($logs->items[0]->getMetadata()['audit']['available']);
    }
}
