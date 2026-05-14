<?php

namespace App\Odoo;

use App\Runtime\RuntimeHttpException;

class OdooClient
{
    public function testConnection(array $config): array
    {
        $session = $this->openSession($config);
        $normalized = $session->getConfig();
        $version = $this->getVersion($normalized);

        return [
            'ok' => true,
            'transport' => $normalized['transport'],
            'uid' => $session->getUid(),
            'version' => $version,
        ];
    }

    public function fieldsGet(array $config): array
    {
        return $this->fieldsGetWithSession($this->openSession($config));
    }

    public function fieldsGetWithSession(OdooExecutionContext $session): array
    {
        $normalized = $session->getConfig();
        $result = $this->executeKwWithSession($session, $normalized['model'], 'fields_get', [], [
            'attributes' => ['string', 'help', 'type', 'required', 'readonly', 'relation', 'selection'],
            'context' => $normalized['defaultContext'],
        ]);

        if (!is_array($result)) {
            throw new RuntimeHttpException('ODOO_FIELDS_GET_INVALID', 'Odoo nao retornou um mapa valido de campos.', 422, [
                'model' => $normalized['model'],
            ]);
        }

        return $result;
    }

    public function searchRead(array $config, array $parameters): array
    {
        return $this->searchReadWithSession($this->openSession($config), $parameters);
    }

    public function searchReadWithSession(OdooExecutionContext $session, array $parameters): array
    {
        $normalized = $session->getConfig();
        $domain = $this->normalizeDomain($parameters['domain'] ?? $normalized['defaultDomain']);
        $fields = $this->normalizeFieldNames($parameters['fields'] ?? []);
        $kwargs = [
            'context' => $this->normalizeContext($parameters['context'] ?? $normalized['defaultContext']),
            'domain' => $domain,
            'fields' => $fields,
            'offset' => max(0, (int) ($parameters['offset'] ?? 0)),
            'limit' => max(1, min(500, (int) ($parameters['limit'] ?? $normalized['defaultLimit'] ?? 80))),
            'order' => trim((string) ($parameters['order'] ?? $normalized['defaultOrder'] ?? '')),
        ];

        return $this->executeKwWithSession($session, $normalized['model'], 'search_read', [$domain], $kwargs);
    }

    public function searchCount(array $config, array $domain, array $context = []): int
    {
        return $this->searchCountWithSession($this->openSession($config), $domain, $context);
    }

    public function searchCountWithSession(OdooExecutionContext $session, array $domain, array $context = []): int
    {
        $normalized = $session->getConfig();
        $result = $this->executeKwWithSession($session, $normalized['model'], 'search_count', [$this->normalizeDomain($domain)], [
            'context' => $this->normalizeContext($context ?: $normalized['defaultContext']),
        ]);

        return (int) $result;
    }

    public function read(array $config, array $ids, array $fields, array $context = []): array
    {
        return $this->readWithSession($this->openSession($config), $ids, $fields, $context);
    }

    public function readWithSession(OdooExecutionContext $session, array $ids, array $fields, array $context = []): array
    {
        $normalized = $session->getConfig();
        $cleanIds = array_values(array_filter(array_map(function ($value) {
            if (is_int($value)) {
                return $value;
            }
            if (is_numeric($value) && (string) (int) $value === (string) $value) {
                return (int) $value;
            }

            return null;
        }, $ids), static fn ($value) => $value !== null));
        if (!$cleanIds) {
            throw new RuntimeHttpException('ODOO_RECORD_ID_REQUIRED', 'Informe um identificador valido do registro Odoo.', 422);
        }

        $result = $this->executeKwWithSession($session, $normalized['model'], 'read', [$cleanIds], [
            'fields' => $this->normalizeFieldNames($fields),
            'context' => $this->normalizeContext($context ?: $normalized['defaultContext']),
        ]);

        return is_array($result) ? $result : [];
    }

    public function openSession(array $config): OdooExecutionContext
    {
        $normalized = $this->normalizeConfig($config);
        $uid = $this->authenticate($normalized);

        return new OdooExecutionContext($normalized, $uid);
    }

