<?php

declare(strict_types=1);

const MOCK_UID = 7;
const MOCK_DB = 'odoo_demo';
const MOCK_LOGIN = 'admin';
const MOCK_SECRET = 'admin123';
const MOCK_MODEL = 'res.partner';

$records = [
    ['id' => 1, 'name' => 'Azure Interior', 'country_id' => [33, 'Brasil']],
    ['id' => 2, 'name' => 'Blue Ocean', 'country_id' => [34, 'Argentina']],
    ['id' => 3, 'name' => 'Casa Lima', 'country_id' => [33, 'Brasil']],
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$raw = file_get_contents('php://input') ?: '';

if ($path === '/jsonrpc') {
    $payload = json_decode($raw, true);
    $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
    $service = (string) ($params['service'] ?? '');
    $method = (string) ($params['method'] ?? '');
    $args = is_array($params['args'] ?? null) ? $params['args'] : [];
    $id = $payload['id'] ?? null;

    try {
        if ($service === 'common' && $method === 'authenticate') {
            incrementAuthCounter();
            outputJson(['jsonrpc' => '2.0', 'id' => $id, 'result' => authenticateUser($args) ? MOCK_UID : 0]);
            return;
        }
        if ($service === 'object' && $method === 'execute_kw') {
            outputJson(['jsonrpc' => '2.0', 'id' => $id, 'result' => handleExecuteKw($args, $records)]);
            return;
        }
        if ($service === 'common' && $method === 'version') {
            outputJson(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['server_version' => '19.0']]);
            return;
        }

        outputJson(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['message' => 'Metodo nao suportado']]);
    } catch (RuntimeException $error) {
        outputJson(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['message' => $error->getMessage()]]);
    }
    return;
}

http_response_code(404);
echo 'not found';

function incrementAuthCounter(): void
{
    $file = $_SERVER['MOCK_ODOO_AUTH_COUNTER_FILE'] ?? getenv('MOCK_ODOO_AUTH_COUNTER_FILE') ?: '';
    if ($file === '') {
        return;
    }
    $count = is_file($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ($count + 1));
}

function authenticateUser(array $params): bool
{
    return ($params[0] ?? null) === MOCK_DB
        && ($params[1] ?? null) === MOCK_LOGIN
        && ($params[2] ?? null) === MOCK_SECRET;
}

function handleExecuteKw(array $params, array $records): mixed
{
    if (($params[0] ?? null) !== MOCK_DB || (int) ($params[1] ?? 0) !== MOCK_UID || ($params[2] ?? null) !== MOCK_SECRET) {
        throw new RuntimeException('Credenciais invalidas.');
    }
    if (($params[3] ?? null) !== MOCK_MODEL) {
        throw new RuntimeException('Modelo nao suportado.');
    }
    $method = (string) ($params[4] ?? '');
    $args = is_array($params[5] ?? null) ? $params[5] : [];
    $kwargs = is_array($params[6] ?? null) ? $params[6] : [];

    return match ($method) {
        'search_read' => handleSearchRead($records, $kwargs),
        'search_count' => count($records),
        'read' => handleRead($records, $args, $kwargs),
        default => throw new RuntimeException('Metodo Odoo nao suportado: ' . $method),
    };
}

function handleSearchRead(array $records, array $kwargs): array
{
    $fields = is_array($kwargs['fields'] ?? null) ? $kwargs['fields'] : [];
    return array_map(fn (array $record): array => pickFields($record, $fields), $records);
}

function handleRead(array $records, array $args, array $kwargs): array
{
    $ids = array_map('intval', is_array($args[0] ?? null) ? $args[0] : []);
    $fields = is_array($kwargs['fields'] ?? null) ? $kwargs['fields'] : [];
    $filtered = array_values(array_filter($records, fn (array $record): bool => in_array((int) $record['id'], $ids, true)));
    return array_map(fn (array $record): array => pickFields($record, $fields), $filtered);
}

function pickFields(array $record, array $fields): array
{
    if (!$fields) {
        return $record;
    }
    $selected = [];
    foreach ($fields as $field) {
        $selected[$field] = $record[$field] ?? null;
    }
    return $selected;
}

function outputJson(array $payload): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
