<?php

declare(strict_types=1);

$options = getopt('', [
    'dist-dir:',
    'public-base-url:',
    'output-dir:',
    'compose:',
    'package:',
    'version:',
]);

$distDir = rtrim((string) ($options['dist-dir'] ?? __DIR__ . '/../../installer/dist'), "/\\");
$outputDir = rtrim((string) ($options['output-dir'] ?? __DIR__ . '/../../outputs/installer-artifacts'), "/\\");
$publicBaseUrl = rtrim((string) ($options['public-base-url'] ?? ''), '/');
$compose = (string) ($options['compose'] ?? __DIR__ . '/../../compose.production.yaml');
$package = (string) ($options['package'] ?? '');
$version = (string) ($options['version'] ?? date('YmdHis'));
$signingKey = trim((string) getenv('APP_INSTALLER_ARTIFACT_SIGNING_KEY'));

if ($publicBaseUrl === '') {
    fail('Informe --public-base-url=https://...');
}
if (!is_dir($distDir)) {
    fail("Diretorio de binarios nao encontrado: {$distDir}");
}
if (!is_file($compose)) {
    fail("Compose nao encontrado: {$compose}");
}
if ($signingKey === '') {
    fail('Configure APP_INSTALLER_ARTIFACT_SIGNING_KEY antes de publicar.');
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fail("Nao foi possivel criar {$outputDir}");
}

$files = [];
foreach (glob($distDir . '/*') ?: [] as $file) {
    if (is_file($file)) {
        $files[] = copyArtifact($file, $outputDir);
    }
}
$files[] = copyArtifact($compose, $outputDir, 'compose.production.yaml');
if ($package !== '') {
    if (!is_file($package)) {
        fail("Pacote nao encontrado: {$package}");
    }
    $files[] = copyArtifact($package, $outputDir);
}

$manifest = [
    'version' => $version,
    'publishedAt' => gmdate(DATE_ATOM),
    'signatureAlgorithm' => 'hmac-sha256',
    'artifacts' => [],
];

foreach ($files as $file) {
    $name = basename($file);
    $url = $publicBaseUrl . '/' . rawurlencode($name);
    $manifest['artifacts'][] = [
        'name' => $name,
        'url' => $url,
        'sha256' => hash_file('sha256', $file),
        'bytes' => filesize($file),
        'signature' => hash_hmac('sha256', json_encode(['name' => $name, 'url' => $url, 'sha256' => hash_file('sha256', $file)], JSON_UNESCAPED_SLASHES), $signingKey),
    ];
}

$manifestPath = $outputDir . '/installer-manifest.json';
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
file_put_contents($manifestPath . '.sha256', hash_file('sha256', $manifestPath) . "  installer-manifest.json\n");

echo "Artefatos publicados em {$outputDir}\n";
echo "Configure APP_INSTALLER_MANIFEST_URL={$publicBaseUrl}/installer-manifest.json\n";
echo "Configure APP_INSTALLER_DOCKER_COMPOSE_URL={$publicBaseUrl}/compose.production.yaml\n";

function copyArtifact(string $source, string $outputDir, ?string $targetName = null): string
{
    $target = $outputDir . '/' . ($targetName ?: basename($source));
    if (!copy($source, $target)) {
        fail("Falha ao copiar {$source}");
    }
    file_put_contents($target . '.sha256', hash_file('sha256', $target) . '  ' . basename($target) . "\n");
    return $target;
}

function fail(string $message): never
{
    fwrite(STDERR, "ERRO: {$message}\n");
    exit(1);
}
