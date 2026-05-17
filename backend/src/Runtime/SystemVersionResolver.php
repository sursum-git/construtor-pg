<?php

namespace App\Runtime;

use Symfony\Component\HttpKernel\KernelInterface;

class SystemVersionResolver
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    public function resolve(): string
    {
        $explicit = trim((string) ($_SERVER['APP_SYSTEM_VERSION'] ?? $_ENV['APP_SYSTEM_VERSION'] ?? getenv('APP_SYSTEM_VERSION') ?: ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $projectRoot = dirname($this->kernel->getProjectDir());
        $packageFile = $projectRoot . '/package.json';
        if (is_file($packageFile)) {
            $payload = json_decode((string) file_get_contents($packageFile), true);
            if (is_array($payload) && trim((string) ($payload['version'] ?? '')) !== '') {
                return trim((string) $payload['version']);
            }
        }

        return '1.0.0';
    }
}
