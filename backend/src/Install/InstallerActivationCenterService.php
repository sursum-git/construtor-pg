<?php

namespace App\Install;

use App\Entity\InstallerActivationLicense;
use App\Entity\InstallerActivationServiceToken;
use App\Repository\InstallerActivationLicenseRepository;
use App\Repository\InstallerActivationServiceTokenRepository;
use App\Runtime\RuntimeHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class InstallerActivationCenterService
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly MailerInterface $mailer,
        private readonly InstallerActivationLicenseRepository $licenses,
        private readonly InstallerActivationServiceTokenRepository $serviceTokens,
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $registry,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function request(array $payload): array
    {
        $profile = $this->profile((string) ($payload['profile'] ?? ''));
        $subscriberCode = $this->subscriberCode((string) ($payload['subscriberCode'] ?? ''));
        $mode = $this->text($payload['mode'] ?? 'docker');
        $fingerprint = $this->text($payload['fingerprint'] ?? '');
        $subscriber = $this->resolveSubscriber($subscriberCode, $profile, $mode, $fingerprint);
        $requestId = bin2hex(random_bytes(18));
        $code = (string) random_int(100000, 999999);
        $request = [
            'requestId' => $requestId,
            'codeHash' => password_hash($code, PASSWORD_DEFAULT),
            'attemptCount' => 0,
            'blockedUntil' => null,
            'profile' => $profile,
            'subscriberCode' => $subscriberCode,
            'mode' => $mode,
            'fingerprint' => $fingerprint,
            'platform' => $this->text($payload['platform'] ?? ''),
            'arch' => $this->text($payload['arch'] ?? ''),
            'email' => $subscriber['email'],
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'expiresAt' => (new \DateTimeImmutable('+15 minutes'))->format(DATE_ATOM),
        ];
        $this->writeRequest($requestId, $request);
        $this->sendCode((string) $subscriber['email'], $code, $subscriberCode, $profile);

        $response = [
            'requestId' => $requestId,
            'maskedEmail' => $this->maskEmail((string) $subscriber['email']),
            'expiresAt' => $request['expiresAt'],
            'message' => 'Codigo de confirmacao enviado ao e-mail cadastrado.',
        ];
        if ($this->kernel->getEnvironment() === 'dev') {
            $response['devCode'] = $code;
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function confirm(array $payload): array
    {
        $requestId = $this->text($payload['requestId'] ?? '');
        $code = $this->text($payload['code'] ?? '');
        $request = $this->readRequest($requestId);
        if ($request === []) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_NOT_FOUND', 'Solicitacao de ativacao nao encontrada.', 404);
        }
        $expiresAt = new \DateTimeImmutable((string) $request['expiresAt']);
        if ($expiresAt <= new \DateTimeImmutable()) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_EXPIRED', 'Codigo de ativacao expirado.', 422);
        }
        $blockedUntil = $this->text($request['blockedUntil'] ?? '');
        if ($blockedUntil !== '') {
            $blockedDate = new \DateTimeImmutable($blockedUntil);
            if ($blockedDate > new \DateTimeImmutable()) {
                throw new RuntimeHttpException('INSTALLER_ACTIVATION_BLOCKED', 'Muitas tentativas invalidas. Solicite novo codigo depois do bloqueio temporario.', 429, [
                    'blockedUntil' => $blockedDate->format(DATE_ATOM),
                ]);
            }
        }
        if (!password_verify($code, (string) $request['codeHash'])) {
            $attemptCount = (int) ($request['attemptCount'] ?? 0) + 1;
            $maxAttempts = max(1, (int) ($this->readEnv('APP_INSTALLER_ACTIVATION_MAX_ATTEMPTS') ?: 5));
            $request['attemptCount'] = $attemptCount;
            $request['lastInvalidAttemptAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            if ($attemptCount >= $maxAttempts) {
                $blockedDate = (new \DateTimeImmutable())->modify('+' . max(1, (int) ($this->readEnv('APP_INSTALLER_ACTIVATION_BLOCK_MINUTES') ?: 30)) . ' minutes');
                $request['blockedUntil'] = $blockedDate->format(DATE_ATOM);
                $this->writeRequest($requestId, $request);
                throw new RuntimeHttpException('INSTALLER_ACTIVATION_BLOCKED', 'Muitas tentativas invalidas. Solicite novo codigo depois do bloqueio temporario.', 429, [
                    'blockedUntil' => $request['blockedUntil'],
                    'attemptCount' => $attemptCount,
                    'maxAttempts' => $maxAttempts,
                ]);
            }
            $this->writeRequest($requestId, $request);
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_CODE_INVALID', 'Codigo de ativacao invalido.', 422, [
                'attemptCount' => $attemptCount,
                'maxAttempts' => $maxAttempts,
            ]);
        }

        return $this->issueSession($request);
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $authorization
     *
     * @return array<string, mixed>
     */
    public function service(array $payload, string $authorization): array
    {
        $provided = preg_replace('/^Bearer\s+/i', '', trim($authorization));
        $profile = $this->profile((string) ($payload['profile'] ?? ''));
        $subscriberCode = $this->subscriberCode((string) ($payload['subscriberCode'] ?? ''));
        $mode = $this->text($payload['mode'] ?? 'saas-docker');
        $fingerprint = $this->text($payload['fingerprint'] ?? '');
        $serviceToken = $this->assertServiceTokenAllows((string) $provided, $profile, $mode, $subscriberCode, $fingerprint);
        $this->resolveSubscriber($subscriberCode, $profile, $mode, $fingerprint);

        $session = $this->issueSession([
            'requestId' => 'service-' . bin2hex(random_bytes(12)),
            'profile' => $profile,
            'subscriberCode' => $subscriberCode,
            'mode' => $mode,
            'fingerprint' => $fingerprint,
            'platform' => $this->text($payload['platform'] ?? ''),
            'arch' => $this->text($payload['arch'] ?? ''),
        ]);
        if ($serviceToken !== null) {
            $serviceToken->registerUse([
                'subscriberCode' => $subscriberCode,
                'profile' => $profile,
                'mode' => $mode,
                'fingerprint' => $fingerprint,
                'sessionId' => (string) $session['sessionId'],
            ]);
            $this->entityManager->flush();
        }

        return $session;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function issueSession(array $request): array
    {
        $now = new \DateTimeImmutable();
        $session = [
            'profile' => (string) $request['profile'],
            'subscriberCode' => (string) $request['subscriberCode'],
            'mode' => (string) $request['mode'],
            'sessionId' => bin2hex(random_bytes(18)),
            'requestId' => (string) $request['requestId'],
            'fingerprint' => (string) ($request['fingerprint'] ?? ''),
            'platform' => (string) ($request['platform'] ?? ''),
            'arch' => (string) ($request['arch'] ?? ''),
            'issuedAt' => $now->format(DATE_ATOM),
            'expiresAt' => $now->modify('+2 hours')->format(DATE_ATOM),
        ];
        $proof = $this->sign($session);
        $this->registerActivationIssued($session);

        $artifacts = $this->artifactContract($session);

        return $session + $artifacts + [
            'activationProof' => $proof,
        ];
    }

    private function sendCode(string $email, string $code, string $subscriberCode, string $profile): void
    {
        $message = (new Email())
            ->from($this->readEnv('APP_INSTALLER_ACTIVATION_FROM') ?: 'no-reply@localhost')
            ->to($email)
            ->subject('Codigo de instalacao - Construtor PG')
            ->text("Codigo do assinante: {$subscriberCode}\nPerfil: {$profile}\nCodigo de confirmacao: {$code}\nValidade: 15 minutos\n");
        $this->mailer->send($message);
    }

    /**
     * @return array<string, string>
     */
    private function resolveSubscriber(string $subscriberCode, string $profile, string $mode, string $fingerprint = ''): array
    {
        $license = $this->findLicense($subscriberCode);
        if ($license !== null) {
            $this->assertLicenseAllows($license, $profile, $mode, $fingerprint);

            return [
                'email' => $license->getActivationEmail(),
                'name' => $license->getSubscriberName(),
                'source' => 'database',
            ];
        }

        $subscribers = json_decode($this->readEnv('APP_INSTALLER_ACTIVATION_SUBSCRIBERS') ?: '{}', true);
        if (!is_array($subscribers) || !isset($subscribers[$subscriberCode]) || !is_array($subscribers[$subscriberCode])) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_SUBSCRIBER_NOT_FOUND', 'Codigo do assinante nao encontrado na central.', 404);
        }
        $email = $this->text($subscribers[$subscriberCode]['email'] ?? '');
        if ($email === '') {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_EMAIL_NOT_CONFIGURED', 'Assinante sem e-mail cadastrado para ativacao.', 422);
        }

        return ['email' => $email, 'source' => 'env'];
    }

    private function findLicense(string $subscriberCode): ?InstallerActivationLicense
    {
        try {
            if (!$this->tableExists('installer_activation_license')) {
                return null;
            }

            return $this->licenses->findOneBySubscriberCode($subscriberCode);
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertLicenseAllows(InstallerActivationLicense $license, string $profile, string $mode, string $fingerprint): void
    {
        if ($license->getStatus() !== 'active') {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_LICENSE_INACTIVE', 'Licenca de instalacao inativa.', 422);
        }
        if ($license->getActivationEmail() === '') {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_EMAIL_NOT_CONFIGURED', 'Assinante sem e-mail cadastrado para ativacao.', 422);
        }
        if ($license->getExpiresAt() !== null && $license->getExpiresAt() <= new \DateTimeImmutable()) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_LICENSE_EXPIRED', 'Licenca de instalacao expirada.', 422);
        }
        if (!in_array($profile, $license->getAllowedProfiles(), true)) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_PROFILE_NOT_ALLOWED', 'Perfil de instalador nao autorizado para este assinante.', 422);
        }
        if (!in_array($mode, $license->getAllowedModes(), true)) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_MODE_NOT_ALLOWED', 'Modo de instalacao nao autorizado para este assinante.', 422);
        }
        if ($license->getMaxActivations() > 0 && $license->getActivationCount() >= $license->getMaxActivations()) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_LIMIT_REACHED', 'Limite de ativacoes da licenca atingido.', 422);
        }
        $metadata = $license->getMetadata();
        $revoked = is_array($metadata['revokedFingerprints'] ?? null) ? $metadata['revokedFingerprints'] : [];
        if ($fingerprint !== '' && in_array($fingerprint, $revoked, true)) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_FINGERPRINT_REVOKED', 'Este host foi revogado para a licenca.', 422);
        }
        $allowed = is_array($metadata['allowedFingerprints'] ?? null) ? $metadata['allowedFingerprints'] : [];
        if ($fingerprint !== '' && $allowed !== [] && !in_array($fingerprint, $allowed, true)) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_FINGERPRINT_NOT_ALLOWED', 'Este host nao esta autorizado para a licenca.', 422);
        }
        $maxHosts = (int) ($metadata['maxHosts'] ?? 0);
        $known = is_array($metadata['fingerprints'] ?? null) ? $metadata['fingerprints'] : [];
        if ($fingerprint !== '' && $maxHosts > 0 && !array_key_exists($fingerprint, $known) && count($known) >= $maxHosts) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_HOST_LIMIT_REACHED', 'Limite de hosts distintos da licenca atingido.', 422);
        }
    }

    /**
     * @param array<string, string> $session
     */
    private function registerActivationIssued(array $session): void
    {
        $license = $this->findLicense((string) $session['subscriberCode']);
        if ($license === null) {
            return;
        }

        $metadata = $license->getMetadata();
        $history = is_array($metadata['activationHistory'] ?? null) ? $metadata['activationHistory'] : [];
        $history[] = [
            'sessionId' => (string) $session['sessionId'],
            'requestId' => (string) $session['requestId'],
            'profile' => (string) $session['profile'],
            'mode' => (string) $session['mode'],
            'fingerprint' => (string) ($session['fingerprint'] ?? ''),
            'platform' => (string) ($session['platform'] ?? ''),
            'arch' => (string) ($session['arch'] ?? ''),
            'issuedAt' => (string) $session['issuedAt'],
            'expiresAt' => (string) $session['expiresAt'],
        ];
        $metadata['activationHistory'] = array_slice($history, -20);
        $fingerprint = (string) ($session['fingerprint'] ?? '');
        if ($fingerprint !== '') {
            $fingerprints = is_array($metadata['fingerprints'] ?? null) ? $metadata['fingerprints'] : [];
            $fingerprintEntry = is_array($fingerprints[$fingerprint] ?? null) ? $fingerprints[$fingerprint] : ['count' => 0];
            $fingerprintEntry['count'] = (int) ($fingerprintEntry['count'] ?? 0) + 1;
            $fingerprintEntry['lastSessionId'] = (string) $session['sessionId'];
            $fingerprintEntry['lastActivatedAt'] = (string) $session['issuedAt'];
            $fingerprints[$fingerprint] = $fingerprintEntry;
            $metadata['fingerprints'] = $fingerprints;
        }
        $license->setMetadata($metadata)->incrementActivationCount();
        $this->entityManager->flush();
    }

    private function assertServiceTokenAllows(string $provided, string $profile, string $mode, string $subscriberCode, string $fingerprint): ?InstallerActivationServiceToken
    {
        if ($provided === '') {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_SERVICE_UNAUTHORIZED', 'Token interno de ativacao invalido.', 401);
        }
        $token = $this->findServiceToken($provided);
        if ($token !== null) {
            if ($token->getExpiresAt() !== null && $token->getExpiresAt() <= new \DateTimeImmutable()) {
                throw new RuntimeHttpException('INSTALLER_ACTIVATION_SERVICE_TOKEN_EXPIRED', 'Token interno expirado.', 401);
            }
            if (!in_array($profile, $token->getAllowedProfiles(), true) || !in_array($mode, $token->getAllowedModes(), true)) {
                throw new RuntimeHttpException('INSTALLER_ACTIVATION_SERVICE_SCOPE_DENIED', 'Token interno nao autorizado para este perfil ou modo.', 403);
            }
            $metadata = $token->getMetadata();
            $allowedSubscribers = is_array($metadata['allowedSubscribers'] ?? null) ? $metadata['allowedSubscribers'] : [];
            if ($allowedSubscribers !== [] && !in_array($subscriberCode, $allowedSubscribers, true)) {
                throw new RuntimeHttpException('INSTALLER_ACTIVATION_SERVICE_SUBSCRIBER_DENIED', 'Token interno nao autorizado para este assinante.', 403);
            }
            $revokedFingerprints = is_array($metadata['revokedFingerprints'] ?? null) ? $metadata['revokedFingerprints'] : [];
            if ($fingerprint !== '' && in_array($fingerprint, $revokedFingerprints, true)) {
                throw new RuntimeHttpException('INSTALLER_ACTIVATION_SERVICE_FINGERPRINT_REVOKED', 'Host revogado para este token interno.', 403);
            }

            return $token;
        }

        $expected = $this->readEnv('APP_INSTALLER_ACTIVATION_SERVICE_TOKEN');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_SERVICE_UNAUTHORIZED', 'Token interno de ativacao invalido.', 401);
        }

        return null;
    }

    private function findServiceToken(string $provided): ?InstallerActivationServiceToken
    {
        try {
            if (!$this->tableExists('installer_activation_service_token')) {
                return null;
            }
            foreach ($this->serviceTokens->findActiveCandidates() as $token) {
                $hash = $token->getTokenHash();
                if ($hash !== '' && (password_verify($provided, $hash) || hash_equals($hash, hash('sha256', $provided)))) {
                    return $token;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param array<string, string> $session
     *
     * @return array<string, mixed>
     */
    private function artifactContract(array $session): array
    {
        $artifacts = [
            'manifestUrl' => $this->readEnv('APP_INSTALLER_MANIFEST_URL'),
            'dockerComposeUrl' => $this->readEnv('APP_INSTALLER_DOCKER_COMPOSE_URL'),
            'packageUrl' => $this->readEnv('APP_INSTALLER_PACKAGE_URL'),
        ];
        $key = $this->readEnv('APP_INSTALLER_ARTIFACT_SIGNING_KEY') ?: $this->readEnv('APP_INSTALLER_ACTIVATION_SIGNING_KEY');
        if ($key === '') {
            return $artifacts + ['artifactSignatureAlgorithm' => 'none'];
        }

        $signatures = [];
        foreach ($artifacts as $name => $url) {
            if ($url === '') {
                continue;
            }
            $payload = [
                'name' => $name,
                'url' => $url,
                'profile' => (string) $session['profile'],
                'subscriberCode' => (string) $session['subscriberCode'],
                'mode' => (string) $session['mode'],
                'sessionId' => (string) $session['sessionId'],
                'expiresAt' => (string) $session['expiresAt'],
            ];
            $signatures[$name] = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '', $key);
        }

        return $artifacts + [
            'artifactSignatureAlgorithm' => 'hmac-sha256',
            'artifactSignatures' => $signatures,
        ];
    }

    private function tableExists(string $tableName): bool
    {
        try {
            return $this->registry->getConnection()->createSchemaManager()->tablesExist([$tableName]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readRequest(string $requestId): array
    {
        if ($requestId === '' || !preg_match('/^[a-f0-9]{36}$/', $requestId)) {
            return [];
        }
        $path = $this->requestDir() . '/' . $requestId . '.json';
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $request
     */
    private function writeRequest(string $requestId, array $request): void
    {
        $dir = $this->requestDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($dir . '/' . $requestId . '.json', json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function requestDir(): string
    {
        return $this->kernel->getProjectDir() . '/var/installer-activation';
    }

    /**
     * @param array<string, string> $payload
     */
    private function sign(array $payload): string
    {
        $key = $this->readEnv('APP_INSTALLER_ACTIVATION_SIGNING_KEY') ?: $this->readEnv('APP_INSTALLATION_SESSION_SIGNING_KEY');
        if ($key === '') {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_SIGNING_KEY_REQUIRED', 'Configure a chave de assinatura da ativacao.', 500);
        }
        $payloadPart = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: ''), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payloadPart, $key, true)), '+/', '-_'), '=');

        return $payloadPart . '.' . $signature;
    }

    private function profile(string $profile): string
    {
        $profile = $this->text($profile);
        if (!in_array($profile, [InstallationActivationService::PROFILE_SYSTEM_BUILDER, InstallationActivationService::PROFILE_SUBSCRIBER], true)) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_PROFILE_INVALID', 'Perfil de instalador invalido.', 422);
        }

        return $profile;
    }

    private function subscriberCode(string $subscriberCode): string
    {
        $subscriberCode = $this->text($subscriberCode);
        if ($subscriberCode === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{1,60}$/i', $subscriberCode)) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_SUBSCRIBER_INVALID', 'Codigo do assinante invalido.', 422);
        }

        return $subscriberCode;
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($domain === '') {
            return '***';
        }

        return mb_substr($user, 0, 1) . '***@' . $domain;
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
