<?php

declare(strict_types=1);

const ORCHESTRATOR_SIGNATURE_HEADER = 'HTTP_X_CONSTRUTOR_SIGNATURE';
const ORCHESTRATOR_EVENT_HEADER = 'HTTP_X_CONSTRUTOR_EVENT';
const ORCHESTRATOR_AUTH_HEADER = 'HTTP_AUTHORIZATION';

if (PHP_SAPI === 'cli') {
    orchestrator_cli($argv);
    return;
}

orchestrator_http();

function orchestrator_http(): void
{
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        echo json_encode(orchestrator_health(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['accepted' => false, 'message' => 'Metodo nao suportado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    try {
        $rawBody = (string) file_get_contents('php://input');
        orchestrator_validate_request($rawBody);
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        $result = orchestrator_execute($payload, false);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        http_response_code(500);
        echo json_encode([
            'accepted' => false,
            'message' => $error->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

function orchestrator_cli(array $argv): void
{
    $options = [
        'config' => null,
        'payloadFile' => null,
        'dryRun' => false,
        'health' => false,
    ];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--health') {
            $options['health'] = true;
            continue;
        }
        if ($argument === '--dry-run') {
            $options['dryRun'] = true;
            continue;
        }
        if (str_starts_with($argument, '--config=')) {
            $options['config'] = substr($argument, 9);
            continue;
        }
        if (str_starts_with($argument, '--payload-file=')) {
            $options['payloadFile'] = substr($argument, 15);
            continue;
        }
        fwrite(STDERR, "Parametro nao suportado: {$argument}\n");
        exit(1);
    }

    if ($options['health']) {
        fwrite(STDOUT, json_encode(orchestrator_health($options['config']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        return;
    }

    if (!$options['payloadFile']) {
        fwrite(STDERR, "Use --payload-file=<arquivo.json> ou --health.\n");
        exit(1);
    }

    $payloadPath = (string) $options['payloadFile'];
    if (!is_file($payloadPath)) {
        fwrite(STDERR, "Payload nao encontrado: {$payloadPath}\n");
        exit(1);
    }

    $payloadJson = (string) file_get_contents($payloadPath);
    $payloadJson = preg_replace('/^\xEF\xBB\xBF/', '', $payloadJson) ?? $payloadJson;
    $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
    $result = orchestrator_execute($payload, (bool) $options['dryRun'], $options['config']);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function orchestrator_health(?string $configPath = null): array
{
    $resolvedConfig = orchestrator_resolve_config_path($configPath);
    return [
        'service' => 'system-update-orchestrator',
        'configPath' => $resolvedConfig,
        'configExists' => is_file($resolvedConfig),
        'logDirectory' => orchestrator_log_directory(),
        'dockerComposeAvailable' => orchestrator_command_available('docker'),
        'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
    ];
}

function orchestrator_validate_request(string $rawBody): void
{
    $expectedToken = orchestrator_env('APP_UPDATE_ORCHESTRATOR_TOKEN');
    $authorization = trim((string) ($_SERVER[ORCHESTRATOR_AUTH_HEADER] ?? ''));
    if ($expectedToken !== '') {
        $expectedHeader = 'Bearer ' . $expectedToken;
        if (!hash_equals($expectedHeader, $authorization)) {
            throw new RuntimeException('Token do orquestrador invalido.');
        }
    }

    $signatureKey = orchestrator_env('APP_UPDATE_ORCHESTRATOR_SIGNING_KEY');
    if ($signatureKey !== '') {
        $expectedSignature = hash_hmac('sha256', $rawBody, $signatureKey);
        $providedSignature = trim((string) ($_SERVER[ORCHESTRATOR_SIGNATURE_HEADER] ?? ''));
        if ($providedSignature === '' || !hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('Assinatura do orquestrador invalida.');
        }
    }

    $event = trim((string) ($_SERVER[ORCHESTRATOR_EVENT_HEADER] ?? ''));
    if ($event !== '' && $event !== 'system.update.rollout') {
        throw new RuntimeException('Evento do orquestrador nao suportado.');
    }
}

function orchestrator_execute(array $payload, bool $dryRun = false, ?string $configPath = null): array
{
    $config = orchestrator_load_config($configPath);
    $targetSubscriber = is_array($payload['targetSubscriber'] ?? null) ? $payload['targetSubscriber'] : [];
    $subscriberCode = trim((string) ($targetSubscriber['code'] ?? ''));
    if ($subscriberCode === '') {
        throw new RuntimeException('Payload sem assinante alvo para rollout.');
    }

    $subscriberConfig = orchestrator_resolve_subscriber_config($config, $subscriberCode);
    $releaseVersion = trim((string) ($payload['releaseVersion'] ?? ''));
    $action = trim((string) ($payload['orchestratorAction'] ?? 'rolling-restart')) ?: 'rolling-restart';
    $commands = orchestrator_build_commands($subscriberConfig, $payload, $subscriberCode);
    $logFile = orchestrator_log_file($subscriberCode, $releaseVersion !== '' ? $releaseVersion : 'unknown');

    $result = [
        'accepted' => true,
        'subscriberCode' => $subscriberCode,
        'releaseVersion' => $releaseVersion,
        'action' => $action,
        'dryRun' => $dryRun,
        'executedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        'commands' => array_map(static fn (array $item): string => (string) ($item['display'] ?? $item['command'] ?? ''), $commands),
        'results' => [],
        'logFile' => $logFile,
    ];

    orchestrator_write_rollout_state($subscriberConfig, $payload, [
        'active' => true,
        'status' => 'running',
        'finishedAt' => null,
    ], $dryRun);

    foreach ($commands as $command) {
        $result['results'][] = $dryRun
            ? [
                'label' => $command['label'],
                'status' => 'skipped',
                'command' => $command['display'],
                'message' => 'Dry-run ativo.',
            ]
            : orchestrator_run_command($command, (int) ($subscriberConfig['timeoutSeconds'] ?? 900));
    }

    $result['status'] = orchestrator_has_failures($result['results']) ? 'failed' : 'succeeded';
    orchestrator_write_rollout_state($subscriberConfig, $payload, [
        'active' => false,
        'status' => $result['status'],
        'finishedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
    ], $dryRun);
    orchestrator_write_log($logFile, [
        'payload' => $payload,
        'result' => $result,
    ]);

    return $result;
}

function orchestrator_load_config(?string $configPath = null): array
{
    $resolved = orchestrator_resolve_config_path($configPath);
    if (!is_file($resolved)) {
        throw new RuntimeException('Arquivo de configuracao do orquestrador nao encontrado: ' . $resolved);
    }
    $decoded = json_decode((string) file_get_contents($resolved), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Configuracao do orquestrador invalida.');
    }
    return $decoded;
}

function orchestrator_resolve_config_path(?string $configPath = null): string
{
    $env = orchestrator_env('APP_UPDATE_ORCHESTRATOR_CONFIG');
    if (is_string($configPath) && trim($configPath) !== '') {
        return trim($configPath);
    }
    if ($env !== '') {
        return $env;
    }
    return __DIR__ . '/system-update-orchestrator.config.json';
}

function orchestrator_resolve_subscriber_config(array $config, string $subscriberCode): array
{
    $defaults = is_array($config['defaults'] ?? null) ? $config['defaults'] : [];
    $subscribers = is_array($config['subscribers'] ?? null) ? $config['subscribers'] : [];
    $subscriber = is_array($subscribers[$subscriberCode] ?? null) ? $subscribers[$subscriberCode] : [];
    if (!$subscriber) {
        throw new RuntimeException('Assinante sem configuracao no orquestrador: ' . $subscriberCode);
    }

    $resolved = array_replace_recursive($defaults, $subscriber);
    foreach (['composeFile', 'workdir', 'projectName'] as $required) {
        if (trim((string) ($resolved[$required] ?? '')) === '') {
            throw new RuntimeException('Configuracao do orquestrador sem ' . $required . ' para ' . $subscriberCode . '.');
        }
    }
    if (!is_array($resolved['services'] ?? null) || !$resolved['services']) {
        throw new RuntimeException('Configuracao do orquestrador sem services para ' . $subscriberCode . '.');
    }
    $resolved['timeoutSeconds'] = max(30, (int) ($resolved['timeoutSeconds'] ?? 900));

    return $resolved;
}

function orchestrator_build_commands(array $config, array $payload, string $subscriberCode): array
{
    $tokens = [
        '{{subscriberCode}}' => $subscriberCode,
        '{{releaseVersion}}' => trim((string) ($payload['releaseVersion'] ?? '')),
        '{{projectName}}' => trim((string) ($config['projectName'] ?? '')),
        '{{workdir}}' => trim((string) ($config['workdir'] ?? '')),
        '{{composeFile}}' => trim((string) ($config['composeFile'] ?? '')),
    ];

    $commands = [];
    if (($payload['requiresMaintenanceMode'] ?? false) === true && trim((string) ($config['maintenanceEnterCommand'] ?? '')) !== '') {
        $commands[] = orchestrator_make_command('maintenance-enter', orchestrator_apply_tokens((string) $config['maintenanceEnterCommand'], $tokens), $config);
    }
    if (($payload['requiresBackup'] ?? false) === true && trim((string) ($config['backupCommand'] ?? '')) !== '') {
        $commands[] = orchestrator_make_command('backup', orchestrator_apply_tokens((string) $config['backupCommand'], $tokens), $config);
    }
    foreach ((array) ($config['preCommands'] ?? []) as $preCommand) {
        if (trim((string) $preCommand) === '') {
            continue;
        }
        $commands[] = orchestrator_make_command('pre-command', orchestrator_apply_tokens((string) $preCommand, $tokens), $config);
    }

    $composeBinary = trim((string) ($config['composeBinary'] ?? 'docker compose')) ?: 'docker compose';
    $composeBase = $composeBinary . ' -f ' . escapeshellarg((string) $config['composeFile']) . ' -p ' . escapeshellarg((string) $config['projectName']);
    $services = implode(' ', array_map(static fn ($item): string => escapeshellarg((string) $item), (array) ($config['services'] ?? [])));

    if (($config['pullBeforeUp'] ?? true) === true) {
        $commands[] = orchestrator_make_command('docker-pull', $composeBase . ' pull ' . $services, $config);
    }
    $upCommand = $composeBase . ' up -d';
    if (($config['forceRecreate'] ?? true) === true) {
        $upCommand .= ' --force-recreate';
    }
    $upCommand .= ' ' . $services;
    $commands[] = orchestrator_make_command('docker-up', $upCommand, $config);

    foreach ((array) ($config['postCommands'] ?? []) as $postCommand) {
        if (trim((string) $postCommand) === '') {
            continue;
        }
        $commands[] = orchestrator_make_command('post-command', orchestrator_apply_tokens((string) $postCommand, $tokens), $config);
    }
    if (($payload['requiresMaintenanceMode'] ?? false) === true && trim((string) ($config['maintenanceExitCommand'] ?? '')) !== '') {
        $commands[] = orchestrator_make_command('maintenance-exit', orchestrator_apply_tokens((string) $config['maintenanceExitCommand'], $tokens), $config);
    }

    return $commands;
}

function orchestrator_make_command(string $label, string $command, array $config): array
{
    return [
        'label' => $label,
        'command' => $command,
        'display' => $command,
        'workdir' => (string) $config['workdir'],
    ];
}

function orchestrator_write_rollout_state(array $config, array $payload, array $state, bool $dryRun): void
{
    $file = trim((string) ($config['rolloutStateFile'] ?? ''));
    if ($file === '') {
        return;
    }

    $window = is_array($payload['rolloutWindow'] ?? null) ? $payload['rolloutWindow'] : [];
    $batch = is_array($payload['rolloutBatch'] ?? null) ? $payload['rolloutBatch'] : [];
    $targetSubscriber = is_array($payload['targetSubscriber'] ?? null) ? $payload['targetSubscriber'] : [];
    $content = [
        'active' => ($state['active'] ?? false) === true,
        'status' => trim((string) ($state['status'] ?? 'running')) ?: 'running',
        'releaseVersion' => trim((string) ($payload['releaseVersion'] ?? '')),
        'category' => trim((string) ($payload['category'] ?? '')),
        'severity' => trim((string) ($payload['severity'] ?? '')),
        'subscriberCode' => trim((string) ($targetSubscriber['code'] ?? '')),
        'batchCode' => trim((string) ($batch['code'] ?? '')),
        'accessMode' => trim((string) ($payload['entryAccessMode'] ?? 'warning')) ?: 'warning',
        'title' => 'Atualizacao SaaS em andamento',
        'message' => (($payload['entryAccessMode'] ?? 'warning') === 'blocked')
            ? 'O tenant esta temporariamente bloqueado enquanto o rollout SaaS termina.'
            : 'O rollout SaaS esta em andamento. Aguarde a validacao final.',
        'windowStartsAt' => trim((string) ($window['startAt'] ?? '')),
        'windowEndsAt' => trim((string) ($window['endAt'] ?? '')),
        'startedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        'finishedAt' => $state['finishedAt'] ?? null,
        'dryRun' => $dryRun,
    ];

    $directory = dirname($file);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Nao foi possivel criar o diretorio do estado de rollout SaaS.');
    }

    file_put_contents($file, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function orchestrator_run_command(array $command, int $timeoutSeconds): array
{
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open((string) $command['command'], $descriptor, $pipes, (string) $command['workdir']);
    if (!is_resource($process)) {
        throw new RuntimeException('Falha ao iniciar o comando do orquestrador: ' . (string) $command['label']);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $startedAt = time();
    do {
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        if ((time() - $startedAt) > $timeoutSeconds) {
            proc_terminate($process, 9);
            throw new RuntimeException('Timeout ao executar o comando do orquestrador: ' . (string) $command['label']);
        }
        usleep(200000);
    } while (true);

    $stdout .= stream_get_contents($pipes[1]) ?: '';
    $stderr .= stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'label' => (string) $command['label'],
        'status' => $exitCode === 0 ? 'ok' : 'failed',
        'command' => (string) $command['display'],
        'exitCode' => $exitCode,
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
    ];
}

function orchestrator_has_failures(array $results): bool
{
    foreach ($results as $result) {
        if (($result['status'] ?? '') === 'failed') {
            return true;
        }
    }
    return false;
}

function orchestrator_log_directory(): string
{
    return dirname(__DIR__, 1) . '/../var/orchestrator-update';
}

function orchestrator_log_file(string $subscriberCode, string $releaseVersion): string
{
    $directory = rtrim(orchestrator_log_directory(), '/\\') . '/' . date('Ymd');
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Nao foi possivel criar o diretorio de logs do orquestrador.');
    }
    $safeSubscriber = preg_replace('/[^a-z0-9._-]+/i', '-', $subscriberCode) ?: 'subscriber';
    $safeVersion = preg_replace('/[^a-z0-9._-]+/i', '-', $releaseVersion) ?: 'release';
    return $directory . '/' . date('His') . '-' . $safeSubscriber . '-' . $safeVersion . '.json';
}

function orchestrator_write_log(string $file, array $payload): void
{
    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function orchestrator_apply_tokens(string $value, array $tokens): string
{
    return strtr($value, $tokens);
}

function orchestrator_env(string $name): string
{
    return trim((string) ($_SERVER[$name] ?? $_ENV[$name] ?? getenv($name) ?: ''));
}

function orchestrator_command_available(string $command): bool
{
    $output = [];
    $status = 1;
    $redirect = DIRECTORY_SEPARATOR === '\\' ? ' 2>nul' : ' 2>/dev/null';
    @exec($command . ' --version' . $redirect, $output, $status);
    return $status === 0;
}
