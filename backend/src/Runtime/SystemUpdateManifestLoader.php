<?php

namespace App\Runtime;

use Symfony\Component\HttpKernel\KernelInterface;

class SystemUpdateManifestLoader
{
    private const SIGNATURE_ALGORITHM = 'hmac-sha256';

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    public function load(?string $source = null): array
    {
        $resolvedSource = $this->resolveSource($source);
        $content = $this->readSource($resolvedSource);
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Manifesto de atualizacao invalido.');
        }

        $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
        $releases = $decoded['releases'] ?? $decoded;
        if (!is_array($releases)) {
            throw new \RuntimeException('Manifesto de atualizacao sem lista de releases.');
        }

        $signature = trim((string) ($decoded['signature'] ?? ''));
        $signatureCheck = $this->verifySignature($resolvedSource, $signature, $meta, $releases);

        return [
            'source' => $resolvedSource,
            'hash' => hash('sha256', $content),
            'meta' => $meta,
            'signature' => $signature !== '' ? $signature : null,
            'signatureStatus' => $signatureCheck['status'],
            'signatureMessage' => $signatureCheck['message'],
            'releases' => array_values(array_filter($releases, 'is_array')),
        ];
    }

    private function resolveSource(?string $source): string
    {
        $normalized = trim((string) $source);
        if ($normalized !== '') {
            return $normalized;
        }

        $configured = trim((string) ($_SERVER['APP_UPDATE_MANIFEST_URL'] ?? $_ENV['APP_UPDATE_MANIFEST_URL'] ?? getenv('APP_UPDATE_MANIFEST_URL') ?: ''));
        if ($configured !== '') {
            return $configured;
        }

        return dirname($this->kernel->getProjectDir()) . '/backend/config/system-updates/manifest.json';
    }

    private function readSource(string $source): string
    {
        if (preg_match('/^https?:\\/\\//i', $source) === 1) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 12,
                    'ignore_errors' => true,
                    'user_agent' => 'ConstrutorPG-SystemUpdater/1.0',
                ],
            ]);
            $content = @file_get_contents($source, false, $context);
            if ($content === false || trim($content) === '') {
                throw new \RuntimeException('Nao foi possivel carregar o manifesto remoto de atualizacao.');
            }

            return $content;
        }

        if (!is_file($source)) {
            throw new \RuntimeException('Manifesto de atualizacao nao encontrado: ' . $source);
        }

        $content = (string) file_get_contents($source);
        if (trim($content) === '') {
            throw new \RuntimeException('Manifesto de atualizacao vazio: ' . $source);
        }

        return $content;
    }

    private function verifySignature(string $source, string $signature, array $meta, array $releases): array
    {
        $isRemote = preg_match('/^https?:\\/\\//i', $source) === 1;
        $signingKey = trim((string) ($_SERVER['APP_UPDATE_MANIFEST_SIGNING_KEY'] ?? $_ENV['APP_UPDATE_MANIFEST_SIGNING_KEY'] ?? getenv('APP_UPDATE_MANIFEST_SIGNING_KEY') ?: ''));
        if ($signature === '') {
            if ($isRemote) {
                return [
                    'status' => 'missing',
                    'message' => 'Manifesto remoto sem assinatura.',
                ];
            }

            return [
                'status' => 'local-unsigned',
                'message' => 'Manifesto local sem assinatura obrigatoria.',
            ];
        }

        if ($signingKey === '') {
            return [
                'status' => 'key-missing',
                'message' => 'Chave de verificacao da assinatura nao configurada.',
            ];
        }

        $algorithm = strtolower(trim((string) ($meta['signatureAlgorithm'] ?? self::SIGNATURE_ALGORITHM)));
        if ($algorithm !== self::SIGNATURE_ALGORITHM) {
            return [
                'status' => 'algorithm-unsupported',
                'message' => 'Algoritmo de assinatura nao suportado.',
            ];
        }

        $canonicalPayload = json_encode([
            'meta' => $meta,
            'releases' => array_values(array_filter($releases, 'is_array')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', $canonicalPayload ?: '', $signingKey);
        if (!hash_equals($expected, $signature)) {
            return [
                'status' => 'invalid',
                'message' => 'Assinatura do manifesto invalida.',
            ];
        }

        return [
            'status' => 'verified',
            'message' => 'Assinatura do manifesto validada.',
        ];
    }
}
