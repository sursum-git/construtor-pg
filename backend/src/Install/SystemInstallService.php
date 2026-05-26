<?php

namespace App\Install;

use App\Runtime\RuntimeHttpException;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

class SystemInstallService
{
    private const STEP_BOOTSTRAP = 'bootstrap';
    private const STEP_SUBSCRIBER = 'subscriber';
    private const STEP_PUBLISH_DEFAULTS = 'publish_defaults';
    private const STEP_INTEGRITY = 'integrity';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Connection $connection,
        private readonly InstallationActivationService $activation,
    ) {
    }

    public function status(): array
    {
        $state = $this->resolveInstallState('');
        $activation = $this->activation->status();

        return [
            'activation' => $activation,
            'systemInstalled' => $state['systemInstalled'],
            'requiresInstallerPassword' => $state['requiresInstallerPassword'],
            'installerPasswordConfigured' => $state['installerPasswordConfigured'],
            'canRun' => $state['canRun'] && ($activation['valid'] ?? false) === true,
            'lockReason' => ($activation['valid'] ?? false) === true ? $state['lockReason'] : (string) $activation['message'],
            'databaseAvailable' => $state['databaseAvailable'],
            'authUserTableExists' => $state['authUserTableExists'],
            'authUserCount' => $state['authUserCount'],
            'environment' => [
                'databaseEnvironment' => $this->readEnv('APP_DATABASE_ENVIRONMENT') ?: 'dev',
                'databaseIdentity' => $this->readEnv('APP_DATABASE_IDENTITY') ?: 'db:dev',
                'authRequired' => $this->readBoolEnv('AUTH_REQUIRED'),
                'centralControl' => $this->readBoolEnv('APP_CENTRAL_CONTROL_ENABLED') || $this->readEnv('APP_SYSTEM_ROLE') === 'saas_central',
            ],
            'steps' => $this->buildSteps(),
            'message' => ($activation['valid'] ?? false) !== true
                ? (string) $activation['message']
                : ($state['canRun']
                ? 'Instalador disponivel.'
                : 'Instalador aguardando senha do instalador e confirmacao quando for reinstalacao.'),
        ];
    }

    public function precheck(array $payload): array
    {
        $normalized = $this->normalizePayload($payload);
        $state = $this->resolveInstallState($normalized['installerPassword']);
        $blocking = [];
        $warnings = [];
        foreach ($this->activation->blockingIssuesFor($normalized) as $issue) {
            $blocking[] = $issue;
        }
        if (
            !$state['canRun']
            && $state['systemInstalled']
            && !$state['databaseAvailable']
            && $normalized['databaseUrl'] !== ''
            && $state['installerPasswordAccepted']
        ) {
            $state['canRun'] = true;
            $state['lockReason'] = '';
        }

        if ($state['requiresInstallerPassword'] && !$state['installerPasswordAccepted']) {
            $blocking[] = $this->issue('installer_password', $state['installerPasswordMessage']);
        }
        if ($state['systemInstalled'] && !$normalized['reinstallConfirmed']) {
            $blocking[] = $this->issue('reinstall_confirmation', 'O sistema ja foi instalado. Confirme explicitamente que deseja reinstalar.');
        }
        if ($state['systemInstalled']) {
            $backupIssue = $this->validateBackupPolicy($normalized);
            if ($backupIssue !== null) {
                $blocking[] = $backupIssue;
            }
        }
        if (!$state['canRun'] && $state['installerPasswordAccepted']) {
            $blocking[] = $this->issue('installer_locked', $state['lockReason'] ?: 'Instalador bloqueado.');
        }
        if ($normalized['subscriberCode'] === '') {
            $blocking[] = $this->issue('subscriber_code', 'Informe o codigo do assinante principal.');
        } elseif (!preg_match('/^[a-z0-9][a-z0-9._-]{1,60}$/i', $normalized['subscriberCode'])) {
            $blocking[] = $this->issue('subscriber_code_format', 'Use codigo de assinante com letras, numeros, ponto, hifen ou underline.');
        }
        if ($normalized['subscriberName'] === '') {
            $blocking[] = $this->issue('subscriber_name', 'Informe o nome do assinante principal.');
        }
        if ($normalized['adminUsername'] === '') {
            $blocking[] = $this->issue('admin_username', 'Informe o usuario administrador inicial.');
        }

        $passwordPolicy = $this->evaluatePassword($normalized['adminPassword'], $normalized['subscriberCode'], $normalized['adminUsername']);
        if ($passwordPolicy['status'] === 'error') {
            $blocking[] = $this->issue('admin_password', $passwordPolicy['message']);
        }

        if ($normalized['databaseUrl'] === '' && $normalized['saveEnv'] === true) {
            $warnings[] = $this->issue('database_url_not_saved', 'DATABASE_URL nao foi informado; .env.local sera atualizado apenas com as demais chaves.');
        }
        if ($normalized['databaseUrl'] === '' && !$state['databaseAvailable']) {
            $warnings[] = $this->issue('database_current_unavailable', 'A conexao atual do backend nao respondeu; informe DATABASE_URL ou revise o .env.local antes de executar.');
        }

        $checklist = [
            $this->check('installed_state', 'Estado da instalacao', $state['systemInstalled'] ? 'warning' : 'ok', $state['systemInstalled'] ? 'Sistema ja marcado como instalado; esta operacao sera reinstalacao.' : 'Primeira instalacao.'),
            $this->check('activation', 'Ativacao pelo instalador compilado', $this->activation->status()['valid'] ? 'ok' : 'error', (string) $this->activation->status()['message']),
            $this->check('installer_password', 'Senha do instalador', !$state['requiresInstallerPassword'] || $state['installerPasswordAccepted'] ? 'ok' : 'error', $state['installerPasswordMessage']),
            $this->check('reinstall_confirmation', 'Confirmacao de reinstalacao', !$state['systemInstalled'] || $normalized['reinstallConfirmed'] ? 'ok' : 'error', $state['systemInstalled'] ? 'Confirme a reinstalacao antes de executar.' : 'Nao se aplica a primeira instalacao.'),
            $this->check('reinstall_backup', 'Backup antes da reinstalacao', !$state['systemInstalled'] || $this->validateBackupPolicy($normalized) === null ? 'ok' : 'error', $state['systemInstalled'] ? $this->backupPolicyMessage($normalized) : 'Nao se aplica a primeira instalacao.'),
            $this->check('database', 'Banco configurado', $normalized['databaseUrl'] !== '' || $state['databaseAvailable'] ? 'ok' : 'warning', $normalized['databaseUrl'] !== '' ? 'DATABASE_URL informado para a execucao.' : 'Usando configuracao atual do backend.'),
            $this->check('subscriber', 'Assinante principal', $normalized['subscriberCode'] !== '' && $normalized['subscriberName'] !== '' ? 'ok' : 'error', 'Sera criado ou atualizado pelo comando app:subscriber:create.'),
            $this->check('admin', 'Administrador inicial', $normalized['adminUsername'] !== '' && $passwordPolicy['status'] === 'ok' ? 'ok' : 'error', $passwordPolicy['message']),
            $this->check('seed', 'Seed de metadados runtime', $normalized['runSeed'] ? 'ok' : 'warning', $normalized['runSeed'] ? 'Sera executado no bootstrap.' : 'Foi desmarcado no formulario.'),
            $this->check('publish', 'Publicacao do catalogo padrao', $normalized['publishDefaults'] ? 'ok' : 'warning', $normalized['publishDefaults'] ? 'Sera validada apos criar o assinante.' : 'Foi desmarcada no formulario.'),
            $this->check('integrity', 'Integridade estrutural', $normalized['runIntegrity'] ? 'ok' : 'warning', $normalized['runIntegrity'] ? 'Sera validada ao final.' : 'Foi desmarcada no formulario.'),
        ];

        return [
            'payload' => $this->safePayload($normalized),
            'canRun' => $blocking === [],
            'hasBlockingIssues' => $blocking !== [],
            'blockingIssues' => $blocking,
            'warnings' => $warnings,
            'checklist' => $checklist,
            'steps' => $this->buildSteps($normalized),
        ];
    }

    public function run(array $payload): array
    {
        $normalized = $this->normalizePayload($payload);
        $precheck = $this->precheck($payload);
        if (($precheck['hasBlockingIssues'] ?? false) === true) {
            throw new RuntimeHttpException('INSTALL_PRECHECK_FAILED', 'Revise os bloqueios antes de executar a instalacao.', 422, $precheck);
        }

        if ($normalized['saveEnv'] === true) {
            $this->writeEnvLocal($normalized, false, true);
        }

        $steps = $this->buildSteps($normalized);
        $env = $this->buildProcessEnvironment($normalized);
        $output = '';
        $startedAt = new \DateTimeImmutable();

        foreach ($steps as $index => $step) {
            $steps[$index]['status'] = 'running';
            $steps[$index]['startedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $result = $this->runStep($step['code'], $normalized, $env);
            $output .= "\n\n[" . $step['code'] . "]\n" . $result['output'];
            $steps[$index]['durationSeconds'] = $result['durationSeconds'];
            $steps[$index]['outputTail'] = $this->tail($result['output']);

            if ($result['exitCode'] !== 0) {
                $steps[$index]['status'] = 'failed';
                $steps[$index]['message'] = $result['error'] ?: 'Falha na etapa.';

                return [
                    'success' => false,
                    'status' => 'failed',
                    'startedAt' => $startedAt->format(DATE_ATOM),
                    'finishedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'steps' => $steps,
                    'outputTail' => $this->tail($output),
                    'precheck' => $precheck,
                ];
            }

            $steps[$index]['status'] = 'succeeded';
            $steps[$index]['message'] = 'Etapa concluida.';
        }

        $this->writeEnvLocal($normalized, true, $normalized['saveEnv']);

        return [
            'success' => true,
            'status' => 'succeeded',
            'startedAt' => $startedAt->format(DATE_ATOM),
            'finishedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'steps' => $steps,
            'outputTail' => $this->tail($output),
            'precheck' => $precheck,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveInstallState(string $installerPassword): array
    {
        $configuredHash = $this->readEnv('APP_INSTALLER_PASSWORD_HASH');
        $plainConfiguredPassword = $this->readEnv('APP_INSTALLER_PASSWORD');
        $passwordConfigured = $configuredHash !== '' || $plainConfiguredPassword !== '';
        $databaseAvailable = false;
        $authUserTableExists = false;
        $authUserCount = null;
        $systemInstalled = $this->readBoolEnv('APP_SYSTEM_INSTALLED');
        $lockReason = '';
        $passwordAccepted = false;
        $passwordMessage = 'Informe uma senha forte para proteger instalacoes futuras.';

        try {
            $schemaManager = $this->connection->createSchemaManager();
            $databaseAvailable = true;
            $authUserTableExists = $schemaManager->tablesExist(['auth_user']);
            if ($authUserTableExists) {
                $authUserCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM auth_user');
                $systemInstalled = $systemInstalled || $authUserCount > 0;
            }
        } catch (\Throwable $error) {
            if ($systemInstalled) {
                $lockReason = 'Nao foi possivel confirmar o banco atual; reinstalacao exige banco acessivel e senha do instalador.';
            }
        }

        if ($passwordConfigured) {
            $passwordAccepted = $this->verifyInstallerPassword($installerPassword, $configuredHash, $plainConfiguredPassword);
            $passwordMessage = $passwordAccepted
                ? 'Senha do instalador validada.'
                : 'Senha do instalador invalida ou ausente.';
        } elseif (!$systemInstalled) {
            $passwordPolicy = $this->evaluateInstallerPassword($installerPassword);
            $passwordAccepted = $passwordPolicy['status'] === 'ok';
            $passwordMessage = $passwordPolicy['message'];
        } else {
            $passwordMessage = 'Sistema instalado sem senha do instalador salva. Configure APP_INSTALLER_PASSWORD_HASH antes de reinstalar.';
        }

        $canRun = $passwordAccepted && (!$systemInstalled || $databaseAvailable);
        if (!$canRun && $lockReason === '') {
            $lockReason = $passwordMessage;
        }

        return [
            'systemInstalled' => $systemInstalled,
            'requiresInstallerPassword' => true,
            'installerPasswordConfigured' => $passwordConfigured,
            'installerPasswordAccepted' => $passwordAccepted,
            'installerPasswordMessage' => $passwordMessage,
            'canRun' => $canRun,
            'lockReason' => $lockReason,
            'databaseAvailable' => $databaseAvailable,
            'authUserTableExists' => $authUserTableExists,
            'authUserCount' => $authUserCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $subscriberCode = $this->text($payload['subscriberCode'] ?? $payload['code'] ?? 'principal');

        $normalized = [
            'installerPassword' => (string) ($payload['installerPassword'] ?? $payload['installToken'] ?? ''),
            'reinstallConfirmed' => $this->bool($payload['reinstallConfirmed'] ?? false),
            'backupPolicy' => $this->text($payload['backupPolicy'] ?? ''),
            'backupJustification' => $this->text($payload['backupJustification'] ?? ''),
            'databaseUrl' => $this->text($payload['databaseUrl'] ?? ''),
            'saveEnv' => $this->bool($payload['saveEnv'] ?? false),
            'createDatabase' => $this->bool($payload['createDatabase'] ?? true),
            'databaseEnvironment' => $this->text($payload['databaseEnvironment'] ?? 'prod') ?: 'prod',
            'databaseIdentity' => $this->text($payload['databaseIdentity'] ?? ('install:' . $subscriberCode)) ?: ('install:' . $subscriberCode),
            'systemRole' => $this->text($payload['systemRole'] ?? 'onprem'),
            'centralControl' => $this->bool($payload['centralControl'] ?? false),
            'authRequired' => $this->bool($payload['authRequired'] ?? true),
            'mailerDsn' => $this->text($payload['mailerDsn'] ?? ''),
            'subscriberCode' => $subscriberCode,
            'subscriberName' => $this->text($payload['subscriberName'] ?? 'Principal'),
            'subscriberDocument' => $this->text($payload['subscriberDocument'] ?? ''),
            'principal' => $this->bool($payload['principal'] ?? true),
            'userTenantId' => $this->text($payload['userTenantId'] ?? 'default') ?: 'default',
            'adminUsername' => $this->text($payload['adminUsername'] ?? 'admin') ?: 'admin',
            'adminPassword' => (string) ($payload['adminPassword'] ?? ''),
            'adminDisplayName' => $this->text($payload['adminDisplayName'] ?? 'Administrador') ?: 'Administrador',
            'adminEmail' => $this->text($payload['adminEmail'] ?? ''),
            'forcePasswordChange' => $this->bool($payload['forcePasswordChange'] ?? true),
            'runSeed' => $this->bool($payload['runSeed'] ?? true),
            'publishDefaults' => $this->bool($payload['publishDefaults'] ?? true),
            'runIntegrity' => $this->bool($payload['runIntegrity'] ?? true),
        ];
        $activation = $this->activation->status();
        if (($activation['valid'] ?? false) === true) {
            $normalized['subscriberCode'] = (string) $activation['subscriberCode'];
            if ((string) $activation['profile'] === InstallationActivationService::PROFILE_SYSTEM_BUILDER) {
                $normalized['systemRole'] = 'saas_central';
                $normalized['centralControl'] = true;
            }
            if ((string) $activation['profile'] === InstallationActivationService::PROFILE_SUBSCRIBER) {
                $normalized['systemRole'] = $normalized['systemRole'] === 'saas_central' ? 'onprem' : $normalized['systemRole'];
                $normalized['centralControl'] = false;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return list<array<string, mixed>>
     */
    private function buildSteps(?array $payload = null): array
    {
        $steps = [
            [
                'code' => self::STEP_BOOTSTRAP,
                'label' => 'Criar banco, aplicar migrations e seed',
                'status' => 'pending',
            ],
            [
                'code' => self::STEP_SUBSCRIBER,
                'label' => 'Criar assinante principal e administrador',
                'status' => 'pending',
            ],
        ];

        if (($payload['publishDefaults'] ?? true) === true) {
            $steps[] = [
                'code' => self::STEP_PUBLISH_DEFAULTS,
                'label' => 'Publicar e validar catalogo padrao',
                'status' => 'pending',
            ];
        }
        if (($payload['runIntegrity'] ?? true) === true) {
            $steps[] = [
                'code' => self::STEP_INTEGRITY,
                'label' => 'Validar integridade estrutural',
                'status' => 'pending',
            ];
        }

        return $steps;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $env
     *
     * @return array{exitCode: int, output: string, error: string, durationSeconds: float}
     */
    private function runStep(string $stepCode, array $payload, array $env): array
    {
        $command = match ($stepCode) {
            self::STEP_BOOTSTRAP => $this->bootstrapCommand($payload),
            self::STEP_SUBSCRIBER => $this->subscriberCommand($payload),
            self::STEP_PUBLISH_DEFAULTS => [$this->phpBinary(), 'bin/console', 'app:runtime:publish-defaults', '--refresh', '--fail-on-missing'],
            self::STEP_INTEGRITY => [$this->phpBinary(), 'bin/console', 'app:integrity:monitor', '--fail-on-invalid'],
            default => throw new \InvalidArgumentException('Etapa de instalacao nao suportada.'),
        };

        $startedAt = microtime(true);
        $process = new Process($command, $this->kernel->getProjectDir(), $env, null, 7200);
        $process->run();

        return [
            'exitCode' => $process->getExitCode() ?? 1,
            'output' => trim($process->getOutput() . "\n" . $process->getErrorOutput()),
            'error' => trim($process->getErrorOutput()),
            'durationSeconds' => round(microtime(true) - $startedAt, 3),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function bootstrapCommand(array $payload): array
    {
        $command = [
            $this->phpBinary(),
            'bin/console',
            'app:install:bootstrap',
            '--skip-publish-defaults',
            '--skip-integrity',
            '--database-environment=' . (string) $payload['databaseEnvironment'],
            '--database-identity=' . (string) $payload['databaseIdentity'],
        ];
        if ($payload['createDatabase'] === true) {
            $command[] = '--create-database';
        }
        if ($payload['runSeed'] !== true) {
            $command[] = '--skip-seed';
        }

        return $command;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function subscriberCommand(array $payload): array
    {
        $command = [
            $this->phpBinary(),
            'bin/console',
            'app:subscriber:create',
            '--code=' . (string) $payload['subscriberCode'],
            '--name=' . (string) $payload['subscriberName'],
            '--user-tenant-id=' . (string) $payload['userTenantId'],
            '--admin-username=' . (string) $payload['adminUsername'],
            '--admin-password=' . (string) $payload['adminPassword'],
            '--admin-display-name=' . (string) $payload['adminDisplayName'],
        ];
        if ((string) $payload['subscriberDocument'] !== '') {
            $command[] = '--document=' . (string) $payload['subscriberDocument'];
        }
        if ((string) $payload['adminEmail'] !== '') {
            $command[] = '--admin-email=' . (string) $payload['adminEmail'];
        }
        if ($payload['principal'] === true) {
            $command[] = '--principal';
        }
        if ($payload['forcePasswordChange'] === true) {
            $command[] = '--force-password-change';
        }

        return $command;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private function buildProcessEnvironment(array $payload): array
    {
        $env = [
            'APP_DATABASE_ENVIRONMENT' => (string) $payload['databaseEnvironment'],
            'APP_DATABASE_IDENTITY' => (string) $payload['databaseIdentity'],
            'AUTH_REQUIRED' => $payload['authRequired'] === true ? '1' : '0',
            'APP_CENTRAL_CONTROL_ENABLED' => $payload['centralControl'] === true ? '1' : '0',
        ];
        if ((string) $payload['databaseUrl'] !== '') {
            $env['DATABASE_URL'] = (string) $payload['databaseUrl'];
        }
        if ((string) $payload['systemRole'] !== '') {
            $env['APP_SYSTEM_ROLE'] = (string) $payload['systemRole'];
        }
        if ((string) $payload['mailerDsn'] !== '') {
            $env['MAILER_DSN'] = (string) $payload['mailerDsn'];
        }

        return $env;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeEnvLocal(array $payload, bool $markInstalled, bool $includeConfig): void
    {
        $path = $this->kernel->getProjectDir() . '/.env.local';
        $values = $includeConfig ? [
            'DATABASE_URL' => (string) $payload['databaseUrl'],
            'APP_DATABASE_ENVIRONMENT' => (string) $payload['databaseEnvironment'],
            'APP_DATABASE_IDENTITY' => (string) $payload['databaseIdentity'],
            'APP_SYSTEM_ROLE' => (string) $payload['systemRole'],
            'APP_CENTRAL_CONTROL_ENABLED' => $payload['centralControl'] === true ? '1' : '0',
            'AUTH_REQUIRED' => $payload['authRequired'] === true ? '1' : '0',
            'MAILER_DSN' => (string) $payload['mailerDsn'],
        ] : [];
        $values = array_merge($values, $this->activation->envValues());
        if ($markInstalled) {
            $values['APP_SYSTEM_INSTALLED'] = '1';
            if ($this->readEnv('APP_INSTALLER_PASSWORD_HASH') === '' && (string) $payload['installerPassword'] !== '') {
                $values['APP_INSTALLER_PASSWORD_HASH'] = password_hash((string) $payload['installerPassword'], PASSWORD_DEFAULT);
            }
        }
        $values = array_filter($values, static fn (string $value): bool => $value !== '');

        $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
        if (!is_array($lines)) {
            $lines = [];
        }
        $keys = array_fill_keys(array_keys($values), true);
        $updated = [];
        foreach ($lines as $line) {
            $trimmed = ltrim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                $updated[] = (string) $line;
                continue;
            }
            [$key] = explode('=', $trimmed, 2);
            $key = trim($key);
            if (!isset($keys[$key])) {
                $updated[] = (string) $line;
                continue;
            }
            $updated[] = $key . '=' . $this->quoteEnvValue($values[$key]);
            unset($keys[$key]);
        }
        foreach (array_keys($keys) as $key) {
            $updated[] = $key . '=' . $this->quoteEnvValue($values[$key]);
        }

        file_put_contents($path, implode(PHP_EOL, $updated) . PHP_EOL);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        $safe = $payload;
        $safe['adminPassword'] = $payload['adminPassword'] !== '' ? '********' : '';
        $safe['installerPassword'] = $payload['installerPassword'] !== '' ? '********' : '';
        if ((string) ($safe['databaseUrl'] ?? '') !== '') {
            $safe['databaseUrl'] = $this->maskDatabaseUrl((string) $safe['databaseUrl']);
        }

        return $safe;
    }

    private function evaluatePassword(string $password, string $subscriberCode, string $adminUsername): array
    {
        $checks = [
            preg_match('/[a-z]/', $password) === 1,
            preg_match('/[A-Z]/', $password) === 1,
            preg_match('/\d/', $password) === 1,
            preg_match('/[^a-zA-Z0-9]/', $password) === 1,
            mb_strlen($password) >= 14,
            $subscriberCode === '' || stripos($password, $subscriberCode) === false,
            $adminUsername === '' || stripos($password, $adminUsername) === false,
        ];

        if (in_array(false, $checks, true)) {
            return [
                'status' => 'error',
                'message' => 'A senha inicial precisa ter pelo menos 14 caracteres, maiuscula, minuscula, numero, simbolo e nao pode repetir usuario ou codigo do assinante.',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Credencial inicial atende a politica minima.',
        ];
    }

    private function evaluateInstallerPassword(string $password): array
    {
        $checks = [
            preg_match('/[a-z]/', $password) === 1,
            preg_match('/[A-Z]/', $password) === 1,
            preg_match('/\d/', $password) === 1,
            preg_match('/[^a-zA-Z0-9]/', $password) === 1,
            mb_strlen($password) >= 14,
        ];

        if (in_array(false, $checks, true)) {
            return [
                'status' => 'error',
                'message' => 'Defina uma senha do instalador com pelo menos 14 caracteres, maiuscula, minuscula, numero e simbolo.',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Senha do instalador definida para proteger reinstalacoes futuras.',
        ];
    }

    private function validateBackupPolicy(array $payload): ?array
    {
        $policy = (string) ($payload['backupPolicy'] ?? '');
        if (!in_array($policy, ['validated', 'skip_with_reason', 'discardable_test'], true)) {
            return $this->issue('reinstall_backup_policy', 'Escolha a politica de backup antes de reinstalar.');
        }
        if ($policy === 'skip_with_reason' && mb_strlen((string) ($payload['backupJustification'] ?? '')) < 20) {
            return $this->issue('reinstall_backup_justification', 'Informe uma justificativa com pelo menos 20 caracteres para pular o backup.');
        }
        if ($policy === 'discardable_test') {
            $environment = strtolower((string) ($payload['databaseEnvironment'] ?? 'prod'));
            if (!in_array($environment, ['dev', 'test', 'homolog', 'sandbox'], true)) {
                return $this->issue('reinstall_backup_discardable_prod', 'Ambiente descartavel/teste nao pode ser usado com banco marcado como producao.');
            }
        }

        return null;
    }

    private function backupPolicyMessage(array $payload): string
    {
        $issue = $this->validateBackupPolicy($payload);
        if ($issue !== null) {
            return (string) $issue['message'];
        }
        return match ((string) ($payload['backupPolicy'] ?? '')) {
            'validated' => 'Backup validado antes da reinstalacao.',
            'skip_with_reason' => 'Backup pulado com justificativa registrada.',
            'discardable_test' => 'Ambiente descartavel/teste confirmado.',
            default => 'Politica de backup informada.',
        };
    }

    private function verifyInstallerPassword(string $password, string $configuredHash, string $plainConfiguredPassword): bool
    {
        if ($password === '') {
            return false;
        }
        if ($configuredHash !== '') {
            return password_verify($password, $configuredHash);
        }

        return $plainConfiguredPassword !== '' && hash_equals($plainConfiguredPassword, $password);
    }

    private function maskDatabaseUrl(string $value): string
    {
        $parts = parse_url($value);
        if (!is_array($parts) || !isset($parts['pass'])) {
            return $value;
        }

        return str_replace((string) $parts['pass'], '********', $value);
    }

    private function check(string $code, string $label, string $status, string $message): array
    {
        return compact('code', 'label', 'status', 'message');
    }

    private function issue(string $code, string $message): array
    {
        return compact('code', 'message');
    }

    private function readEnv(string $name): string
    {
        $value = getenv($name);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return trim((string) ($_SERVER[$name] ?? $_ENV[$name] ?? ''));
    }

    private function readBoolEnv(string $name): bool
    {
        return in_array(strtolower($this->readEnv($name)), ['1', 'true', 'yes', 'on'], true);
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }

    private function phpBinary(): string
    {
        return PHP_BINARY ?: 'php';
    }

    private function quoteEnvValue(string $value): string
    {
        return '"' . str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value) . '"';
    }

    private function tail(string $text, int $length = 6000): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $length ? mb_substr($text, -1 * $length) : $text;
    }
}
