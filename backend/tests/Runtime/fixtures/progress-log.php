<?php
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scenario = basename($path);
$id = 'EXE-20261015-050000-000001';
if ($scenario === 'zero') { $id = 'EXE-20261015-050000-000000'; }
if ($scenario === 'collection-failure') { $id = 'EXE-20261015-050000-000002'; }
if (str_contains($path, '/SursumDynamicQuery/')) {
    if ($path !== '/med/web/SursumDynamicQuery/executions/EXE-20261015-050000-000001/events'
        || ($_SERVER['HTTP_X_PROBE_TOKEN'] ?? '') !== 'fixture-secret'
        || ($_SERVER['HTTP_X_SURSUM_USER'] ?? '') !== 'admin'
        || ($_SERVER['HTTP_X_SESSION_ID'] ?? '') !== 'verified'
        || $_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET !== ['limit' => '500']) {
        http_response_code(503); echo '{"error":"fixture-secret"}'; return;
    }
    // These response headers must not trigger a recursive collection.
    header('X-Sursum-Execution-Id: EXE-20261015-050000-000002');
    header('X-Sursum-Log-Count: 3');
    if (($_SERVER['HTTP_X_PROBE_SCENARIO'] ?? '') === 'missing-events') {
        echo json_encode(['success' => true, 'executionId' => $id, 'data' => []]); return;
    }
    if (($_SERVER['HTTP_X_PROBE_SCENARIO'] ?? '') === 'invalid-utf8') {
        echo '{"success":true,"executionId":"' . $id . '","data":[{"eventMessage":"' . "\xe3" . '"}]}'; return;
    }
    $context = json_encode(['pasoeAgent' => 'agent-3', 'pasoeInstance' => 'sursum-api', 'currentUser' => 'admin',
        'pasoePid' => '123', 'Authorization' => 'fixture-secret', 'payload' => 'private-payload', 'custom' => ['password' => 'fixture-secret']]);
    $warningMessage = match ($_SERVER['HTTP_X_PROBE_SCENARIO'] ?? '') {
        'authorization-bearer' => 'Token bearer-component-credential recusado',
        'authorization-basic' => 'Credencial dGVzdDpmaXh0dXJlLXBhc3N3b3Jk e senha fixture-password recusadas',
        'long-message' => str_repeat('x', 3995) . 'fixture-secret',
        default => 'Token fixture-secret removido',
    };
    $event = ['eventId' => $id . '-050000-000003', 'executionId' => $id, 'eventStatus' => 'log.info',
        'eventMessage' => 'Etapa São José', 'eventTime' => (($_SERVER['HTTP_X_PROBE_SCENARIO'] ?? '') === 'european-time' ? '15/10/2026 21:08:43,321' : '2026-10-15T12:30:00'), 'eventContextJson' => $context];
    echo json_encode(['success' => true, 'executionId' => $id, 'data' => [$event,
        array_replace($event, ['eventStatus' => 'completed']),
        array_replace($event, ['executionId' => 'forged']),
        array_replace($event, ['eventId' => $id . '-050000-000004', 'eventStatus' => 'log.warning', 'eventMessage' => $warningMessage])]], JSON_UNESCAPED_UNICODE);
    return;
}
header('X-Sursum-Execution-Id: ' . ($scenario === 'invalid-id' ? 'EXE-../../forged' : $id));
header('X-Sursum-Log-Count: ' . ($scenario === 'zero' ? '0' : '2'));
if ($scenario === 'business-error') {
    echo '{"error":{"code":"PROBE_REJECTED","message":"Operacao recusada","status":409}}'; return;
}
echo '{"data":{"despesa_id":71,"executionId":"forged","eventsUrl":"http://forged"}}';
