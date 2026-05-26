<?php

namespace App\Runtime;

use App\Repository\InstallerActivationLicenseRepository;
use App\Repository\InstallerActivationServiceTokenRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class CentralOperationsService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly InstallerActivationLicenseRepository $licenses,
        private readonly InstallerActivationServiceTokenRepository $serviceTokens,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $licenses = $this->licenseRows();
        $tokens = $this->tokenRows();
        $executions = $this->executionRows();
        $artifacts = $this->artifactRows();
        $keys = $this->keyRows();
        $attemptPolicy = $this->attemptPolicy();
        $subscribers = $this->subscriberRows($licenses, $executions);
        $alerts = $this->alerts($licenses, $tokens, $executions, $artifacts, $keys, $attemptPolicy);

        return [
            'summary' => $this->summary($licenses, $tokens, $executions, $artifacts, $keys, $alerts),
            'subscribers' => $subscribers,
            'licenses' => $licenses,
            'serviceTokens' => $tokens,
            'artifacts' => $artifacts,
            'keys' => $keys,
            'attemptPolicy' => $attemptPolicy,
            'alerts' => $alerts,
            'notifications' => $this->notifications($alerts, $subscribers),
            'audit' => $this->auditRows($licenses, $tokens),
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function licenseAction(array $payload): array
    {
        $action = $this->text($payload['action'] ?? '');
        $subscriberCode = $this->text($payload['subscriberCode'] ?? '');
        $fingerprint = $this->text($payload['fingerprint'] ?? '');
        $reason = $this->text($payload['reason'] ?? 'Ajuste administrativo pela central');
        $license = $this->licenses->findOneBySubscriberCode($subscriberCode);
        if ($license === null) {
            throw new RuntimeHttpException('CENTRAL_OPERATIONS_LICENSE_NOT_FOUND', 'Licenca nao encontrada.', 404);
        }

        $metadata = $license->getMetadata();
        $event = [
            'type' => $action,
            'reason' => $reason,
            'fingerprint' => $fingerprint,
            'at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'source' => 'admin.central-operacoes',
        ];
        $this->appendMetadata($metadata, 'auditTrail', $event, 80);

        if ($action === 'suspend_license') {
            $license->setStatus('suspended');
        } elseif ($action === 'activate_license') {
            $license->setStatus('active');
        } elseif ($action === 'revoke_license') {
            $license->setStatus('revoked');
            $this->appendMetadata($metadata, 'revocationHistory', $event, 80);
        } elseif ($action === 'revoke_fingerprint') {
            if ($fingerprint === '') {
                throw new RuntimeHttpException('CENTRAL_OPERATIONS_FINGERPRINT_REQUIRED', 'Informe o fingerprint para revogar.', 422);
            }
            $revoked = is_array($metadata['revokedFingerprints'] ?? null) ? $metadata['revokedFingerprints'] : [];
            if (!in_array($fingerprint, $revoked, true)) {
                $revoked[] = $fingerprint;
            }
            $metadata['revokedFingerprints'] = array_values($revoked);
            $this->appendMetadata($metadata, 'revocationHistory', $event, 80);
        } else {
            throw new RuntimeHttpException('CENTRAL_OPERATIONS_ACTION_INVALID', 'Acao da licenca invalida.', 422);
        }

        $license->setMetadata($metadata);
        $this->entityManager->flush();

        return ['ok' => true, 'dashboard' => $this->dashboard()];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function tokenAction(array $payload): array
    {
        $action = $this->text($payload['action'] ?? '');
        $code = $this->text($payload['code'] ?? '');
        $reason = $this->text($payload['reason'] ?? 'Ajuste administrativo pela central');
        $token = $this->serviceTokens->findOneByCode($code);
        if ($token === null) {
            throw new RuntimeHttpException('CENTRAL_OPERATIONS_TOKEN_NOT_FOUND', 'Token interno nao encontrado.', 404);
        }

        $metadata = $token->getMetadata();
        $event = [
            'type' => $action,
            'reason' => $reason,
            'at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'source' => 'admin.central-operacoes',
        ];
        $this->appendMetadata($metadata, 'auditTrail', $event, 80);

        if ($action === 'suspend_token') {
            $token->setStatus('suspended');
        } elseif ($action === 'activate_token') {
            $token->setStatus('active');
        } elseif ($action === 'revoke_token') {
            $token->setStatus('revoked');
            $this->appendMetadata($metadata, 'revocationHistory', $event, 80);
        } else {
            throw new RuntimeHttpException('CENTRAL_OPERATIONS_ACTION_INVALID', 'Acao do token invalida.', 422);
        }

        $token->setMetadata($metadata);
        $this->entityManager->flush();

        return ['ok' => true, 'dashboard' => $this->dashboard()];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function licenseRows(): array
    {
        if (!$this->tableExists('installer_activation_license')) {
            return [];
        }

        return array_map(function (array $row): array {
            $metadata = $this->decodeJson($row['metadata'] ?? []);
            $fingerprints = is_array($metadata['fingerprints'] ?? null) ? $metadata['fingerprints'] : [];
            $revoked = is_array($metadata['revokedFingerprints'] ?? null) ? $metadata['revokedFingerprints'] : [];

            return [
                'id' => (int) $row['id'],
                'subscriberCode' => (string) $row['subscriber_code'],
                'subscriberName' => (string) $row['subscriber_name'],
                'activationEmail' => (string) $row['activation_email'],
                'status' => (string) $row['status'],
                'allowedProfiles' => $this->decodeJson($row['allowed_profiles'] ?? []),
                'allowedModes' => $this->decodeJson($row['allowed_modes'] ?? []),
                'maxActivations' => (int) $row['max_activations'],
                'activationCount' => (int) $row['activation_count'],
                'expiresAt' => $this->dateString($row['expires_at'] ?? null),
                'lastActivatedAt' => $this->dateString($row['last_activated_at'] ?? null),
                'fingerprintCount' => count($fingerprints),
                'revokedFingerprintCount' => count($revoked),
                'fingerprints' => array_keys($fingerprints),
                'revokedFingerprints' => array_values($revoked),
                'metadata' => $metadata,
            ];
        }, $this->connection->fetchAllAssociative(
            'SELECT * FROM installer_activation_license ORDER BY updated_at DESC, id DESC LIMIT 300'
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tokenRows(): array
    {
        if (!$this->tableExists('installer_activation_service_token')) {
            return [];
        }

        return array_map(function (array $row): array {
            $metadata = $this->decodeJson($row['metadata'] ?? []);

            return [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'status' => (string) $row['status'],
                'allowedProfiles' => $this->decodeJson($row['allowed_profiles'] ?? []),
                'allowedModes' => $this->decodeJson($row['allowed_modes'] ?? []),
                'expiresAt' => $this->dateString($row['expires_at'] ?? null),
                'lastUsedAt' => $this->dateString($row['last_used_at'] ?? null),
                'usageCount' => (int) $row['usage_count'],
                'metadata' => $metadata,
            ];
        }, $this->connection->fetchAllAssociative(
            'SELECT * FROM installer_activation_service_token ORDER BY updated_at DESC, id DESC LIMIT 300'
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function executionRows(): array
    {
        if (!$this->tableExists('system_update_execution')) {
            return [];
        }

        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'subscriberCode' => (string) ($row['target_subscriber_code'] ?? ''),
                'subscriberName' => (string) ($row['target_subscriber_name'] ?? ''),
                'version' => (string) $row['release_version'],
                'title' => (string) $row['release_title'],
                'category' => (string) $row['category'],
                'severity' => (string) $row['severity'],
                'status' => (string) $row['status'],
                'mode' => (string) $row['mode'],
                'errorMessage' => (string) ($row['error_message'] ?? ''),
                'createdAt' => $this->dateString($row['created_at'] ?? null),
                'finishedAt' => $this->dateString($row['finished_at'] ?? null),
            ];
        }, $this->connection->fetchAllAssociative(
            'SELECT * FROM system_update_execution ORDER BY created_at DESC, id DESC LIMIT 500'
        ));
    }

    /**
     * @param list<array<string, mixed>> $licenses
     * @param list<array<string, mixed>> $executions
     *
     * @return list<array<string, mixed>>
     */
    private function subscriberRows(array $licenses, array $executions): array
    {
        $latestSuccess = [];
        $latestAny = [];
        foreach ($executions as $execution) {
            $code = (string) ($execution['subscriberCode'] ?? '');
            if ($code === '') {
                continue;
            }
            $latestAny[$code] ??= $execution;
            if (($execution['status'] ?? '') === 'succeeded') {
                $latestSuccess[$code] ??= $execution;
            }
        }

        return array_values(array_map(function (array $license) use ($latestSuccess, $latestAny): array {
            $code = (string) $license['subscriberCode'];
            $current = $latestSuccess[$code] ?? null;
            $last = $latestAny[$code] ?? null;
            $alerts = [];
            if (($license['status'] ?? '') !== 'active') {
                $alerts[] = 'Licenca ' . (string) $license['status'];
            }
            if ($last && ($last['status'] ?? '') === 'failed') {
                $alerts[] = 'Ultima atualizacao falhou';
            }
            if ((int) ($license['maxActivations'] ?? 0) > 0 && (int) ($license['activationCount'] ?? 0) >= (int) $license['maxActivations']) {
                $alerts[] = 'Limite de ativacoes atingido';
            }

            return [
                'subscriberCode' => $code,
                'subscriberName' => (string) $license['subscriberName'],
                'licenseStatus' => (string) $license['status'],
                'currentVersion' => $current['version'] ?? '',
                'lastUpdateStatus' => $last['status'] ?? '',
                'lastUpdateAt' => $last['createdAt'] ?? '',
                'lastActivationAt' => (string) ($license['lastActivatedAt'] ?? ''),
                'activationCount' => (int) ($license['activationCount'] ?? 0),
                'maxActivations' => (int) ($license['maxActivations'] ?? 0),
                'fingerprintCount' => (int) ($license['fingerprintCount'] ?? 0),
                'revokedFingerprintCount' => (int) ($license['revokedFingerprintCount'] ?? 0),
                'alerts' => $alerts,
            ];
        }, $licenses));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function artifactRows(): array
    {
        $items = [
            ['name' => 'Manifesto do instalador', 'env' => 'APP_INSTALLER_MANIFEST_URL', 'required' => true],
            ['name' => 'Compose do instalador', 'env' => 'APP_INSTALLER_DOCKER_COMPOSE_URL', 'required' => false],
            ['name' => 'Pacote nativo do instalador', 'env' => 'APP_INSTALLER_PACKAGE_URL', 'required' => false],
            ['name' => 'Manifesto de atualizacao', 'env' => 'APP_UPDATE_MANIFEST_URL', 'required' => true],
            ['name' => 'Diretorio de distribuicao', 'env' => 'APP_UPDATE_DISTRIBUTION_DIR', 'required' => true],
            ['name' => 'Base publica de distribuicao', 'env' => 'APP_UPDATE_PUBLIC_BASE_URL', 'required' => false],
            ['name' => 'Orquestrador SaaS', 'env' => 'APP_UPDATE_ORCHESTRATOR_URL', 'required' => false],
        ];

        return array_map(function (array $item): array {
            $value = $this->readEnv((string) $item['env']);

            return $item + [
                'configured' => $value !== '',
                'status' => $value !== '' ? 'configured' : (($item['required'] ?? false) ? 'missing' : 'optional'),
                'valuePreview' => $this->preview($value),
            ];
        }, $items);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function keyRows(): array
    {
        $items = [
            ['name' => 'Sessao de ativacao', 'env' => 'APP_INSTALLER_ACTIVATION_SIGNING_KEY', 'required' => true],
            ['name' => 'Sessao local de instalacao', 'env' => 'APP_INSTALLATION_SESSION_SIGNING_KEY', 'required' => true],
            ['name' => 'Artefatos do instalador', 'env' => 'APP_INSTALLER_ARTIFACT_SIGNING_KEY', 'required' => true],
            ['name' => 'Manifesto de atualizacao', 'env' => 'APP_UPDATE_MANIFEST_SIGNING_KEY', 'required' => true],
            ['name' => 'Pacote de atualizacao', 'env' => 'APP_UPDATE_PACKAGE_SIGNING_KEY', 'required' => true],
            ['name' => 'Push de distribuicao', 'env' => 'APP_UPDATE_DISTRIBUTION_PUSH_SIGNING_KEY', 'required' => false],
            ['name' => 'Orquestrador SaaS', 'env' => 'APP_UPDATE_ORCHESTRATOR_SIGNING_KEY', 'required' => false],
        ];

        return array_map(function (array $item): array {
            $value = $this->readEnv((string) $item['env']);
            $length = strlen($value);
            $weak = $value !== '' && $length < 32;

            return $item + [
                'configured' => $value !== '',
                'length' => $length,
                'status' => $value === '' ? (($item['required'] ?? false) ? 'missing' : 'optional') : ($weak ? 'weak' : 'ok'),
            ];
        }, $items);
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptPolicy(): array
    {
        return [
            'maxAttempts' => max(1, (int) ($this->readEnv('APP_INSTALLER_ACTIVATION_MAX_ATTEMPTS') ?: 5)),
            'blockMinutes' => max(1, (int) ($this->readEnv('APP_INSTALLER_ACTIVATION_BLOCK_MINUTES') ?: 30)),
            'requestTtlMinutes' => 15,
            'sessionTtlMinutes' => 120,
        ];
    }

    /**
     * @param list<array<string, mixed>> $licenses
     * @param list<array<string, mixed>> $tokens
     * @param list<array<string, mixed>> $executions
     * @param list<array<string, mixed>> $artifacts
     * @param list<array<string, mixed>> $keys
     * @param list<array<string, mixed>> $alerts
     *
     * @return array<string, mixed>
     */
    private function summary(array $licenses, array $tokens, array $executions, array $artifacts, array $keys, array $alerts): array
    {
        return [
            'licenseCount' => count($licenses),
            'activeLicenses' => count(array_filter($licenses, static fn (array $row): bool => ($row['status'] ?? '') === 'active')),
            'inactiveLicenses' => count(array_filter($licenses, static fn (array $row): bool => ($row['status'] ?? '') !== 'active')),
            'serviceTokenCount' => count($tokens),
            'activeServiceTokens' => count(array_filter($tokens, static fn (array $row): bool => ($row['status'] ?? '') === 'active')),
            'updateFailureCount' => count(array_filter($executions, static fn (array $row): bool => ($row['status'] ?? '') === 'failed')),
            'missingArtifactCount' => count(array_filter($artifacts, static fn (array $row): bool => ($row['status'] ?? '') === 'missing')),
            'missingKeyCount' => count(array_filter($keys, static fn (array $row): bool => ($row['status'] ?? '') === 'missing')),
            'alertCount' => count($alerts),
        ];
    }

    /**
     * @param list<array<string, mixed>> $licenses
     * @param list<array<string, mixed>> $tokens
     * @param list<array<string, mixed>> $executions
     * @param list<array<string, mixed>> $artifacts
     * @param list<array<string, mixed>> $keys
     * @param array<string, mixed> $attemptPolicy
     *
     * @return list<array<string, mixed>>
     */
    private function alerts(array $licenses, array $tokens, array $executions, array $artifacts, array $keys, array $attemptPolicy): array
    {
        $alerts = [];
        foreach ($licenses as $license) {
            if (($license['status'] ?? '') !== 'active') {
                $alerts[] = $this->alert('warning', 'Licenca inativa', 'Assinante ' . $license['subscriberCode'] . ' esta com status ' . $license['status'] . '.', (string) $license['subscriberCode']);
            }
            if ($this->expiresSoon($license['expiresAt'] ?? null, 15)) {
                $alerts[] = $this->alert('warning', 'Licenca perto do vencimento', 'Assinante ' . $license['subscriberCode'] . ' vence em ate 15 dias.', (string) $license['subscriberCode']);
            }
        }
        foreach ($tokens as $token) {
            if (($token['status'] ?? '') !== 'active') {
                $alerts[] = $this->alert('info', 'Token interno inativo', 'Token ' . $token['code'] . ' esta com status ' . $token['status'] . '.', (string) $token['code']);
            }
            if ($this->expiresSoon($token['expiresAt'] ?? null, 15)) {
                $alerts[] = $this->alert('warning', 'Token interno perto do vencimento', 'Token ' . $token['code'] . ' vence em ate 15 dias.', (string) $token['code']);
            }
        }
        foreach ($executions as $execution) {
            if (($execution['status'] ?? '') === 'failed') {
                $alerts[] = $this->alert('error', 'Atualizacao falhou', 'Release ' . $execution['version'] . ' falhou para ' . (($execution['subscriberCode'] ?? '') ?: 'ambiente central') . '.', (string) ($execution['subscriberCode'] ?? ''));
            }
        }
        foreach ($artifacts as $artifact) {
            if (($artifact['status'] ?? '') === 'missing') {
                $alerts[] = $this->alert('error', 'Artefato sem configuracao', $artifact['env'] . ' nao esta configurado.', (string) $artifact['env']);
            }
        }
        foreach ($keys as $key) {
            if (in_array($key['status'] ?? '', ['missing', 'weak'], true)) {
                $alerts[] = $this->alert('error', 'Chave ausente ou fraca', $key['env'] . ' esta com status ' . $key['status'] . '.', (string) $key['env']);
            }
        }
        if ((int) $attemptPolicy['maxAttempts'] < 3) {
            $alerts[] = $this->alert('warning', 'Politica de tentativas baixa', 'APP_INSTALLER_ACTIVATION_MAX_ATTEMPTS esta abaixo de 3.', 'activation');
        }

        return array_slice($alerts, 0, 200);
    }

    /**
     * @param list<array<string, mixed>> $alerts
     * @param list<array<string, mixed>> $subscribers
     *
     * @return list<array<string, mixed>>
     */
    private function notifications(array $alerts, array $subscribers): array
    {
        $items = array_map(function (array $alert): array {
            return [
                'severity' => $alert['severity'],
                'title' => $alert['title'],
                'message' => $alert['message'],
                'target' => $alert['target'],
                'suggestedChannel' => 'admin.central-operacoes',
            ];
        }, $alerts);

        foreach ($subscribers as $subscriber) {
            if (($subscriber['currentVersion'] ?? '') === '') {
                $items[] = [
                    'severity' => 'info',
                    'title' => 'Assinante sem versao aplicada',
                    'message' => 'Assinante ' . $subscriber['subscriberCode'] . ' ainda nao tem atualizacao concluida registrada.',
                    'target' => $subscriber['subscriberCode'],
                    'suggestedChannel' => 'admin.central-operacoes',
                ];
            }
        }

        return array_slice($items, 0, 200);
    }

    /**
     * @param list<array<string, mixed>> $licenses
     * @param list<array<string, mixed>> $tokens
     *
     * @return list<array<string, mixed>>
     */
    private function auditRows(array $licenses, array $tokens): array
    {
        $items = [];
        foreach ($licenses as $license) {
            foreach (['activationHistory', 'auditTrail', 'revocationHistory'] as $key) {
                foreach ($this->metadataList($license['metadata'] ?? [], $key) as $event) {
                    $items[] = [
                        'source' => 'license',
                        'target' => (string) $license['subscriberCode'],
                        'type' => $key,
                        'at' => (string) ($event['at'] ?? $event['issuedAt'] ?? ''),
                        'detail' => $event,
                    ];
                }
            }
        }
        foreach ($tokens as $token) {
            foreach (['usageHistory', 'auditTrail', 'revocationHistory'] as $key) {
                foreach ($this->metadataList($token['metadata'] ?? [], $key) as $event) {
                    $items[] = [
                        'source' => 'service_token',
                        'target' => (string) $token['code'],
                        'type' => $key,
                        'at' => (string) ($event['at'] ?? $event['usedAt'] ?? ''),
                        'detail' => $event,
                    ];
                }
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));

        return array_slice($items, 0, 200);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function metadataList(mixed $metadata, string $key): array
    {
        if (!is_array($metadata) || !is_array($metadata[$key] ?? null)) {
            return [];
        }

        return array_values(array_filter($metadata[$key], 'is_array'));
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $event
     */
    private function appendMetadata(array &$metadata, string $key, array $event, int $limit): void
    {
        $items = is_array($metadata[$key] ?? null) ? $metadata[$key] : [];
        $items[] = $event;
        $metadata[$key] = array_slice($items, -$limit);
    }

    /**
     * @return array<string, string>
     */
    private function alert(string $severity, string $title, string $message, string $target): array
    {
        return [
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'target' => $target,
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    private function expiresSoon(mixed $value, int $days): bool
    {
        $text = $this->text($value);
        if ($text === '') {
            return false;
        }
        try {
            $date = new \DateTimeImmutable($text);
        } catch (\Throwable) {
            return false;
        }

        $now = new \DateTimeImmutable();
        return $date >= $now && $date <= $now->modify('+' . $days . ' days');
    }

    private function tableExists(string $tableName): bool
    {
        try {
            return $this->connection->createSchemaManager()->tablesExist([$tableName]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return mixed
     */
    private function decodeJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function dateString(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : trim((string) $value);
    }

    private function preview(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= 80) {
            return $value;
        }

        return substr($value, 0, 36) . '...' . substr($value, -24);
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }

    private function readEnv(string $name): string
    {
        $value = getenv($name);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return trim((string) ($_SERVER[$name] ?? $_ENV[$name] ?? ''));
    }
}
