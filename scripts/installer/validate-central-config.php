<?php

declare(strict_types=1);

$required = [
    'APP_INSTALLER_ACTIVATION_SIGNING_KEY',
    'APP_INSTALLATION_SESSION_SIGNING_KEY',
    'APP_INSTALLER_ARTIFACT_SIGNING_KEY',
    'APP_INSTALLER_ACTIVATION_FROM',
    'MAILER_DSN',
    'APP_INSTALLER_MANIFEST_URL',
];

$errors = [];
$warnings = [];

foreach ($required as $name) {
    $value = trim((string) getenv($name));
    if ($value === '') {
        $errors[] = "{$name} nao configurado.";
    }
}

$mailer = trim((string) getenv('MAILER_DSN'));
if ($mailer !== '' && $mailer === 'null://null') {
    $errors[] = 'MAILER_DSN esta em null://null; configure SMTP real antes de producao.';
}

$from = trim((string) getenv('APP_INSTALLER_ACTIVATION_FROM'));
if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'APP_INSTALLER_ACTIVATION_FROM nao parece ser e-mail valido.';
}

foreach (['APP_INSTALLER_MANIFEST_URL', 'APP_INSTALLER_DOCKER_COMPOSE_URL', 'APP_INSTALLER_PACKAGE_URL'] as $name) {
    $value = trim((string) getenv($name));
    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
        $errors[] = "{$name} nao e uma URL valida.";
    }
    if ($value !== '' && !str_starts_with(strtolower($value), 'https://')) {
        $warnings[] = "{$name} nao usa HTTPS.";
    }
}

foreach (['APP_INSTALLER_ACTIVATION_SIGNING_KEY', 'APP_INSTALLATION_SESSION_SIGNING_KEY', 'APP_INSTALLER_ARTIFACT_SIGNING_KEY'] as $name) {
    $value = trim((string) getenv($name));
    if ($value !== '' && strlen($value) < 32) {
        $errors[] = "{$name} deve ter pelo menos 32 caracteres.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Configuração central invalida:\n- " . implode("\n- ", $errors) . "\n");
    if ($warnings !== []) {
        fwrite(STDERR, "\nAvisos:\n- " . implode("\n- ", $warnings) . "\n");
    }
    exit(1);
}

echo "Configuracao central minima validada.\n";
if ($warnings !== []) {
    echo "Avisos:\n- " . implode("\n- ", $warnings) . "\n";
}
