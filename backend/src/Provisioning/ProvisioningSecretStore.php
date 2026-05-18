<?php

namespace App\Provisioning;

use Symfony\Component\HttpKernel\KernelInterface;

class ProvisioningSecretStore
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    public function store(array $payload): string
    {
        $token = bin2hex(random_bytes(16));
        $path = $this->resolvePath($token);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nao foi possivel preparar o armazenamento temporario de credenciais de provisionamento.');
        }

        $data = [
            'storedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'payload' => $payload,
        ];

        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $token;
    }

    public function load(string $token): array
    {
        $path = $this->resolvePath($token);
        if (!is_file($path)) {
            throw new \RuntimeException('Credencial temporaria do provisionamento nao encontrada.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || !is_array($decoded['payload'] ?? null)) {
            throw new \RuntimeException('Credencial temporaria do provisionamento esta invalida.');
        }

        return $decoded['payload'];
    }

    private function resolvePath(string $token): string
    {
        return $this->kernel->getProjectDir() . '/var/provisioning-secrets/' . preg_replace('/[^a-z0-9]/i', '', $token) . '.json';
    }
}
