<?php

namespace App\Runtime;

use App\Auth\AuthenticatedSessionResolver;

class RuntimeExternalJsonClient
{
    /** Only alive while a single explicitly read-only analytics batch is executing. */
    private ?array $readBatchResponses = null;

    public function withReadBatch(callable $work): mixed
    {
        if ($this->readBatchResponses !== null) {
            throw new RuntimeHttpException('ANALYTICS_BATCH_NESTED', 'Consulta agrupada aninhada indisponivel.', 422);
        }
        $this->readBatchResponses = [];
        try { return $work(); }
        finally { $this->readBatchResponses = null; }
    }

    public function __construct(
        private readonly ?AuthenticatedSessionResolver $authenticatedSessions = null,
        private readonly ?RuntimeTransactionService $transactions = null,
    ) {
    }

    public function request(string $url, string $method, array $headers, mixed $payload, int $timeoutSeconds, array $context = []): array
    {
        if (($context['responseContract'] ?? 'generic') === 'sursum-programs') {
            // Identity comes from the verified login, never from payload or configured headers.
            foreach (array_keys($headers) as $name) {
                if (in_array(strtolower((string) $name), ['x-sursum-user', 'x-session-id', 'x-sursum-auth-source'], true)) {
                    unset($headers[$name]);
                }
            }
            $session = $this->authenticatedSessions?->resolve();
            if ($session !== null && $session->getStatus() === 'active') {
                $headers['X-Sursum-User'] = $session->getUserId();
                $headers['X-Session-Id'] = $session->getSessionId();
                $authentication = $session->getSessionProperties()['authentication'] ?? null;
                $provider = is_array($authentication) ? ($authentication['provider'] ?? null) : null;
                if (is_string($provider) && preg_match('/\A[A-Za-z0-9_.:-]{1,80}\z/', $provider) === 1) {
                    $headers['X-Sursum-Auth-Source'] = $provider;
                }
            }
        }
        // Identity has already been rebuilt from the active session before matching.
        $batchKey = null;
        if ($this->readBatchResponses !== null) {
            $batchKey = hash('sha256', serialize([$url,strtoupper($method),$headers,$payload,$timeoutSeconds,$context['responseContract'] ?? 'generic']));
            if (isset($this->readBatchResponses[$batchKey])) { return $this->readBatchResponses[$batchKey]; }
            if (count($this->readBatchResponses) >= 10) {
                // Lookup enrichment may legitimately issue more requests; only bound retained responses.
                $batchKey = null;
            }
        }
        $response = $this->send($url, $method, $headers, $payload, $timeoutSeconds, $context);
        $status = $response['status'];
        // Collect before interpreting the business envelope: rollback messages remain visible.
        if (($context['responseContract'] ?? 'generic') === 'sursum-programs') {
            $this->collectProgressLogs($url, $headers, $response['headers'], $timeoutSeconds);
        }
        $decoded = json_decode($response['raw'], true);
        if ($response['raw'] === '') {
            $decoded = [];
        }
        if (!is_array($decoded)) {
            throw new RuntimeHttpException('ENTITY_API_RESPONSE_INVALID', 'A API externa nao retornou um JSON/array valido.', 422, $context + [
                'url' => $url,
                'status' => $status,
            ]);
        }
        if (($context['responseContract'] ?? 'generic') === 'sursum-programs' && array_key_exists('error', $decoded)) {
            $business = is_array($decoded['error']) ? $decoded['error'] : [];
            $code = is_string($business['code'] ?? null) && preg_match('/^[A-Z][A-Z0-9_]{0,119}$/', $business['code'])
                ? $business['code'] : 'ENTITY_API_BUSINESS_ERROR';
            $message = is_string($business['message'] ?? null) && trim($business['message']) !== ''
                ? mb_substr($business['message'], 0, 2000) : 'A API Progress recusou a operacao.';
            $businessStatus = is_int($business['status'] ?? null) ? $business['status'] : 502;
            if ($businessStatus < 400 || $businessStatus > 599) {
                $businessStatus = 502;
            }
            // Only this new storage-aware profile consumes an explicit Progress rollback.
            // Missing/ambiguous envelopes must remain uncertain; HTTP status is not proof.
            if (RuntimePurchaseAttachmentAudit::supports($context) && is_bool($decoded['committed']??null)) { $context['progressCommitted']=$decoded['committed']; }
            throw new RuntimeHttpException($code, $message, $businessStatus, $context);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeHttpException('ENTITY_API_HTTP_ERROR', 'A API externa respondeu com erro.', 502, $context + [
                'url' => $url,
                'status' => $status,
                'response' => $decoded,
            ]);
        }
        $result = ['status' => $status, 'body' => $decoded];
        if ($batchKey !== null) { $this->readBatchResponses[$batchKey] = $result; }
        return $result;
    }

