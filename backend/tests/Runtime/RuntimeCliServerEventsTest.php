<?php
namespace App\Tests\Runtime;

use PHPUnit\Framework\TestCase;

final class RuntimeCliServerEventsTest extends TestCase
{
    private static mixed $server;
    private static array $pipes = [];
    private static string $base;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false); fclose($socket);
        self::$base = 'http://' . $address;
        self::$server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/runtime-events.php'],
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

    public function testRuntimeStreamEndsAfterAuthenticatedPollAndReleasesTheSingleWorker(): void
    {
        $stream = $this->http('/events?timeout=45&interval=5');
        $next = $this->http('/fast');
        self::assertSame(0, $stream['errno'], 'O stream local nao terminou antes do timeout do cliente.');
        self::assertSame(0, $next['errno'], 'A segunda requisicao ficou bloqueada pelo stream.');
        self::assertSame('available', $next['body']);
        self::assertStringContainsString("retry: 5000\n\n", $stream['body']);
        self::assertStringContainsString('event: runtime-messages', $stream['body']);
        self::assertStringContainsString('"sessionChecks":1', $stream['body']);
        self::assertStringContainsString('"flushed":true', $stream['body']);
        self::assertLessThan(2.0, $stream['duration']);
    }

    public function testChatEndpointStreamAlsoReleasesTheBuiltinWorkerAfterItsAuthorizedDispatch(): void
    {
        $stream = $this->http('/endpoint-events?stream=1&timeout=45&interval=3');
        self::assertSame(0, $stream['errno']);
        self::assertStringContainsString('event: home-chat-events', $stream['body']);
        self::assertStringContainsString('"sessionChecks":2', $stream['body']);
        self::assertSame('available', $this->http('/fast')['body']);
    }

    public function testRevocationCheckStillEmitsRuntimeErrorBeforeTheLocalStreamCloses(): void
    {
        $stream = $this->http('/events?timeout=45', ['X-Probe-Revoke: 1']);
        self::assertSame(0, $stream['errno']);
        self::assertStringContainsString('event: runtime-error', $stream['body']);
        self::assertStringContainsString('SESSION_REVOKED', $stream['body']);
        self::assertStringNotContainsString('event: runtime-messages', $stream['body']);
    }

    public function testOtherSapisRetainTheTimedPollingLoop(): void
    {
        self::assertSame('cli', PHP_SAPI);
        $sessions = $this->createStub(\App\Runtime\RuntimeSessionGuard::class);
        $sessions->method('ensureActive')->willReturn(new \App\Entity\RuntimeUserSession());
        $messages = $this->createStub(\App\Runtime\RuntimeMessageService::class);
        $messages->method('poll')->willReturn(['messages' => []]);
        $controller = new \App\Controller\RuntimeController($this->createStub(\App\Runtime\ScreenDefinitionService::class),
            $this->createStub(\App\Runtime\RuntimeEndpointDispatcher::class), $messages,
            $this->createStub(\App\Runtime\RuntimeLiteralService::class), $this->createStub(\App\Runtime\RuntimeProcessHandler::class), $sessions);
        $callback = $controller->events(\Symfony\Component\HttpFoundation\Request::create('/events?timeout=1&interval=1'))->getCallback();
        $startedAt = microtime(true);
        ob_start(static fn ($chunk) => '');
        try { $callback(); } finally { ob_end_clean(); }
        self::assertGreaterThanOrEqual(0.9, microtime(true) - $startedAt);
    }
    private function http(string $path, array $extraHeaders = []): array
    {
        $ch = curl_init(self::$base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 2000,
            CURLOPT_HTTPHEADER => ['X-Probe-Auth: allowed', ...$extraHeaders]]);
        $body = curl_exec($ch);
        $result = ['body' => is_string($body) ? $body : '', 'errno' => curl_errno($ch),
            'duration' => curl_getinfo($ch, CURLINFO_TOTAL_TIME)];
        curl_close($ch);
        return $result;
    }
}
