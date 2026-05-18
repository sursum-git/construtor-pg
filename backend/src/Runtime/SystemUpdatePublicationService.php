<?php

namespace App\Runtime;

use Symfony\Component\HttpKernel\KernelInterface;

class SystemUpdatePublicationService
{
    private const SIGNATURE_ALGORITHM = 'hmac-sha256';
    private const DEFAULT_DISTRIBUTION_TIMEOUT = 30;

    public function __construct(
        private readonly SystemUpdateManifestLoader $manifestLoader,
        private readonly SystemUpdatePackageDownloader $packages,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function publish(?string $version = null, ?string $source = null, ?string $outputDirectory = null, ?string $baseUrl = null, ?string $channel = null): array
    {
        $manifest = $this->manifestLoader->load($source);
        $manifestSigningKey = $this->resolveConfig('APP_UPDATE_MANIFEST_SIGNING_KEY');
        $packageSigningKey = $this->resolveConfig('APP_UPDATE_PACKAGE_SIGNING_KEY');
        if ($manifestSigningKey === '' || $packageSigningKey === '') {
            throw new \RuntimeException('Configure APP_UPDATE_MANIFEST_SIGNING_KEY e APP_UPDATE_PACKAGE_SIGNING_KEY para publicar artefatos oficiais.');
        }

        $selectedVersion = trim((string) $version);
        $releases = array_values(array_filter($manifest['releases'], function ($release) use ($selectedVersion): bool {
            if (!is_array($release)) {
                return false;
            }
            if ($selectedVersion === '') {
                return true;
            }

            return trim((string) ($release['version'] ?? '')) === $selectedVersion;
        }));
        if ($selectedVersion !== '' && count($releases) === 0) {
            throw new \RuntimeException('Release nao encontrada no manifesto para publicacao: ' . $selectedVersion);
        }

        $distributionDirectory = $this->resolveOutputDirectory($outputDirectory, $selectedVersion);
        $packagesDirectory = $distributionDirectory . '/packages';
        if (!is_dir($packagesDirectory) && !@mkdir($packagesDirectory, 0775, true) && !is_dir($packagesDirectory)) {
            throw new \RuntimeException('Nao foi possivel criar o diretorio de distribuicao das releases.');
        }

        $configuredBaseUrl = trim((string) ($baseUrl ?: $this->resolveConfig('APP_UPDATE_PUBLIC_BASE_URL')));
        $publishedReleases = [];
        $publishedPackages = [];
        foreach ($releases as $release) {
            $published = $this->publishReleasePackage($release, $packagesDirectory, $packageSigningKey, $configuredBaseUrl);
            $publishedReleases[] = $published['release'];
            $publishedPackages[] = $published['package'];
        }

        $meta = is_array($manifest['meta'] ?? null) ? $manifest['meta'] : [];
        $meta['channel'] = trim((string) ($channel ?: ($meta['channel'] ?? 'stable'))) ?: 'stable';
        $meta['generatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $meta['signatureAlgorithm'] = self::SIGNATURE_ALGORITHM;
        $meta['publishedBy'] = 'admin.atualizacoes';
        $meta['distributionMode'] = 'official';
        $meta['releaseCount'] = count($publishedReleases);

        $canonicalManifest = [
            'meta' => $meta,
            'releases' => $publishedReleases,
        ];
        $signature = hash_hmac('sha256', json_encode($canonicalManifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', $manifestSigningKey);
        $manifestPayload = $canonicalManifest;
        $manifestPayload['signature'] = $signature;

        $manifestPath = $distributionDirectory . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $checksums = [];
        foreach ($publishedPackages as $package) {
            $checksums[] = $package['hash'] . '  packages/' . $package['fileName'];
        }
        $checksums[] = hash('sha256', (string) file_get_contents($manifestPath)) . '  manifest.json';
        file_put_contents($distributionDirectory . '/SHA256SUMS', implode(PHP_EOL, $checksums) . PHP_EOL);

        $publicationSummary = [
            'generatedAt' => $meta['generatedAt'],
            'channel' => $meta['channel'],
            'manifestPath' => $manifestPath,
            'distributionDirectory' => $distributionDirectory,
            'baseUrl' => $configuredBaseUrl !== '' ? $configuredBaseUrl : null,
            'versions' => array_values(array_map(static fn (array $release): string => (string) ($release['version'] ?? ''), $publishedReleases)),
            'packages' => $publishedPackages,
        ];
        file_put_contents(
            $distributionDirectory . '/publication.json',
            json_encode($publicationSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $externalPublication = $this->publishToExternalDestination(
            $distributionDirectory,
            $manifestPayload,
            $publishedPackages,
            $publicationSummary
        );

        return [
            'status' => 'published',
            'distributionDirectory' => $distributionDirectory,
            'manifestPath' => $manifestPath,
            'manifestSignature' => $signature,
            'channel' => $meta['channel'],
            'releaseCount' => count($publishedReleases),
            'versions' => $publicationSummary['versions'],
            'packages' => $publishedPackages,
            'baseUrl' => $publicationSummary['baseUrl'],
            'externalPublication' => $externalPublication,
        ];
    }

    private function publishReleasePackage(array $release, string $packagesDirectory, string $packageSigningKey, string $baseUrl): array
    {
        $loaded = $this->packages->loadRawPackage($release);
        $content = $loaded['content'];
        $hash = hash('sha256', $content);
        $signature = hash_hmac('sha256', $content, $packageSigningKey);
        $fileName = preg_replace('/[^a-z0-9._-]+/i', '-', (string) $loaded['fileName']) ?: 'system-update.pkg';
        $packagePath = $packagesDirectory . '/' . $fileName;
        file_put_contents($packagePath, $content);

        $releaseMetadata = is_array($release['metadata'] ?? null) ? $release['metadata'] : [];
        $releaseMetadata['packageHash'] = $hash;
        $releaseMetadata['packageSignature'] = $signature;
        $releaseMetadata['packageSignatureAlgorithm'] = self::SIGNATURE_ALGORITHM;
        $releaseMetadata['packagePublishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $releaseMetadata['packageFileName'] = $fileName;
        $releaseMetadata['packageUrl'] = $baseUrl !== ''
            ? rtrim($baseUrl, '/') . '/packages/' . rawurlencode($fileName)
            : 'packages/' . $fileName;

        $publishedRelease = $release;
        $publishedRelease['metadata'] = $releaseMetadata;

        return [
            'release' => $publishedRelease,
            'package' => [
                'version' => (string) ($release['version'] ?? ''),
                'fileName' => $fileName,
                'path' => $packagePath,
                'hash' => $hash,
                'signature' => $signature,
                'url' => $releaseMetadata['packageUrl'],
            ],
        ];
    }

    private function resolveOutputDirectory(?string $outputDirectory, string $version): string
    {
        $normalized = trim((string) ($outputDirectory ?: $this->resolveConfig('APP_UPDATE_DISTRIBUTION_DIR')));
        if ($normalized !== '') {
            return rtrim($normalized, '/\\');
        }

        $projectRoot = dirname($this->kernel->getProjectDir());
        $suffix = $version !== '' ? preg_replace('/[^a-z0-9._-]+/i', '-', $version) : 'catalog';

        return $projectRoot . '/var/system-updates/distribution/' . $suffix;
    }

    private function resolveConfig(string $name): string
    {
        return trim((string) ($_SERVER[$name] ?? $_ENV[$name] ?? getenv($name) ?: ''));
    }

    private function publishToExternalDestination(string $distributionDirectory, array $manifestPayload, array $publishedPackages, array $publicationSummary): ?array
    {
        $pushUrl = $this->resolveConfig('APP_UPDATE_DISTRIBUTION_PUSH_URL');
        if ($pushUrl === '') {
            return null;
        }

        $token = $this->resolveConfig('APP_UPDATE_DISTRIBUTION_PUSH_TOKEN');
        $signingKey = $this->resolveConfig('APP_UPDATE_DISTRIBUTION_PUSH_SIGNING_KEY');
        if ($token === '' || $signingKey === '') {
            throw new \RuntimeException('Configure APP_UPDATE_DISTRIBUTION_PUSH_TOKEN e APP_UPDATE_DISTRIBUTION_PUSH_SIGNING_KEY para publicar artefatos em destino externo.');
        }

        $files = [
            [
                'path' => 'manifest.json',
                'mimeType' => 'application/json',
                'contentBase64' => base64_encode((string) file_get_contents($distributionDirectory . '/manifest.json')),
            ],
            [
                'path' => 'SHA256SUMS',
                'mimeType' => 'text/plain',
                'contentBase64' => base64_encode((string) file_get_contents($distributionDirectory . '/SHA256SUMS')),
            ],
            [
                'path' => 'publication.json',
                'mimeType' => 'application/json',
                'contentBase64' => base64_encode((string) file_get_contents($distributionDirectory . '/publication.json')),
            ],
        ];
        foreach ($publishedPackages as $package) {
            $files[] = [
                'path' => 'packages/' . (string) ($package['fileName'] ?? ''),
                'mimeType' => 'application/octet-stream',
                'contentBase64' => base64_encode((string) file_get_contents((string) ($package['path'] ?? ''))),
                'hash' => (string) ($package['hash'] ?? ''),
            ];
        }

        $payload = [
            'event' => 'system.update.distribution.publish',
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'distributionDirectory' => $distributionDirectory,
            'manifest' => [
                'versions' => $publicationSummary['versions'] ?? [],
                'channel' => $publicationSummary['channel'] ?? 'stable',
                'signature' => $manifestPayload['signature'] ?? '',
            ],
            'publicationSummary' => $publicationSummary,
            'files' => $files,
        ];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new \RuntimeException('Falha ao serializar o payload de distribuicao externa.');
        }

        $signature = hash_hmac('sha256', $payloadJson, $signingKey);
        $timeout = max(5, (int) ($this->resolveConfig('APP_UPDATE_DISTRIBUTION_PUSH_TIMEOUT') ?: self::DEFAULT_DISTRIBUTION_TIMEOUT));

        $ch = curl_init($pushUrl);
        if ($ch === false) {
            throw new \RuntimeException('Nao foi possivel inicializar a chamada do destino externo de distribuicao.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-Update-Distribution-Signature: ' . $signature,
                'X-Update-Distribution-Algorithm: ' . self::SIGNATURE_ALGORITHM,
            ],
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 10),
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Falha ao publicar artefatos no destino externo: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Destino externo rejeitou a publicacao dos artefatos. HTTP ' . $status . '.');
        }

        $decoded = json_decode((string) $body, true);

        return [
            'status' => 'dispatched',
            'url' => $pushUrl,
            'httpStatus' => $status,
            'signatureAlgorithm' => self::SIGNATURE_ALGORITHM,
            'response' => is_array($decoded) ? $decoded : ['body' => (string) $body],
        ];
    }
}