    public function getVersion(array $config): array
    {
        $normalized = $this->normalizeConfig($config);

        return match ($normalized['transport']) {
            'xmlrpc' => $this->xmlRpcCall($normalized['baseUrl'] . '/xmlrpc/2/common', 'version', [], (int) $normalized['timeoutSeconds']),
            'jsonrpc' => $this->jsonRpcCall($normalized['baseUrl'] . '/jsonrpc', 'common', 'version', [], (int) $normalized['timeoutSeconds']),
            default => throw new RuntimeHttpException('ODOO_TRANSPORT_NOT_SUPPORTED', 'Transporte Odoo nao suportado nesta etapa.', 422, [
                'transport' => $normalized['transport'],
            ]),
        };
    }

    public function authenticate(array $config): int
    {
        $normalized = $this->normalizeConfig($config);
        $result = match ($normalized['transport']) {
            'xmlrpc' => $this->xmlRpcCall($normalized['baseUrl'] . '/xmlrpc/2/common', 'authenticate', [
                $normalized['database'],
                $normalized['login'],
                $normalized['secretValue'],
                [],
            ], (int) $normalized['timeoutSeconds']),
            'jsonrpc' => $this->jsonRpcCall($normalized['baseUrl'] . '/jsonrpc', 'common', 'authenticate', [
                $normalized['database'],
                $normalized['login'],
                $normalized['secretValue'],
                new \stdClass(),
            ], (int) $normalized['timeoutSeconds']),
            default => throw new RuntimeHttpException('ODOO_TRANSPORT_NOT_SUPPORTED', 'Transporte Odoo nao suportado nesta etapa.', 422, [
                'transport' => $normalized['transport'],
            ]),
        };

        $uid = (int) $result;
        if ($uid <= 0) {
            throw new RuntimeHttpException('ODOO_AUTH_FAILED', 'Nao foi possivel autenticar no Odoo com as credenciais informadas.', 422, [
                'transport' => $normalized['transport'],
                'database' => $normalized['database'],
                'login' => $normalized['login'],
            ]);
        }

        return $uid;
    }

    public function executeKw(array $config, int $uid, string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        $normalized = $this->normalizeConfig($config);

        return match ($normalized['transport']) {
            'xmlrpc' => $this->xmlRpcCall($normalized['baseUrl'] . '/xmlrpc/2/object', 'execute_kw', array_values(array_filter([
                $normalized['database'],
                $uid,
                $normalized['secretValue'],
                $model,
                $method,
                $args,
                $kwargs ?: null,
            ], static fn ($value) => $value !== null)), (int) $normalized['timeoutSeconds']),
            'jsonrpc' => $this->jsonRpcCall($normalized['baseUrl'] . '/jsonrpc', 'object', 'execute_kw', array_values(array_filter([
                $normalized['database'],
                $uid,
                $normalized['secretValue'],
                $model,
                $method,
                $args,
                $kwargs ?: null,
            ], static fn ($value) => $value !== null)), (int) $normalized['timeoutSeconds']),
            default => throw new RuntimeHttpException('ODOO_TRANSPORT_NOT_SUPPORTED', 'Transporte Odoo nao suportado nesta etapa.', 422, [
                'transport' => $normalized['transport'],
            ]),
        };
    }

    public function executeKwWithSession(OdooExecutionContext $session, string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        $normalized = $session->getConfig();
        $uid = $session->getUid();

        return match ($normalized['transport']) {
            'xmlrpc' => $this->xmlRpcCall($normalized['baseUrl'] . '/xmlrpc/2/object', 'execute_kw', array_values(array_filter([
                $normalized['database'],
                $uid,
                $normalized['secretValue'],
                $model,
                $method,
                $args,
                $kwargs ?: null,
            ], static fn ($value) => $value !== null)), (int) $normalized['timeoutSeconds']),
            'jsonrpc' => $this->jsonRpcCall($normalized['baseUrl'] . '/jsonrpc', 'object', 'execute_kw', array_values(array_filter([
                $normalized['database'],
                $uid,
                $normalized['secretValue'],
                $model,
                $method,
                $args,
                $kwargs ?: null,
            ], static fn ($value) => $value !== null)), (int) $normalized['timeoutSeconds']),
            default => throw new RuntimeHttpException('ODOO_TRANSPORT_NOT_SUPPORTED', 'Transporte Odoo nao suportado nesta etapa.', 422, [
                'transport' => $normalized['transport'],
            ]),
        };
    }

