<?php

namespace App\Provisioning;

use Symfony\Component\HttpKernel\KernelInterface;

class OnPremPackageBuilderService
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    public function build(array $context): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive nao esta disponivel para gerar o pacote on-premise.');
        }

        $projectRoot = dirname($this->kernel->getProjectDir());
        $varDir = $this->kernel->getProjectDir() . '/var/onprem-package';
        if (!is_dir($varDir) && !mkdir($varDir, 0777, true) && !is_dir($varDir)) {
            throw new \RuntimeException('Nao foi possivel preparar o diretorio temporario do pacote on-premise.');
        }

        $subscriberCode = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($context['subscriberCode'] ?? 'cliente')) ?: 'cliente';
        $fileName = 'construtor-pg-onprem-' . strtolower($subscriberCode) . '.zip';
        $targetPath = $varDir . '/' . $fileName;
        @unlink($targetPath);

        $zip = new \ZipArchive();
        if ($zip->open($targetPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Nao foi possivel abrir o arquivo ZIP do pacote on-premise.');
        }

        $rootFolder = 'construtor-pg-onprem';
        $this->addDirectory($zip, $projectRoot . '/assets', $rootFolder . '/assets');
        $this->addDirectory($zip, $projectRoot . '/backend', $rootFolder . '/backend', [
            '/var\//',
            '/vendor\/bin\/phpunit/',
        ]);
        $this->addDirectory($zip, $projectRoot . '/kendo', $rootFolder . '/kendo');
        $this->addDirectory($zip, $projectRoot . '/production', $rootFolder . '/production');
        $this->addDirectory($zip, $projectRoot . '/public', $rootFolder . '/public');
        $this->addDirectory($zip, $projectRoot . '/src', $rootFolder . '/src');
        $this->addDirectory($zip, $projectRoot . '/vendor/jquery', $rootFolder . '/vendor/jquery');
        $this->addFile($zip, $projectRoot . '/home.html', $rootFolder . '/home.html');
        $this->addFile($zip, $projectRoot . '/index.html', $rootFolder . '/index.html');
        $this->addFile($zip, $projectRoot . '/login.html', $rootFolder . '/login.html');
        $this->addFile($zip, $projectRoot . '/program-builder.html', $rootFolder . '/program-builder.html');
        $this->addFile($zip, $projectRoot . '/theme-builder.html', $rootFolder . '/theme-builder.html');
        $this->addFile($zip, $projectRoot . '/docs/provisionamento-saas-onprem.md', $rootFolder . '/docs/provisionamento-saas-onprem.md');
        $this->addFile($zip, $projectRoot . '/scripts/install-onprem.sh', $rootFolder . '/scripts/install-onprem.sh');
        $this->addFile($zip, $projectRoot . '/scripts/update-onprem.sh', $rootFolder . '/scripts/update-onprem.sh');

        $zip->addFromString($rootFolder . '/install.sh', $this->installEntrypoint());
        $zip->addFromString($rootFolder . '/update.sh', $this->updateEntrypoint());
        $zip->addFromString($rootFolder . '/.env.template', $this->buildEnvTemplate($context));
        $zip->addFromString($rootFolder . '/README-INSTALACAO.txt', $this->buildReadme($context));

        $zip->close();

        $sha256 = hash_file('sha256', $targetPath) ?: '';
        $signature = $this->signPackageChecksum($sha256);

        return [
            'path' => $targetPath,
            'fileName' => $fileName,
            'size' => filesize($targetPath) ?: 0,
            'sha256' => $sha256,
            'signature' => $signature,
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    private function installEntrypoint(): string
    {
        return "#!/usr/bin/env bash\nset -euo pipefail\ncd \"$(dirname \"$0\")\"\nchmod +x scripts/install-onprem.sh\nexec ./scripts/install-onprem.sh \"$@\"\n";
    }

    private function updateEntrypoint(): string
    {
        return "#!/usr/bin/env bash\nset -euo pipefail\ncd \"$(dirname \"$0\")\"\nchmod +x scripts/update-onprem.sh\nexec ./scripts/update-onprem.sh \"$@\"\n";
    }

    private function buildEnvTemplate(array $context): string
    {
        return implode("\n", [
            'APP_ENV="prod"',
            'APP_DATABASE_ENVIRONMENT="' . $this->escapeEnv((string) ($context['databaseEnvironment'] ?? 'prod')) . '"',
            'APP_DATABASE_IDENTITY="' . $this->escapeEnv((string) ($context['databaseIdentity'] ?? 'onprem:cliente')) . '"',
            'DATABASE_URL="postgresql://app:__DEFINA_SENHA_FORTE_DO_BANCO__@127.0.0.1:5432/' . $this->escapeEnv((string) ($context['databaseName'] ?? 'construtor_pg')) . '?serverVersion=16&charset=utf8"',
            '',
        ]) . "\n";
    }

    private function buildReadme(array $context): string
    {
        return implode("\n", [
            'Pacote on-premise gerado automaticamente.',
            '',
            'Assinante sugerido: ' . (string) ($context['subscriberCode'] ?? 'default'),
            'Nome sugerido: ' . (string) ($context['subscriberName'] ?? 'Principal'),
            'Banco sugerido: ' . (string) ($context['databaseName'] ?? 'construtor_pg'),
            'Admin sugerido: ' . (string) ($context['adminUsername'] ?? 'admin'),
            '',
            'Passos:',
            '1. extraia o zip em um diretorio de trabalho no Ubuntu 24.04;',
            '2. ajuste .env.template ou deixe o install.sh gerar backend/.env.local;',
            '3. exporte CONSTRUTOR_PG_DATABASE_PASSWORD e CONSTRUTOR_PG_ADMIN_PASSWORD com segredos fortes;',
            '4. rode ./install.sh;',
            '5. acompanhe a saida ate o bootstrap concluir.',
            '6. para atualizacoes futuras, use ./update.sh com o manifesto adequado.',
            '',
            'Referencia completa: docs/provisionamento-saas-onprem.md',
        ]) . "\n";
    }

    private function addDirectory(\ZipArchive $zip, string $source, string $target, array $excludePatterns = []): void
    {
        if (!is_dir($source)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $fullPath = $item->getPathname();
            $relative = str_replace('\\', '/', substr($fullPath, strlen($source) + 1));
            if ($relative === '') {
                continue;
            }
            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $relative) === 1) {
                    continue 2;
                }
            }

            $zipPath = $target . '/' . $relative;
            if ($item->isDir()) {
                $zip->addEmptyDir($zipPath);
                continue;
            }
            $zip->addFile($fullPath, $zipPath);
        }
    }

    private function addFile(\ZipArchive $zip, string $source, string $target): void
    {
        if (is_file($source)) {
            $zip->addFile($source, $target);
        }
    }

    private function escapeEnv(string $value): string
    {
        return str_replace('"', '\"', $value);
    }

    private function signPackageChecksum(string $sha256): ?string
    {
        $key = (string) (getenv('APP_ONPREM_PACKAGE_SIGNING_KEY') ?: getenv('APP_UPDATE_PACKAGE_SIGNING_KEY') ?: '');
        if ($key === '' || $sha256 === '') {
            return null;
        }

        return hash_hmac('sha256', $sha256, $key);
    }
}
