<?php

namespace App\Runtime;

class SystemUpdateOrchestratorClient
{
    public function isEnabled(): bool
    {
        return trim((string) ($_SERVER['APP_UPDATE_ORCHESTRATOR_URL'] ?? $_ENV['APP_UPDATE_ORCHESTRATOR_URL'] ?? getenv('APP_UPDATE_ORCHESTRATOR_URL') ?: '')) !== '';
    }

    public function getEndpoint(): ?string
    {
        $url = trim((string) ($_SERVER['APP_UPDATE_ORCHESTRATOR_URL'] ?? $_ENV['APP_UPDATE_ORCHESTRATOR_URL'] ?? getenv('APP_UPDATE_ORCHESTRATOR_URL') ?: ''));
        return $url !== '' ? $url : null;
    }

    public function dispatch(array $payload): array
    {
        $endpoint = $this->getEndpoint();
        if ($endpoint === null) {
            return [
                'status' => 'disabled',
                'message' => 'Orquestrador SaaS nao configurado.',
                'endpoint' => null,
            ];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \RuntimeException('Falha ao serializar o payload do orquestrador.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Construtor-Event: system.update.rollout',
        ];

        $bearerToken = trim((string) ($_SERVER['APP_UPDATE_ORCHESTRATOR_TOKEN'] ?? $_ENV['APP_UPDATE_ORCHESTRATOR_TOKEN'] ?? getenv('APP_UPDATE_ORCHESTRATOR_TOKEN') ?: ''));
        if ($bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        $signingKey = trim((string) ($_SERVER['APP_UPDATE_ORCHESTRATOR_SIGNING_KEY'] ?? $_ENV['APP_UPDATE_ORCHESTRATOR_SIGNING_KEY'] ?? getenv('APP_UPDATE_ORCHESTRATOR_SIGNING_KEY') ?: ''));
        if ($signingKey !== '') {
            $headers[] = 'X-Construtor-Signature: ' . hash_hmac('sha256', $body, $signingKey);
        }

        $timeout = (int) ($_SERVER['APP_UPDATE_ORCHESTRATOR_TIMEOUT'] ?? $_ENV['APP_UPDATE_ORCHESTRATOR_TIMEOUT'] ?? getenv('APP_UPDATE_ORCHESTRATOR_TIMEOUT') ?: 20);
        if ($timeout < 3) {
            $timeout = 20;
        }

        $responseHeaders = [];
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $content = @file_get_contents($endpoint, false, $context);
        $rawHeaders = $http_response_header ?? [];
        foreach ($rawHeaders as $line) {
            $pos = strpos((string) $line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr((string) $line, 0, $pos)));
            $value = trim(substr((string) $line, $pos + 1));
            if ($name !== '') {
                $responseHeaders[$name] = $value;
            }
        }

        $statusCode = 0;
        if (!empty($rawHeaders) && preg_match('/\\s(\\d{3})\\s/', (string) $rawHeaders[0], $matches) === 1) {
            $statusCode = (int) $matches[1];
        }

        $decoded = null;
        if (is_string($content) && trim($content) !== '') {
            try {
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $decoded = null;
            }
        }

        if ($content === false || $statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException('Falha ao despachar rollout para o orquestrador externo.');
        }

        return [
            'status' => 'dispatched',
            'message' => 'Rollout despachado para o orquestrador externo.',
            'endpoint' => $endpoint,
            'httpStatus' => $statusCode,
            'responseHeaders' => $responseHeaders,
            'responseBody' => $decoded !== null ? $decoded : (is_string($content) ? trim($content) : null),
        ];
    }
}