    public function normalizeConfig(array $config): array
    {
        $baseUrl = trim((string) ($config['baseUrl'] ?? ''));
        $database = trim((string) ($config['database'] ?? ''));
        $login = trim((string) ($config['login'] ?? ''));
        $secretMode = strtolower(trim((string) ($config['secretMode'] ?? 'password')));
        $secretValue = trim((string) ($config['secretValue'] ?? ''));
        $transport = strtolower(trim((string) ($config['transport'] ?? 'xmlrpc')));
        $model = trim((string) ($config['model'] ?? ''));

        if ($baseUrl === '' || $database === '' || $login === '' || $secretValue === '') {
            throw new RuntimeHttpException('ODOO_REQUIRED_FIELDS', 'Informe URL, banco, login e segredo do Odoo.', 422);
        }
        if (!preg_match('/^https?:\/\//i', $baseUrl)) {
            throw new RuntimeHttpException('ODOO_URL_INVALID', 'A URL do Odoo deve ser absoluta.', 422, [
                'baseUrl' => $baseUrl,
            ]);
        }
        if (!in_array($secretMode, ['password', 'api_key'], true)) {
            throw new RuntimeHttpException('ODOO_SECRET_MODE_INVALID', 'Tipo de segredo do Odoo invalido.', 422, [
                'secretMode' => $secretMode,
            ]);
        }
        if (!in_array($transport, ['xmlrpc', 'jsonrpc'], true)) {
            throw new RuntimeHttpException('ODOO_TRANSPORT_INVALID', 'Transporte Odoo invalido. Use xmlrpc ou jsonrpc.', 422, [
                'transport' => $transport,
            ]);
        }

        return [
            'baseUrl' => rtrim($baseUrl, '/'),
            'database' => $database,
            'login' => $login,
            'secretMode' => $secretMode,
            'secretValue' => $secretValue,
            'transport' => $transport,
            'model' => $model,
            'defaultContext' => $this->normalizeContext($config['defaultContext'] ?? []),
            'defaultDomain' => $this->normalizeDomain($config['defaultDomain'] ?? []),
            'defaultOrder' => trim((string) ($config['defaultOrder'] ?? '')),
            'defaultLimit' => max(1, min(500, (int) ($config['defaultLimit'] ?? 80))),
            'timeoutSeconds' => max(1, min(120, (int) ($config['timeoutSeconds'] ?? 20))),
        ];
    }

    private function jsonRpcCall(string $url, string $service, string $method, array $args, int $timeoutSeconds): mixed
    {
        $response = $this->requestJson($url, [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => $service,
                'method' => $method,
                'args' => $args,
            ],
            'id' => 'odoo-' . bin2hex(random_bytes(6)),
        ], $timeoutSeconds);

        if (isset($response['error'])) {
            $error = is_array($response['error']) ? $response['error'] : [];
            throw new RuntimeHttpException('ODOO_JSONRPC_ERROR', 'Odoo retornou erro na chamada JSON-RPC.', 422, [
                'error' => $error,
            ]);
        }
        if (!array_key_exists('result', $response)) {
            throw new RuntimeHttpException('ODOO_JSONRPC_INVALID', 'Resposta JSON-RPC do Odoo sem campo result.', 422);
        }

