<?php

namespace App\Install;

use App\Runtime\RuntimeHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class InstallerActivationCenterService
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly MailerInterface $mailer,
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
        $subscriber = $this->resolveSubscriber($subscriberCode);
        $requestId = bin2hex(random_bytes(18));
        $code = (string) random_int(100000, 999999);
        $request = [
            'requestId' => $requestId,
            'codeHash' => password_hash($code, PASSWORD_DEFAULT),
            'profile' => $profile,
            'subscriberCode' => $subscriberCode,
            'mode' => $this->text($payload['mode'] ?? 'docker'),
            'fingerprint' => $this->text($payload['fingerprint'] ?? ''),
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
        if (!password_verify($code, (string) $request['codeHash'])) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_CODE_INVALID', 'Codigo de ativacao invalido.', 422);
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
        $expected = $this->readEnv('APP_INSTALLER_ACTIVATION_SERVICE_TOKEN');
        $provided = preg_replace('/^Bearer\s+/i', '', trim($authorization));
        if ($expected === '' || !hash_equals($expected, (string) $provided)) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_SERVICE_UNAUTHORIZED', 'Token interno de ativacao invalido.', 401);
        }

        $profile = $this->profile((string) ($payload['profile'] ?? ''));
        $subscriberCode = $this->subscriberCode((string) ($payload['subscriberCode'] ?? ''));
        $this->resolveSubscriber($subscriberCode);

        return $this->issueSession([
            'requestId' => 'service-' . bin2hex(random_bytes(12)),
            'profile' => $profile,
            'subscriberCode' => $subscriberCode,
            'mode' => $this->text($payload['mode'] ?? 'saas-docker'),
            'fingerprint' => $this->text($payload['fingerprint'] ?? ''),
            'platform' => $this->text($payload['platform'] ?? ''),
            'arch' => $this->text($payload['arch'] ?? ''),
        ]);
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

        return $session + [
            'activationProof' => $proof,
            'manifestUrl' => $this->readEnv('APP_INSTALLER_MANIFEST_URL'),
            'dockerComposeUrl' => $this->readEnv('APP_INSTALLER_DOCKER_COMPOSE_URL'),
            'packageUrl' => $this->readEnv('APP_INSTALLER_PACKAGE_URL'),
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
    private function resolveSubscriber(string $subscriberCode): array
    {
        $subscribers = json_decode($this->readEnv('APP_INSTALLER_ACTIVATION_SUBSCRIBERS') ?: '{}', true);
        if (!is_array($subscribers) || !isset($subscribers[$subscriberCode]) || !is_array($subscribers[$subscriberCode])) {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_SUBSCRIBER_NOT_FOUND', 'Codigo do assinante nao encontrado na central.', 404);
        }
        $email = $this->text($subscribers[$subscriberCode]['email'] ?? '');
        if ($email === '') {
            throw new RuntimeHttpException('INSTALLER_ACTIVATION_EMAIL_NOT_CONFIGURED', 'Assinante sem e-mail cadastrado para ativacao.', 422);
        }

        return ['email' => $email];
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
        return trim((string) ($_SERVER[$name] ?? $_ENV[$name] ?? getenv($name) ?: ''));
    }
}
