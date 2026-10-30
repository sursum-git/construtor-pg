<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json; charset=utf-8');
if ($path === '/identity') {
    echo json_encode(['user'=>$_SERVER['HTTP_X_SURSUM_USER']??null,'session'=>$_SERVER['HTTP_X_SESSION_ID']??null,
        'integration'=>$_SERVER['HTTP_X_EMPRESAS_TOKEN']??null,
        'authSource'=>$_SERVER['HTTP_X_SURSUM_AUTH_SOURCE']??null]); return;
}
if ($path === '/invalid') { echo '<html>Erro de upstream</html>'; return; }
if ($path === '/http-error') { http_response_code(503); echo '{"error":{"code":"UNAVAILABLE"}}'; return; }
if ($path === '/malformed-error') { echo '{"error":"Falha na gravação"}'; return; }
if ($path === '/business-error') {
    echo json_encode(['error' => ['code' => 'EMPRESA_VALIDATION', 'message' => 'Nome deve ter até 50 caracteres.', 'status' => 422]], JSON_UNESCAPED_UNICODE);
    return;
}
echo json_encode(['data' => ['empresa_id' => 1, 'nome' => 'Empresa São José']], JSON_UNESCAPED_UNICODE);
