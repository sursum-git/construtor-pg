<?php
namespace App\Tests\Runtime;

use App\Runtime\RuntimeExternalJsonClient;
use App\Runtime\RuntimeHttpException;
use App\Auth\AuthenticatedSessionResolver;
use App\Entity\RuntimeUserSession;
use PHPUnit\Framework\TestCase;

final class RuntimeExternalJsonClientTest extends TestCase
{
    private static mixed $server;
    private static array $pipes = [];
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$baseUrl = 'http://' . $address;
        self::$server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/external-json.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], self::$pipes);
        self::assertIsResource(self::$server);
        $deadline = microtime(true) + 5;
        do {
            $ready = @stream_socket_client('tcp://' . $address, $errno, $message, 0.1);
            if ($ready) { fclose($ready); return; }
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::fail('Servidor de teste HTTP não iniciou.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            foreach (self::$pipes as $pipe) { if (is_resource($pipe)) fclose($pipe); }
            proc_close(self::$server);
        }
    }

    public function testSursumBusinessErrorInsideHttp200FailsTheRequest(): void
    {
        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Nome deve ter até 50 caracteres.');
        (new RuntimeExternalJsonClient())->request(self::$baseUrl . '/business-error', 'POST', [], [], 5,
            ['responseContract' => 'sursum-programs']);
    }

    public function testSursumErrorPreservesSafeCodeAndStatus(): void
    {
        try {
            (new RuntimeExternalJsonClient())->request(self::$baseUrl . '/business-error', 'POST', [], [], 5,
                ['responseContract' => 'sursum-programs']);
            self::fail('Erro de negócio foi aceito como sucesso.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('EMPRESA_VALIDATION', $error->getErrorCode());
            self::assertSame(422, $error->getStatusCode());
        }
    }

    public function testGenericSourceKeepsItsExistingResponseContract(): void
    {
        $response = (new RuntimeExternalJsonClient())->request(self::$baseUrl . '/business-error', 'GET', [], null, 5);
        self::assertSame(200, $response['status']);
        self::assertSame('EMPRESA_VALIDATION', $response['body']['error']['code']);
    }

    public function testSuccessfulSursumResponseKeepsUnicodeAndData(): void
    {
        $response = (new RuntimeExternalJsonClient())->request(self::$baseUrl . '/success', 'GET', [], null, 5,
            ['responseContract' => 'sursum-programs']);
        self::assertSame('Empresa São José', $response['body']['data']['nome']);
    }

    public function testInvalidJsonStillFails(): void
    {
        $this->expectException(RuntimeHttpException::class);
        (new RuntimeExternalJsonClient())->request(self::$baseUrl . '/invalid', 'GET', [], null, 5);
    }

    public function testHttpFailureStillFails(): void
    {
        $this->expectException(RuntimeHttpException::class);
        (new RuntimeExternalJsonClient())->request(self::$baseUrl . '/http-error', 'GET', [], null, 5);
    }

    public function testMalformedSursumErrorCannotConfirmASave(): void
    {
        $this->expectException(RuntimeHttpException::class);
        (new RuntimeExternalJsonClient())->request(self::$baseUrl . '/malformed-error', 'POST', [], [], 5,
            ['responseContract' => 'sursum-programs']);
    }

    public function testSursumForwardsAuthenticatedIdentityAndOverridesConfiguredSpoofedHeaders(): void
    {
        $session=(new RuntimeUserSession())->setUserId('admin')->setSessionId('session-verified')->setStatus('active');
        $resolver=$this->createMock(AuthenticatedSessionResolver::class);
        $resolver->expects(self::once())->method('resolve')->willReturn($session);
        $response=(new RuntimeExternalJsonClient($resolver))->request(self::$baseUrl.'/identity','POST',[
            'x-sursum-user'=>'forged', 'X-SESSION-ID'=>'forged-session', 'X-Empresas-Token'=>'test-token',
        ],['userName'=>'forged-payload'],5,['responseContract'=>'sursum-programs']);
        self::assertSame('admin',$response['body']['user']);
        self::assertSame('session-verified',$response['body']['session']);
        self::assertSame('test-token',$response['body']['integration']);
    }

    public function testSursumWithoutAuthenticatedSessionDoesNotForwardConfiguredUser(): void
    {
        $resolver=$this->createStub(AuthenticatedSessionResolver::class);
        $resolver->method('resolve')->willReturn(null);
        $response=(new RuntimeExternalJsonClient($resolver))->request(self::$baseUrl.'/identity','GET',[
            'X-Sursum-User'=>'forged','X-Session-Id'=>'forged-session',
        ],null,5,['responseContract'=>'sursum-programs']);
        self::assertNull($response['body']['user']);
        self::assertNull($response['body']['session']);
    }

    public function testGenericContractDoesNotInjectInternalIdentity(): void
    {
        $resolver=$this->createMock(AuthenticatedSessionResolver::class);
        $resolver->expects(self::never())->method('resolve');
        $response=(new RuntimeExternalJsonClient($resolver))->request(self::$baseUrl.'/identity','GET',[],null,5);
        self::assertNull($response['body']['user']);
        self::assertNull($response['body']['session']);
    }

    public function testSursumForwardsOnlyVerifiedAuthenticationProvider(): void
    {
        $session = (new RuntimeUserSession())->setUserId('ciw-test-user')->setSessionId('ciw-session')
            ->setStatus('active')->setSessionProperties(['authentication' => ['provider' => 'ima-ldap']]);
        $resolver = $this->createStub(AuthenticatedSessionResolver::class);
        $resolver->method('resolve')->willReturn($session);
        $response = (new RuntimeExternalJsonClient($resolver))->request(self::$baseUrl . '/identity', 'POST', [
            'X-Sursum-Auth-Source' => 'forged-local', 'x-SURSUM-auth-SOURCE' => 'forged-other',
        ], ['auth_provider' => 'forged-payload'], 5, ['responseContract' => 'sursum-programs', 'authSource' => 'forged-context']);
        self::assertSame('ima-ldap', $response['body']['authSource']);
    }

    public function testSursumDoesNotForwardAuthenticationSourceWithoutActiveSession(): void
    {
        foreach ([null, (new RuntimeUserSession())->setUserId('ciw-test-user')->setStatus('revoked')
            ->setSessionProperties(['authentication' => ['provider' => 'ima-ldap']])] as $session) {
            $resolver = $this->createStub(AuthenticatedSessionResolver::class);
            $resolver->method('resolve')->willReturn($session);
            $response = (new RuntimeExternalJsonClient($resolver))->request(self::$baseUrl . '/identity', 'GET', [
                'x-sursum-auth-source' => 'forged-provider',
            ], null, 5, ['responseContract' => 'sursum-programs']);
            self::assertNull($response['body']['authSource']);
        }
    }

    public function testSursumMissingOrMalformedProviderCannotSupplyTrustedOrigin(): void
    {
        foreach ([[], ['authentication' => 'ima-ldap'], ['authentication' => ['provider' => ['ima-ldap']]],
            ['authentication' => ['provider' => '']], ['authentication' => ['provider' => 'ima' . "\r\n" . 'X-Injected: true']]] as $properties) {
            $session = (new RuntimeUserSession())->setUserId('ciw-test-user')->setSessionId('ciw-session')
                ->setStatus('active')->setSessionProperties($properties);
            $resolver = $this->createStub(AuthenticatedSessionResolver::class);
            $resolver->method('resolve')->willReturn($session);
            $response = (new RuntimeExternalJsonClient($resolver))->request(self::$baseUrl . '/identity', 'GET', [
                'X-Sursum-Auth-Source' => 'forged-provider',
            ], null, 5, ['responseContract' => 'sursum-programs']);
            self::assertNull($response['body']['authSource']);
        }
    }

    public function testSursumProviderIsReadForEachSessionAndIsNotCached(): void
    {
        $sessions = [];
        foreach (['ima-ldap', 'local'] as $provider) {
            $sessions[] = (new RuntimeUserSession())->setUserId('ciw-test-user')->setSessionId('ciw-session-' . $provider)
                ->setStatus('active')->setSessionProperties(['authentication' => ['provider' => $provider]]);
        }
        $resolver = $this->createMock(AuthenticatedSessionResolver::class);
        $resolver->expects(self::exactly(2))->method('resolve')->willReturnOnConsecutiveCalls(...$sessions);
        $client = new RuntimeExternalJsonClient($resolver);
        foreach (['ima-ldap', 'local'] as $provider) {
            $response = $client->request(self::$baseUrl . '/identity', 'GET', [], null, 5, ['responseContract' => 'sursum-programs']);
            self::assertSame($provider, $response['body']['authSource']);
        }
    }

    public function testGenericSourceKeepsItsConfiguredAuthenticationSourceHeader(): void
    {
        $resolver = $this->createMock(AuthenticatedSessionResolver::class);
        $resolver->expects(self::never())->method('resolve');
        $response = (new RuntimeExternalJsonClient($resolver))->request(self::$baseUrl . '/identity', 'GET', [
            'X-Sursum-Auth-Source' => 'external-provider',
        ], null, 5);
        self::assertSame('external-provider', $response['body']['authSource']);
    }
}