    private function send(string $url, string $method, array $headers, mixed $payload, int $timeoutSeconds, array $context = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeHttpException('ENTITY_API_REQUEST_INIT_FAILED', 'Nao foi possivel iniciar a chamada da API externa.', 500, $context + ['url' => $url]);
        }
        $httpHeaders = [];
        foreach ($headers as $key => $value) {
            $httpHeaders[] = (string) $key . ': ' . (string) $value;
        }
        $encodedPayload = null;
        if ($payload !== null) {
            $encodedPayload = is_scalar($payload) ? (string) $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $httpHeaders[] = 'Content-Type: application/json';
        }
        $responseHeaders = [];
        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $httpHeaders,
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                    if (str_starts_with($line, 'HTTP/')) {
                        $responseHeaders = [];
                    } elseif (str_contains($line, ':')) {
                        [$name, $value] = explode(':', $line, 2);
                        $name = strtolower(trim($name));
                        // Duplicate correlation headers are ambiguous and must not be trusted.
                        $responseHeaders[$name] = array_key_exists($name, $responseHeaders) ? null : trim($value);
                    }
                    return strlen($line);
                },
            ]);
            if ($encodedPayload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload);
            }
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeHttpException('ENTITY_API_REQUEST_FAILED', 'Falha ao consultar a API externa.', 502, $context + [
                    'url' => $url, 'curlError' => curl_error($ch), 'curlCode' => curl_errno($ch),
                ]);
            }
            return ['status' => $status, 'raw' => (string) $raw, 'headers' => $responseHeaders];
        } finally {
            curl_close($ch);
        }
    }

    private function collectProgressLogs(string $url, array $headers, array $responseHeaders, int $timeoutSeconds): void
    {
        if (!$this->transactions?->getCurrent()) {
            return;
        }
        $executionId = $responseHeaders['x-sursum-execution-id'] ?? null;
        if (!is_string($executionId) || !preg_match('/\AEXE-[0-9]{8}-[0-9]{6}-[0-9]{6}\z/', $executionId)) {
            return;
        }
        $countHeader = $responseHeaders['x-sursum-log-count'] ?? null;
        $logCount = is_string($countHeader) && preg_match('/\A[0-9]{1,9}\z/', $countHeader) ? (int) $countHeader : null;
        $this->transactions->log('api.progress.execution', 'Execucao Progress correlacionada.', metadata: [
            'executionId' => $executionId, 'logCount' => $logCount,
        ]);
        if ($logCount === null || $logCount === 0) {
            return;
        }
        $failure = ['executionId' => $executionId, 'reason' => 'endpoint_invalid'];
        try {
            $eventsUrl = $this->progressEventsUrl($url, $executionId);
            // Transport only: fetching audit events never re-enters the request logger.
            $failure['reason'] = 'request_failed';
            $response = $this->send($eventsUrl, 'GET', $headers, null, min(5, max(1, $timeoutSeconds)));
            $failure['httpStatus'] = $response['status'];
            $failure['reason'] = 'http_error';
            if ($response['status'] !== 200) {
                throw new \UnexpectedValueException('Invalid progress events status.');
            }
            $body = json_decode($response['raw'], true);
            $failure['reason'] = 'response_invalid';
            if (!is_array($body) || ($body['success'] ?? false) !== true) {
                $failure['jsonError'] = json_last_error();
                throw new \UnexpectedValueException('Invalid progress events response.');
            }
            $failure['reason'] = 'execution_mismatch';
            if (($body['executionId'] ?? null) !== $executionId) {
                throw new \UnexpectedValueException('Unexpected progress execution.');
            }
            $failure['reason'] = 'events_invalid';
            if (!is_array($body['data'] ?? null)) {
                throw new \UnexpectedValueException('Invalid progress event list.');
            }
            $failure['reason'] = 'import_failed';
            $failure['receivedEvents'] = count($body['data']);
            $seen = [];
            foreach (array_slice($body['data'], 0, 500) as $event) {
                if (!is_array($event) || ($event['executionId'] ?? null) !== $executionId
                    || !in_array($event['eventStatus'] ?? null, ['log.debug', 'log.info', 'log.warning', 'log.error'], true)
                    || !is_string($event['eventMessage'] ?? null) || !is_string($event['eventId'] ?? null)
                    || !preg_match('/\A' . preg_quote($executionId, '/') . '-[0-9]{6}-[0-9]{6}\z/', $event['eventId'])
                    || isset($seen[$event['eventId']])) {
                    continue;
                }
                $seen[$event['eventId']] = true;
                $metadata = ['executionId' => $executionId, 'eventId' => $event['eventId']];
                if (is_string($event['eventTime'] ?? null) && preg_match('/\A[0-9TtZz:,.+\/ -]{1,64}\z/', $event['eventTime'])) {
                    $metadata['occurredAt'] = $event['eventTime'];
                }
                $eventContext = is_string($event['eventContextJson'] ?? null)
                    ? json_decode($event['eventContextJson'], true) : [];
                foreach (['currentUser', 'pasoeAgent', 'pasoeInstance', 'pasoePid'] as $field) {
                    if (is_array($eventContext) && is_string($eventContext[$field] ?? null)) {
                        $metadata[$field] = mb_substr($this->redactProgressMessage($eventContext[$field], $headers), 0, 160);
                    }
                }
                $this->transactions->log('progress.' . $event['eventStatus'],
                    mb_substr($this->redactProgressMessage($event['eventMessage'], $headers), 0, 4000), metadata: $metadata);
            }
            if (count($seen) < min($logCount, 500)) {
                $failure['reason'] = 'incomplete';
                $failure['importedLogs'] = count($seen);
                throw new \UnexpectedValueException('Incomplete progress log collection.');
            }
        } catch (\Throwable $error) {
            if ($error instanceof RuntimeHttpException && is_int($error->getDetails()['curlCode'] ?? null)) {
                $failure['curlCode'] = $error->getDetails()['curlCode'];
                $failure['reason'] = $failure['curlCode'] === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'request_failed';
            }
            // No upstream body, URL, headers or exception text can leak into this warning.
            $this->transactions->log('api.progress.logs.unavailable', 'Nao foi possivel coletar os logs da execucao Progress.',
                metadata: $failure);
        }
    }

    private function progressEventsUrl(string $url, string $executionId): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || !preg_match('~\A(/[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*)?/SursumPrograms/[A-Za-z0-9_-]+\z~', $parts['path'] ?? '', $matches)) {
            throw new \UnexpectedValueException('Invalid registered Progress endpoint.');
        }
        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($matches[1] ?? '') . '/SursumDynamicQuery/executions/' . $executionId . '/events?limit=500';
    }

    private function redactProgressMessage(string $message, array $headers): string
    {
        foreach ($headers as $name => $value) {
            if (!preg_match('/token|authorization|password|senha|secret|api[-_]?key|cookie/i', (string) $name) || (string) $value === '') {
                continue;
            }
            $secrets = [(string) $value];
            if (str_contains(strtolower((string) $name), 'authorization')
                && preg_match('/\A(Bearer|Basic)\s+(\S+)\s*\z/i', trim((string) $value), $credentials)) {
                $secrets[] = $credentials[2];
                if (strcasecmp($credentials[1], 'Basic') === 0) {
                    $decoded = base64_decode($credentials[2], true);
                    if (is_string($decoded) && str_contains($decoded, ':')) {
                        $secrets[] = $decoded;
                        [, $password] = explode(':', $decoded, 2);
                        if ($password !== '') {
                            $secrets[] = $password;
                        }
                    }
                }
            }
            $message = str_replace($secrets, '[redigido]', $message);
        }
        return $message;
    }
}
