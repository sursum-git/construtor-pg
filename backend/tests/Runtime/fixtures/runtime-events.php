<?php
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Controller\RuntimeController;
use App\Entity\RuntimeUserSession;
use App\Runtime\RuntimeEndpointDispatcher;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeMessageService;
use App\Runtime\RuntimeSessionGuard;
use Symfony\Component\HttpFoundation\Request;

if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/fast') { echo 'available'; return; }
$sessions = new class extends RuntimeSessionGuard {
    public int $calls = 0;
    public function __construct() {}
    public function ensureActive(bool $touch = true): RuntimeUserSession
    {
        ++$this->calls;
        if (($_SERVER['HTTP_X_PROBE_AUTH'] ?? '') !== 'allowed') {
            throw new RuntimeHttpException('AUTH_REQUIRED', 'Autenticacao obrigatoria.', 401);
        }
        if (!$touch && ($_SERVER['HTTP_X_PROBE_REVOKE'] ?? '') === '1') {
            throw new RuntimeHttpException('SESSION_REVOKED', 'Sessao revogada.', 401);
        }
        return (new RuntimeUserSession())->setStatus('active');
    }
};
$messages = new class($sessions) extends RuntimeMessageService {
    public function __construct(private readonly RuntimeSessionGuard $guard) {}
    public function poll(bool $flush = false): array
    {
        return ['messages' => ($_SERVER['HTTP_X_PROBE_REVOKE'] ?? '') === '1' ? []
            : [['type' => 'force_logout', 'messageId' => 1, 'sessionChecks' => $this->guard->calls, 'flushed' => $flush]]];
    }
};
$dispatcher = new class($sessions) extends RuntimeEndpointDispatcher {
    public function __construct(private readonly RuntimeSessionGuard $guard) {}
    public function dispatchEvent(string $screenId, string $endpointId, array $payload): array
    {
        $this->guard->ensureActive();
        return ['messages' => [['messageId' => 1, 'sessionChecks' => $this->guard->calls]]];
    }
};
$withoutConstructor = static fn (string $class) => (new ReflectionClass($class))->newInstanceWithoutConstructor();
$controller = new RuntimeController($withoutConstructor(App\Runtime\ScreenDefinitionService::class), $dispatcher, $messages,
    $withoutConstructor(App\Runtime\RuntimeLiteralService::class), $withoutConstructor(App\Runtime\RuntimeProcessHandler::class), $sessions);
try {
    $request = Request::create($_SERVER['REQUEST_URI']);
    $response = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/endpoint-events'
        ? $controller->endpointEvents('home', 'home.chat.events', $request) : $controller->events($request);
    $response->send();
} catch (RuntimeHttpException $error) {
    echo json_encode(['error' => $error->getErrorCode()]);
}
