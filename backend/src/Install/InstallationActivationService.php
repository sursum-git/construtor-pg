<?php

namespace App\Install;

class InstallationActivationService
{
    public const PROFILE_SYSTEM_BUILDER = 'system_builder';
    public const PROFILE_SUBSCRIBER = 'subscriber';

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $session = $this->readSession();
        $proof = $this->verifyProof((string) ($session['activationProof'] ?? ''));
        $expiresAt = $this->parseDate((string) ($proof['payload']['expiresAt'] ?? $session['expiresAt'] ?? ''));
        $expired = $expiresAt instanceof \DateTimeImmutable && $expiresAt <= new \DateTimeImmutable();
        $profile = $this->text($proof['payload']['profile'] ?? $session['profile'] ?? '');
        $subscriberCode = $this->text($proof['payload']['subscriberCode'] ?? $session['subscriberCode'] ?? '');
        $mode = $this->text($proof['payload']['mode'] ?? $session['mode'] ?? '');
        $required = $this->isRequired();

        $valid = !$required || (
            $session !== []
            && $proof['valid'] === true
            && !$expired
            && in_array($profile, [self::PROFILE_SYSTEM_BUILDER, self::PROFILE_SUBSCRIBER], true)
            && $subscriberCode !== ''
        );

        $message = 'Ativacao local validada.';
        if (!$required) {
            $message = 'Ativacao local nao obrigatoria neste ambiente.';
        } elseif ($session === []) {
            $message = 'Execute o instalador compilado para liberar esta instalacao.';
        } elseif ($proof['valid'] !== true) {
            $message = $proof['message'];
        } elseif ($expired) {
            $message = 'Sessao de ativacao expirada. Execute o instalador novamente.';
        } elseif ($profile === '' || $subscriberCode === '') {
            $message = 'Sessao de ativacao incompleta.';
        }

        return [
            'required' => $required,
            'valid' => $valid,
            'profile' => $profile,
            'profileLabel' => $this->profileLabel($profile),
            'subscriberCode' => $subscriberCode,
            'mode' => $mode,
            'sessionId' => $this->text($proof['payload']['sessionId'] ?? $session['sessionId'] ?? ''),
            'issuedAt' => $this->text($proof['payload']['issuedAt'] ?? $session['issuedAt'] ?? ''),
            'expiresAt' => $expiresAt ? $expiresAt->format(DATE_ATOM) : '',
            'proofHash' => $this->proofHash((string) ($session['activationProof'] ?? '')),
            'message' => $message,
            'sessionFile' => $this->sessionFile(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{code: string, message: string}>
     */
    public function blockingIssuesFor(array $payload): array
    {
        $status = $this->status();
        if (($status['valid'] ?? false) !== true) {
            return [[
                'code' => 'activation_session',
                'message' => (string) ($status['message'] ?? 'Ativacao local obrigatoria.'),
            ]];
        }
        if (($status['required'] ?? false) !== true) {
            return [];
        }

        $issues = [];
        $profile = (string) ($status['profile'] ?? '');
        $subscriberCode = (string) ($status['subscriberCode'] ?? '');
        $payloadSubscriber = $this->text($payload['subscriberCode'] ?? '');
        $systemRole = $this->text($payload['systemRole'] ?? '');
        $centralControl = $this->bool($payload['centralControl'] ?? false);

        if ($payloadSubscriber !== '' && !hash_equals($subscriberCode, $payloadSubscriber)) {
            $issues[] = [
                'code' => 'activation_subscriber_mismatch',
                'message' => 'O codigo do assinante precisa ser o mesmo liberado pelo instalador compilado.',
            ];
        }
        if ($profile === self::PROFILE_SUBSCRIBER && ($systemRole === 'saas_central' || $centralControl)) {
            $issues[] = [
                'code' => 'activation_profile_mismatch',
                'message' => 'O instalador de Assinante nao libera instalacao como Construtor de Sistemas.',
            ];
        }
        if ($profile === self::PROFILE_SYSTEM_BUILDER && $systemRole !== 'saas_central') {
            $issues[] = [
                'code' => 'activation_profile_mismatch',
                'message' => 'O instalador do Construtor exige perfil de sistema Central SaaS.',
            ];
        }

        return $issues;
    }

    /**
     * @return array<string, string>
     */
    public function envValues(): array
    {
        $status = $this->status();
        if (($status['valid'] ?? false) !== true) {
            return [];
        }

        return [
            'APP_INSTALLATION_TYPE' => (string) $status['profile'],
            'APP_ACTIVATION_SUBSCRIBER_CODE' => (string) $status['subscriberCode'],
            'APP_ACTIVATION_PROOF_HASH' => (string) $status['proofHash'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readSession(): array
    {
        $path = $this->sessionFile();
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{valid: bool, message: string, payload: array<string, mixed>}
     */
    private function verifyProof(string $proof): array
    {
        $key = $this->signingKey();
        if ($proof === '') {
            return ['valid' => false, 'message' => 'Comprovante de ativacao ausente.', 'payload' => []];
        }
        if ($key === '') {
            return ['valid' => false, 'message' => 'APP_INSTALLATION_SESSION_SIGNING_KEY nao configurada.', 'payload' => []];
        }
        $parts = explode('.', $proof);
        if (count($parts) !== 2) {
            return ['valid' => false, 'message' => 'Comprovante de ativacao em formato invalido.', 'payload' => []];
        }
        [$payloadPart, $signaturePart] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $payloadPart, $key, true));
        if (!hash_equals($expected, $signaturePart)) {
            return ['valid' => false, 'message' => 'Assinatura da ativacao invalida.', 'payload' => []];
        }
        $payloadJson = $this->base64UrlDecode($payloadPart);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return ['valid' => false, 'message' => 'Payload da ativacao invalido.', 'payload' => []];
        }

        return ['valid' => true, 'message' => 'Comprovante de ativacao valido.', 'payload' => $payload];
    }

    private function sessionFile(): string
    {
        $configured = $this->readEnv('APP_INSTALLATION_SESSION_FILE');
        if ($configured !== '') {
            return $configured;
        }

        return dirname(__DIR__, 2) . '/var/install/activation-session.json';
    }

    private function signingKey(): string
    {
        return $this->readEnv('APP_INSTALLATION_SESSION_SIGNING_KEY');
    }

    private function isRequired(): bool
    {
        $value = strtolower($this->readEnv('APP_INSTALLATION_SESSION_REQUIRED'));
        if ($value === '') {
            return true;
        }

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function profileLabel(string $profile): string
    {
        return match ($profile) {
            self::PROFILE_SYSTEM_BUILDER => 'Construtor de Sistemas',
            self::PROFILE_SUBSCRIBER => 'Assinante',
            default => $profile !== '' ? $profile : 'Nao liberado',
        };
    }

    private function proofHash(string $proof): string
    {
        return $proof !== '' ? hash('sha256', $proof) : '';
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function readEnv(string $name): string
    {
        return trim((string) ($_SERVER[$name] ?? $_ENV[$name] ?? getenv($name) ?: ''));
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padded = str_pad(strtr($value, '-_', '+/'), strlen($value) % 4 === 0 ? strlen($value) : strlen($value) + 4 - strlen($value) % 4, '=', STR_PAD_RIGHT);

        return (string) base64_decode($padded, true);
    }
}
