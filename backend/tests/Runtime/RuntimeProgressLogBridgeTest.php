<?php
namespace App\Tests\Runtime;

use App\Auth\AuthenticatedSessionResolver;
use App\Entity\RuntimeTransaction;
use App\Entity\RuntimeTransactionLog;
use App\Entity\RuntimeUserSession;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeExecutionContext;
use App\Runtime\RuntimeExternalJsonClient;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeTransactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RuntimeProgressLogBridgeTest extends TestCase
{
    private static mixed $server;
    private static array $pipes = [];
    private static string $base;
    private array $logs = [];
    private RuntimeTransactionService $transactions;
    private RuntimeTransaction $transaction;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$base = 'http://' . $address;
        self::$server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/progress-log.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], self::$pipes);
        $deadline = microtime(true) + 5;
        do {
            $ready = @stream_socket_client('tcp://' . $address, $errno, $message, 0.1);
            if ($ready) { fclose($ready); return; }
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::fail('Servidor HTTP de teste indisponivel.');
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$server);
        foreach (self::$pipes as $pipe) { fclose($pipe); }
        proc_close(self::$server);
    }

    protected function setUp(): void
    {
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof RuntimeTransactionLog) { $this->logs[] = $entity; }
        });
        $this->transactions = new RuntimeTransactionService($manager, $this->createStub(PermissionResolver::class),
            new RuntimeExecutionContext(), $this->createStub(RuntimeEntityDefinitionResolver::class),
            $this->createStub(RuntimeEnvironmentIdentityResolver::class));
        $this->transaction = new RuntimeTransaction();
        (new \ReflectionProperty($this->transactions, 'current'))->setValue($this->transactions, $this->transaction);
    }

    public function testSuccessImportsOnlyManualLogsAndSafeIdentityIntoCurrentTransaction(): void
    {
        $result = $this->request('success');
        self::assertSame(71, $result['body']['data']['despesa_id']);
        self::assertSame(['api.progress.execution', 'progress.log.info', 'progress.log.warning'], $this->types());
        self::assertSame('EXE-20261015-050000-000001', $this->logs[0]->getMetadata()['executionId']);
        self::assertSame('Etapa São José', $this->logs[1]->getMessage());
        self::assertSame('2026-10-15T12:30:00', $this->logs[1]->getMetadata()['occurredAt']);
        self::assertSame('agent-3', $this->logs[1]->getMetadata()['pasoeAgent']);
        self::assertSame('sursum-api', $this->logs[1]->getMetadata()['pasoeInstance']);
        self::assertSame('admin', $this->logs[1]->getMetadata()['currentUser']);
        self::assertSame($this->transaction, $this->logs[1]->getTransaction());
        $serialized = json_encode(array_map(static fn ($log) => [$log->getMessage(), $log->getMetadata()], $this->logs));
        foreach (['fixture-secret', 'private-payload', 'forged', 'eventContextJson', 'Authorization', 'http://'] as $secret) {
            self::assertStringNotContainsString($secret, $serialized);
        }
    }

    public function testBusinessFailureStillImportsLogsBeforeThrowing(): void
    {
        try {
            $this->request('business-error');
            self::fail('Erro de negocio ignorado.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('PROBE_REJECTED', $error->getErrorCode());
            self::assertSame(409, $error->getStatusCode());
        }
        self::assertSame(['api.progress.execution', 'progress.log.info', 'progress.log.warning'], $this->types());
    }

    public function testZeroCountAvoidsAnyEventRequest(): void
    {
        $this->request('zero');
        self::assertSame(['api.progress.execution'], $this->types());
        self::assertSame(0, $this->logs[0]->getMetadata()['logCount']);
    }

    public function testCollectionFailureDoesNotChangeBusinessSuccessOrExposeUpstreamDetails(): void
    {
        self::assertSame(71, $this->request('collection-failure')['body']['data']['despesa_id']);
        self::assertSame(['api.progress.execution', 'api.progress.logs.unavailable'], $this->types());
        self::assertStringNotContainsString('fixture-secret', json_encode($this->logs[1]->getMetadata()));
        self::assertSame('http_error', $this->logs[1]->getMetadata()['reason']);
        self::assertSame(503, $this->logs[1]->getMetadata()['httpStatus']);
    }

    public function testInvalidExecutionHeaderCannotSelectAnEndpoint(): void
    {
        $this->request('invalid-id');
        self::assertSame([], $this->types());
    }

    public function testGenericContractDoesNotFetchOrTrustExecutionHeaders(): void
    {
        $this->request('success', 'generic');
        self::assertSame([], $this->types());
    }

    public function testUnexpectedRegisteredPathDoesNotSendCredentialsToDerivedEndpoint(): void
    {
        $this->request('success', 'sursum-programs', '/unexpected/');
        self::assertSame(['api.progress.execution', 'api.progress.logs.unavailable'], $this->types());
    }

    public function testCreateBindsServerGeneratedPrimaryKeyToExistingTransaction(): void
    {
        $this->transaction->setRecordId(999);
        $client = $this->createStub(RuntimeExternalJsonClient::class);
        $client->method('request')->willReturn(['status' => 200, 'body' => ['data' => ['despesa_id' => 71, 'descricao' => 'Criado']]]);
        $entity = (new \App\Entity\BuilderEntity())->setCode('despesas_api')->setEntityType('api')->setMetadata(['apiSource' => [
            'authHeaders' => [], 'listEndpoint' => ['url' => '/example'], 'listResponse' => ['itemsPath' => 'data'],
            'createEndpoint' => ['url' => '/example', 'method' => 'POST'], 'createResponse' => ['itemPath' => 'data'],
        ]]);
        $entity->addField((new \App\Entity\BuilderField())->setCode('despesa_id')->setLabel('ID')->setDataType('integer')->setPrimaryKey(true));
        $entity->addField((new \App\Entity\BuilderField())->setCode('descricao')->setLabel('Descricao')->setDataType('string'));
        $entities = $this->createStub(\App\Repository\BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturn($entity);
        $service = new \App\Runtime\RuntimeApiEntityActionService($entities, $this->transactions, $client);
        self::assertSame(71, $service->handle('test', 'create', ['entityCode' => 'despesas_api', 'operation' => 'create'],
            ['record' => ['despesa_id' => 999, 'descricao' => 'Enviado']])['despesa_id']);
        self::assertSame('71', $this->transaction->getRecordId());
    }
    public function testCredentialIsRedactedBeforeLongMessagesAreTruncated(): void
    {
        $this->request('long-message');
        self::assertCount(3, $this->logs);
        self::assertStringNotContainsString('fixtu', $this->logs[2]->getMessage());
        self::assertLessThanOrEqual(4000, mb_strlen($this->logs[2]->getMessage()));
    }
    public function testMissingQueuedMessagesRecordACollectionWarningWithoutFailingBusiness(): void
    {
        self::assertSame(71, $this->request('missing-events')['body']['data']['despesa_id']);
        self::assertSame('incomplete', $this->logs[1]->getMetadata()['reason']);
        self::assertSame(['api.progress.execution', 'api.progress.logs.unavailable'], $this->types());
    }
    public function testAuthorizationCredentialsAreRedactedWhenLoggedWithoutTheScheme(): void
    {
        foreach ([
            ['authorization-bearer', 'Bearer bearer-component-credential', ['bearer-component-credential']],
            ['authorization-basic', 'Basic dGVzdDpmaXh0dXJlLXBhc3N3b3Jk', ['dGVzdDpmaXh0dXJlLXBhc3N3b3Jk', 'fixture-password']],
        ] as [$scenario, $authorization, $secrets]) {
            $this->logs = [];
            $this->request($scenario, extraHeaders: ['Authorization' => $authorization]);
            self::assertSame(['api.progress.execution', 'progress.log.info', 'progress.log.warning'], $this->types());
            foreach ($secrets as $secret) {
                self::assertStringNotContainsString($secret, $this->logs[2]->getMessage());
            }
            self::assertStringContainsString('[redigido]', $this->logs[2]->getMessage());
        }
    }
    public function testInvalidUtf8FromTheMonitorIsReportedWithoutChangingBusinessOrGuessingEncoding(): void
    {
        self::assertSame(71, $this->request('invalid-utf8')['body']['data']['despesa_id']);
        self::assertSame(['api.progress.execution', 'api.progress.logs.unavailable'], $this->types());
        self::assertSame('response_invalid', $this->logs[1]->getMetadata()['reason']);
        self::assertSame(JSON_ERROR_UTF8, $this->logs[1]->getMetadata()['jsonError']);
        self::assertSame(200, $this->logs[1]->getMetadata()['httpStatus']);
    }
    public function testEuropeanProgressTimestampPreservesCommaMilliseconds(): void
    {
        $this->request('european-time');
        self::assertSame('15/10/2026 21:08:43,321', $this->logs[1]->getMetadata()['occurredAt']);
    }
    private function request(string $scenario, string $contract = 'sursum-programs', string $prefix = '/med/web/SursumPrograms/', array $extraHeaders = []): array
    {
        $resolver = $this->createStub(AuthenticatedSessionResolver::class);
        $resolver->method('resolve')->willReturn((new RuntimeUserSession())->setStatus('active')->setUserId('admin')->setSessionId('verified'));
        return (new RuntimeExternalJsonClient($resolver, $this->transactions))->request(self::$base . $prefix . $scenario,
            'POST', ['X-Probe-Scenario' => $scenario, 'X-Probe-Token' => 'fixture-secret', 'X-Sursum-User' => 'forged'] + $extraHeaders,
            ['executionId' => 'forged', 'eventsUrl' => 'http://forged'], 5, ['responseContract' => $contract]);
    }

    private function types(): array
    {
        return array_map(static fn ($log) => $log->getEventType(), $this->logs);
    }
}