        return $response['result'];
    }

    private function xmlRpcCall(string $url, string $method, array $params, int $timeoutSeconds): mixed
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<methodCall><methodName>' . htmlspecialchars($method, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</methodName><params>'
            . implode('', array_map(fn ($param) => '<param>' . $this->xmlRpcEncodeValue($param) . '</param>', $params))
            . '</params></methodCall>';

        $raw = $this->requestRaw($url, [
            'Content-Type: text/xml',
            'Accept: text/xml',
        ], $xml, $timeoutSeconds);

        $document = @simplexml_load_string($raw);
        if (!$document instanceof \SimpleXMLElement) {
            throw new RuntimeHttpException('ODOO_XMLRPC_INVALID', 'Odoo retornou XML-RPC invalido.', 422, [
                'url' => $url,
            ]);
        }
        if (isset($document->fault)) {
            $fault = $this->xmlRpcDecodeValue($document->fault->value);
            throw new RuntimeHttpException('ODOO_XMLRPC_FAULT', 'Odoo retornou fault em XML-RPC.', 422, [
                'fault' => $fault,
            ]);
        }
        if (!isset($document->params->param->value)) {
            return null;
        }

        return $this->xmlRpcDecodeValue($document->params->param->value);
    }

    private function requestJson(string $url, array $payload, int $timeoutSeconds): array
    {
        $raw = $this->requestRaw($url, [
            'Content-Type: application/json',
            'Accept: application/json',
        ], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $timeoutSeconds);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeHttpException('ODOO_JSON_INVALID', 'Odoo retornou JSON invalido.', 422, [
                'url' => $url,
            ]);
        }

        return $decoded;
    }

    private function requestRaw(string $url, array $headers, ?string $body = null, int $timeoutSeconds = 20): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeHttpException('ODOO_REQUEST_INIT_FAILED', 'Nao foi possivel iniciar a conexao com o Odoo.', 422, [
                'url' => $url,
            ]);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => max(1, min(120, $timeoutSeconds)),
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body ?? '',
        ]);

        try {
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeHttpException('ODOO_REQUEST_FAILED', 'Falha ao chamar o Odoo.', 502, [
                    'url' => $url,
                    'curlError' => curl_error($ch),
                ]);
            }
            if ($status >= 400) {
                throw new RuntimeHttpException('ODOO_HTTP_ERROR', 'Odoo retornou erro HTTP.', 502, [
                    'url' => $url,
                    'status' => $status,
                    'body' => mb_substr((string) $raw, 0, 1000),
                ]);
            }

            return (string) $raw;
        } finally {
            curl_close($ch);
        }
    }

    private function xmlRpcEncodeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return '<value><boolean>' . ($value ? '1' : '0') . '</boolean></value>';
        }
        if (is_int($value)) {
            return '<value><int>' . $value . '</int></value>';
        }
        if (is_float($value)) {
            return '<value><double>' . str_replace(',', '.', (string) $value) . '</double></value>';
        }
        if (is_string($value)) {
            return '<value><string>' . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</string></value>';
        }
        if ($value === null) {
            return '<value><string></string></value>';
        }
        if (is_array($value)) {
            if ($this->isSequentialArray($value)) {
                return '<value><array><data>' . implode('', array_map(fn ($item) => $this->xmlRpcEncodeValue($item), $value)) . '</data></array></value>';
            }

            $members = [];
            foreach ($value as $key => $item) {
                $members[] = '<member><name>' . htmlspecialchars((string) $key, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</name>' . $this->xmlRpcEncodeValue($item) . '</member>';
            }

            return '<value><struct>' . implode('', $members) . '</struct></value>';
        }
        if ($value instanceof \stdClass) {
            return '<value><struct></struct></value>';
        }

        return '<value><string>' . htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</string></value>';
    }

    private function xmlRpcDecodeValue(\SimpleXMLElement $value): mixed
    {
        $children = $value->children();
        if (count($children) === 0) {
            return (string) $value;
        }

        $type = $children[0]->getName();
        $content = $children[0];

        return match ($type) {
            'boolean' => trim((string) $content) === '1',
            'int', 'i4' => (int) $content,
            'double' => (float) $content,
            'string', 'base64', 'dateTime.iso8601' => (string) $content,
            'array' => array_map(fn ($child) => $this->xmlRpcDecodeValue($child), iterator_to_array($content->data->value ?? [])),
            'struct' => $this->xmlRpcDecodeStruct($content),
            default => (string) $content,
        };
    }

    private function xmlRpcDecodeStruct(\SimpleXMLElement $struct): array
    {
        $result = [];
        foreach ($struct->member as $member) {
            $result[(string) $member->name] = $this->xmlRpcDecodeValue($member->value);
        }

        return $result;
    }

    private function isSequentialArray(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function normalizeContext(mixed $context): array
    {
        if ($context === null || $context === '') {
            return [];
        }
        if (!is_array($context)) {
            throw new RuntimeHttpException('ODOO_CONTEXT_INVALID', 'Contexto padrao do Odoo deve ser um objeto JSON.', 422);
        }

        return $context;
    }

    private function normalizeDomain(mixed $domain): array
    {
        if ($domain === null || $domain === '') {
            return [];
        }
        if (!is_array($domain)) {
            throw new RuntimeHttpException('ODOO_DOMAIN_INVALID', 'Dominio padrao do Odoo deve ser um array JSON.', 422);
        }

        return $domain;
    }

    private function normalizeFieldNames(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($field) => trim((string) $field), $fields), static fn ($field) => $field !== ''));
    }
}
