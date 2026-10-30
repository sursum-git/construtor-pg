<?php
namespace App\Tests\Runtime;

use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Entity\RuntimeTransaction;
use App\Entity\RuntimeTransactionLog;
use App\Repository\BuilderEntityRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeExecutionContext;
use App\Runtime\RuntimeExternalJsonClient;
use App\Runtime\RuntimeTransactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RuntimeApiAuditSnapshotTest extends TestCase
{
    public function testUpdateAuditsOnlySnapshotsConfirmedByProgressWithNullAndZeroPreserved(): void
    {
        $audit = $this->audit('update', ['despesa_id' => 71, 'descricao' => 'Antes', 'valor' => null, 'senha' => 'private', 'extra' => 'private'],
            ['despesa_id' => 71, 'descricao' => 'Depois', 'valor' => 0, 'senha' => 'private']);
        [$saved, $logs] = $this->execute('update', ['data' => ['despesa_id' => 71, 'descricao' => 'Depois'], 'audit' => $audit]);
        self::assertSame('Depois', $saved['descricao']);
        $log = $logs[0];
        self::assertSame(['despesa_id' => 71, 'descricao' => 'Antes', 'valor' => null], $log->getBeforeData());
        self::assertSame(['despesa_id' => 71, 'descricao' => 'Depois', 'valor' => 0], $log->getAfterData());
        self::assertSame(['before' => null, 'after' => 0], $log->getDiffData()['valor']);
        self::assertArrayNotHasKey('omitido', $log->getAfterData());
        self::assertSame(['available' => true, 'version' => 1, 'source' => 'progress-transaction', 'resource' => 'despesas',
            'operation' => 'update', 'recordId' => 71], $log->getMetadata()['audit']);
        self::assertStringNotContainsString('private', json_encode([$log->getBeforeData(), $log->getAfterData(), $log->getMetadata()]));
    }

    public function testMissingOrGenericAuditDoesNotPretendSubmittedDataIsThePriorState(): void
    {
        foreach ([['sursum-programs', []], ['generic', ['audit' => $this->audit('update', ['despesa_id' => 71], ['despesa_id' => 71])]]] as [$contract, $extra]) {
            [$saved, $logs] = $this->execute('update', ['data' => ['despesa_id' => 71, 'descricao' => 'Salvo']] + $extra, $contract);
            self::assertSame('Salvo', $saved['descricao']);
            self::assertSame([], $logs[0]->getBeforeData());
            self::assertSame([], $logs[0]->getAfterData());
            self::assertSame([], $logs[0]->getDiffData());
            self::assertFalse($logs[0]->getMetadata()['audit']['available']);
        }
    }

    public function testInvalidOptionalAuditWarnsButDoesNotTurnACommittedWriteIntoFailure(): void
    {
        foreach ([['operation' => 'delete'], ['recordId' => 72], ['resource' => 'empresas_despesa'], ['version' => 2],
            ['source' => 'browser'], ['before' => ['despesa_id' => 72]], ['after' => ['despesa_id' => 71, 'valor' => ['token' => 'private']]]] as $change) {
            $audit = array_replace($this->audit('update', ['despesa_id' => 71], ['despesa_id' => 71]), $change);
            [$saved, $logs] = $this->execute('update', ['data' => ['despesa_id' => 71, 'descricao' => 'Salvo'], 'audit' => $audit]);
            self::assertSame('Salvo', $saved['descricao']);
            self::assertSame(['despesas_api.api.audit.unavailable', 'despesas_api.api.update'], array_map(static fn ($log) => $log->getEventType(), $logs));
            self::assertSame([], $logs[1]->getBeforeData());
            self::assertFalse($logs[1]->getMetadata()['audit']['available']);
        }
    }

    public function testCreateAndDeleteUseServerSnapshotsAndReturnedIdentity(): void
    {
        [$saved, $logs, $transaction] = $this->execute('create', ['data' => ['despesa_id' => 71, 'descricao' => 'Criado'],
            'audit' => $this->audit('create', [], ['despesa_id' => 71, 'descricao' => 'Criado'])]);
        self::assertSame(71, $saved['despesa_id']);
        self::assertSame('71', $transaction->getRecordId());
        self::assertSame([], $logs[0]->getBeforeData());
        self::assertSame('Criado', $logs[0]->getAfterData()['descricao']);
        [$saved, $logs] = $this->execute('delete', ['data' => [], 'audit' => $this->audit('delete', ['despesa_id' => 71, 'descricao' => 'Removido', 'valor' => 0], [])]);
        self::assertTrue($saved['deleted']);
        self::assertSame('Removido', $logs[0]->getBeforeData()['descricao']);
        self::assertSame([], $logs[0]->getAfterData());
        self::assertSame(['before' => 0, 'after' => null], $logs[0]->getDiffData()['valor']);
    }

    public function testRejectedWritesRemainCorrelatedWhenOnlyTheRecordPrimaryKeyIsSubmitted(): void
    {
        foreach (['update', 'delete'] as $operation) {
            $failure = new \App\Runtime\RuntimeHttpException('PROBE_REJECTED', 'Operacao recusada.', 422);
            [$error, $logs, $transaction] = $this->execute($operation, [], failure: $failure);
            self::assertSame($failure, $error);
            self::assertSame('71', $transaction->getRecordId());
            self::assertSame([], $logs);
        }
    }
    private function audit(string $operation, array $before, array $after): array
    {
        return ['version' => 1, 'source' => 'progress-transaction', 'resource' => 'despesas', 'operation' => $operation,
            'recordId' => 71, 'before' => $before, 'after' => $after];
    }

    public function testLegacyResourcesUseOnlyTheirRegisteredBindingAndRouteForSnapshots(): void
    {
        foreach (['empresas' => ['empresa_id', 'nome'], 'tipos_anexo' => ['tipo_anexo_id', 'descricao']] as $resource => [$pk, $name]) {
            foreach (['create', 'update', 'delete'] as $operation) {
                $before = $operation === 'create' ? [] : [$pk => 71, $name => 'Antes'];
                $after = $operation === 'delete' ? [] : [$pk => 71, $name => 'Depois'];
                if ($resource === 'empresas') {
                    if ($before) { $before += ['cod_emp_ems2' => null, 'cod_emp_ems5' => 0]; }
                    if ($after) { $after += ['cod_emp_ems2' => 0, 'cod_emp_ems5' => null]; }
                }
                $audit = ['version' => 1, 'source' => 'progress-transaction', 'resource' => $resource,
                    'operation' => $operation, 'recordId' => 71, 'before' => $before, 'after' => $after];
                [$saved, $logs] = $this->execute($operation, ['data' => $after, 'audit' => $audit], legacy: $resource);
                self::assertSame($before, $logs[0]->getBeforeData());
                self::assertSame($after, $logs[0]->getAfterData());
                self::assertTrue($logs[0]->getMetadata()['audit']['available']);
            }
        }
    }

    public function testLegacyResourceRejectsWrongBindingRouteKeyAndIncompleteOrUnknownFields(): void
    {
        foreach ([['source' => 'other'], ['route' => '/SursumPrograms/other'], ['entity' => 'other'], ['primaryKey' => 'other_id'], ['method' => 'PUT'],
            ['template' => ['operation' => 'update', 'resource' => 'despesas'], 'audit' => ['resource' => 'despesas']],
            ['template' => ['operation' => 'delete']],
            ['audit' => ['resource' => 'empresas']], ['after' => ['tipo_anexo_id' => 71]],
            ['after' => ['tipo_anexo_id' => 71, 'descricao' => 'Depois', 'unknown' => 'private']],
            ['after' => ['tipo_anexo_id' => 71, 'descricao' => 12]]] as $change) {
            $audit = ['version' => 1, 'source' => 'progress-transaction', 'resource' => 'tipos_anexo', 'operation' => 'update',
                'recordId' => 71, 'before' => ['tipo_anexo_id' => 71, 'descricao' => null],
                'after' => ['tipo_anexo_id' => 71, 'descricao' => 'Depois']];
            $audit = array_replace($audit, $change['audit'] ?? [], array_intersect_key($change, ['after' => true]));
            [$saved, $logs] = $this->execute('update', ['data' => ['tipo_anexo_id' => 71, 'descricao' => 'Salvo'], 'audit' => $audit], legacy: 'tipos_anexo', legacyOverrides: $change);
            self::assertSame('Salvo', $saved['descricao']);
            self::assertCount(2, $logs, json_encode($change));
            self::assertSame([], $logs[1]->getBeforeData());
            self::assertSame([], $logs[1]->getAfterData());
            self::assertFalse($logs[1]->getMetadata()['audit']['available']);
        }
    }

    private function execute(string $operation, array $body, string $contract = 'sursum-programs', ?\Throwable $failure = null, ?string $legacy = null, array $legacyOverrides = []): array
    {
        $endpoint = ['url' => '/SursumPrograms/despesas', 'method' => 'POST', 'bodyTemplate' => ['resource' => 'despesas', 'operation' => $operation]];
        $entityCode = $legacy ? ($legacyOverrides['entity'] ?? $legacy . '_progress') : 'despesas_api';
        $pk = $legacy === 'empresas' ? 'empresa_id' : ($legacy ? 'tipo_anexo_id' : 'despesa_id');
        if ($legacy) {
            $endpoint = ['url' => $legacyOverrides['route'] ?? '/med/web/SursumPrograms/' . str_replace('_', '-', $legacy), 'method' => $legacyOverrides['method'] ?? 'POST', 'bodyTemplate' => $legacyOverrides['template'] ?? ['operation' => $operation]];
        }
        $entity = (new BuilderEntity())->setCode($entityCode)->setEntityType('api')->setMetadata(['apiBinding' => ['sourceCode' => $legacyOverrides['source'] ?? 'motor_progress_' . $legacy], 'apiSource' => [
            'responseContract' => $contract, 'authHeaders' => [], 'listEndpoint' => $endpoint, 'listResponse' => ['itemsPath' => 'data'],
            $operation . 'Endpoint' => $endpoint, $operation . 'Response' => ['itemPath' => 'data'],
        ]]);
        $fields = $legacy === 'empresas' ? ['empresa_id' => 'integer', 'nome' => 'string', 'cod_emp_ems2' => 'integer', 'cod_emp_ems5' => 'integer']
            : ($legacy ? ['tipo_anexo_id' => 'integer', 'descricao' => 'string'] : ['despesa_id' => 'integer', 'descricao' => 'string', 'valor' => 'decimal', 'omitido' => 'string', 'senha' => 'string']);
        foreach ($fields as $field => $type) {
            $entity->addField((new BuilderField())->setCode($field)->setLabel($field)->setDataType($type)->setPrimaryKey($field === ($legacyOverrides['primaryKey'] ?? $pk)));
        }
        if (isset($legacyOverrides['primaryKey'])) { $entity->addField((new BuilderField())->setCode($legacyOverrides['primaryKey'])->setDataType('integer')->setPrimaryKey(true)); }
        $entities = $this->createStub(BuilderEntityRepository::class); $entities->method('findOneBy')->willReturn($entity);
        $logs = new \stdClass(); $logs->items = [];
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(static function ($item) use ($logs): void {
            if ($item instanceof RuntimeTransactionLog) { $logs->items[] = $item; }
        });
        $transactions = new RuntimeTransactionService($manager, $this->createStub(PermissionResolver::class), new RuntimeExecutionContext(),
            $this->createStub(RuntimeEntityDefinitionResolver::class), $this->createStub(RuntimeEnvironmentIdentityResolver::class));
        $transaction = new RuntimeTransaction();
        (new \ReflectionProperty($transactions, 'current'))->setValue($transactions, $transaction);
        $client = $this->createMock(RuntimeExternalJsonClient::class);
        $request = $client->expects(self::once())->method('request');
        if ($failure) { $request->willThrowException($failure); } else { $request->willReturn(['status' => 200, 'body' => $body]); }
        try {
        $result = (new RuntimeApiEntityActionService($entities, $transactions, $client))->handle('test', $operation,
            ['entityCode' => $entityCode, 'operation' => $operation], ($legacy ? ['id' => 71] : []) + ['record' => [$pk => $operation === 'create' ? 999 : 71,
                'descricao' => 'FORJADO', 'valor' => 999], 'audit' => ['before' => ['descricao' => 'FORJADO']]]);
        } catch (\Throwable $error) {
            if (!$failure) { throw $error; }
            $result = $error;
        }
        return [$result, $logs->items, $transaction];
    }
}
