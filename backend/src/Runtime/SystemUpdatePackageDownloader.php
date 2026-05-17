<?php

namespace App\Runtime;

use Symfony\Component\HttpKernel\KernelInterface;

class SystemUpdatePackageDownloader
{
    private const SIGNATURE_ALGORITHM = 'hmac-sha256';

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    public function download(array $release, bool $persist = true): array
    {
        $metadata = is_array($release['metadata'] ?? null) ? $release['metadata'] : [];
        $packageUrl = trim((string) ($metadata['packageUrl'] ?? ''));
        if ($packageUrl === '') {
            throw new \RuntimeException('Release sem pacote configurado para download.');
        }

        $content = $this->readSource($packageUrl);
        $hash = hash('sha256', $content);
        $expectedHash = strtolower(trim((string) ($metadata['packageHash'] ?? '')));
        if ($expectedHash !== '' && !hash_equals($expectedHash, $hash)) {
            throw new \RuntimeException('Hash do pacote de atualizacao invalido.');
        }

        $signatureStatus = $this->verifySignature($content, $metadata);
        $filename = trim((string) ($metadata['packageFileName'] ?? ''));
        if ($filename === '') {
            $filename = 'system-update-' . preg_replace('/[^a-z0-9._-]+/i', '-', (string) ($release['version'] ?? 'unknown')) . '.pkg';
        }

        $savedPath = null;
        if ($persist) {
            $savedPath = $this->persistPackage((string) ($release['version'] ?? 'unknown'), $filename, $content);
        }

        return [
            'url' => $packageUrl,
            'hash' => $hash,
            'expectedHash' => $expectedHash !== '' ? $expectedHash : null,
            'sizeBytes' => strlen($content),
            'fileName' => $filename,
            'savedPath' => $savedPath,
            'signatureStatus' => $signatureStatus['status'],
            'signatureMessage' => $signatureStatus['message'],
        ];
    }

    private function readSource(string $source): string
    {
        if (preg_match('/^https?:\\/\\//i', $source) === 1) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'ignore_errors' => true,
                    'user_agent' => 'ConstrutorPG-SystemUpdater/1.0',
                ],
            ]);
            $content = @file_get_contents($source, false, $context);
            if ($content === false || $content === '') {
                throw new \RuntimeException('Nao foi possivel baixar o pacote remoto de atualizacao.');
            }

            return $content;
        }

        if (!is_file($source)) {
            throw new \RuntimeException('Pacote de atualizacao nao encontrado: ' . $source);
        }

        $content = (string) file_get_contents($source);
        if ($content === '') {
            throw new \RuntimeException('Pacote de atualizacao vazio: ' . $source);
        }

        return $content;
    }

    private function verifySignature(string $content, array $metadata): array
    {
        $signature = trim((string) ($metadata['packageSignature'] ?? ''));
        if ($signature === '') {
            return [
                'status' => 'missing',
                'message' => 'Pacote sem assinatura declarada.',
            ];
        }

        $signingKey = trim((string) ($_SERVER['APP_UPDATE_PACKAGE_SIGNING_KEY'] ?? $_ENV['APP_UPDATE_PACKAGE_SIGNING_KEY'] ?? getenv('APP_UPDATE_PACKAGE_SIGNING_KEY') ?: ''));
        if ($signingKey === '') {
            return [
                'status' => 'key-missing',
                'message' => 'Chave de verificacao do pacote nao configurada.',
            ];
        }

        $algorithm = strtolower(trim((string) ($metadata['packageSignatureAlgorithm'] ?? self::SIGNATURE_ALGORITHM)));
        if ($algorithm !== self::SIGNATURE_ALGORITHM) {
            return [
                'status' => 'algorithm-unsupported',
                'message' => 'Algoritmo de assinatura do pacote nao suportado.',
            ];
        }

        $expected = hash_hmac('sha256', $content, $signingKey);
        if (!hash_equals($expected, $signature)) {
            return [
                'status' => 'invalid',
                'message' => 'Assinatura do pacote invalida.',
            ];
        }

        return [
            'status' => 'verified',
            'message' => 'Assinatura do pacote validada.',
        ];
    }

    private function persistPackage(string $version, string $filename, string $content): string
    {
        $projectRoot = dirname($this->kernel->getProjectDir());
        $directory = $projectRoot . '/var/system-updates/' . preg_replace('/[^a-z0-9._-]+/i', '-', $version);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nao foi possivel criar o diretorio local do pacote de atualizacao.');
        }

        $path = $directory . '/' . preg_replace('/[^a-z0-9._-]+/i', '-', $filename);
        file_put_contents($path, $content);

        return $path;
    }
}
