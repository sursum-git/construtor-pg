<?php

namespace App\Runtime;

class RuntimeExternalJsonClient
{
    public function request(string $url, string $method, array $headers, mixed $payload, int $timeoutSeconds, array $context = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeHttpException('ENTITY_API_REQUEST_INIT_FAILED', 'Nao foi possivel iniciar a chamada da API externa.', 500, $context + [
                'url' => $url,
            ]);
        }

        $httpHeaders = [];
        foreach ($headers as $key => $value) {
            $httpHeaders[] = (string) $key . ': ' . (string) $value;
        }

        $encodedPayload = null;
        if ($payload !== null) {
            $encodedPayload = is_scalar($payload)
                ? (string) $payload
                : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $httpHeaders[] = 'Content-Type: application/json';
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $httpHeaders,
            ]);
            if ($encodedPayload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload);
            }

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeHttpException('ENTITY_API_REQUEST_FAILED', 'Falha ao consultar a API externa.', 502, $context + [
                    'url' => $url,
                    'curlError' => curl_error($ch),
                ]);
            }

            $decoded = json_decode((string) $raw, true);
            if ((string) $raw === '' || $raw === null) {
                $decoded = [];
            }
            if (!is_array($decoded)) {
                throw new RuntimeHttpException('ENTITY_API_RESPONSE_INVALID', 'A API externa nao retornou um JSON/array valido.', 422, $context + [
                    'url' => $url,
                    'status' => $status,
                ]);
            }
            if ($status < 200 || $status >= 300) {
                throw new RuntimeHttpException('ENTITY_API_HTTP_ERROR', 'A API externa respondeu com erro.', 502, $context + [
                    'url' => $url,
                    'status' => $status,
                    'response' => $decoded,
                ]);
            }

            return [
                'status' => $status,
                'body' => $decoded,
            ];
        } finally {
            curl_close($ch);
        }
    }
}
