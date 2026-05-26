<?php

namespace App\Tests\Install;

use App\Install\InstallationActivationService;
use PHPUnit\Framework\TestCase;

class InstallationActivationServiceTest extends TestCase
{
    private string $sessionFile;

    protected function setUp(): void
    {
        $this->sessionFile = sys_get_temp_dir() . '/construtor-install-session-' . bin2hex(random_bytes(4)) . '.json';
        $this->setEnv('APP_INSTALLATION_SESSION_FILE', $this->sessionFile);
        $this->setEnv('APP_INSTALLATION_SESSION_SIGNING_KEY', 'test-key');
        $this->setEnv('APP_INSTALLATION_SESSION_REQUIRED', '1');
    }

    protected function tearDown(): void
    {
        if (is_file($this->sessionFile)) {
            unlink($this->sessionFile);
        }
        $this->setEnv('APP_INSTALLATION_SESSION_FILE', '');
        $this->setEnv('APP_INSTALLATION_SESSION_SIGNING_KEY', '');
        $this->setEnv('APP_INSTALLATION_SESSION_REQUIRED', '');
    }

    public function testValidActivationSessionIsAccepted(): void
    {
        $payload = [
            'profile' => 'subscriber',
            'subscriberCode' => 'cliente-a',
            'mode' => 'docker',
            'sessionId' => 'sess-1',
            'issuedAt' => (new \DateTimeImmutable('-1 minute'))->format(DATE_ATOM),
            'expiresAt' => (new \DateTimeImmutable('+1 hour'))->format(DATE_ATOM),
        ];
        $this->writeSession($payload);

        $status = (new InstallationActivationService())->status();

        self::assertTrue($status['valid']);
        self::assertSame('subscriber', $status['profile']);
        self::assertSame('cliente-a', $status['subscriberCode']);
    }

    public function testInvalidSignatureBlocksActivation(): void
    {
        $payload = [
            'profile' => 'subscriber',
            'subscriberCode' => 'cliente-a',
            'mode' => 'docker',
            'sessionId' => 'sess-1',
            'issuedAt' => (new \DateTimeImmutable('-1 minute'))->format(DATE_ATOM),
            'expiresAt' => (new \DateTimeImmutable('+1 hour'))->format(DATE_ATOM),
        ];
        $this->writeSession($payload, 'other-key');

        $status = (new InstallationActivationService())->status();

        self::assertFalse($status['valid']);
        self::assertSame('Assinatura da ativacao invalida.', $status['message']);
    }

    public function testSubscriberInstallerCannotEnableCentralControl(): void
    {
        $payload = [
            'profile' => 'subscriber',
            'subscriberCode' => 'cliente-a',
            'mode' => 'docker',
            'sessionId' => 'sess-1',
            'issuedAt' => (new \DateTimeImmutable('-1 minute'))->format(DATE_ATOM),
            'expiresAt' => (new \DateTimeImmutable('+1 hour'))->format(DATE_ATOM),
        ];
        $this->writeSession($payload);

        $issues = (new InstallationActivationService())->blockingIssuesFor([
            'subscriberCode' => 'cliente-a',
            'systemRole' => 'saas_central',
            'centralControl' => true,
        ]);

        self::assertSame('activation_profile_mismatch', $issues[0]['code']);
    }

    /**
     * @param array<string, string> $payload
     */
    private function writeSession(array $payload, string $key = 'test-key'): void
    {
        $payloadPart = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: ''), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payloadPart, $key, true)), '+/', '-_'), '=');
        file_put_contents($this->sessionFile, json_encode([
            'profile' => $payload['profile'],
            'subscriberCode' => $payload['subscriberCode'],
            'mode' => $payload['mode'],
            'sessionId' => $payload['sessionId'],
            'issuedAt' => $payload['issuedAt'],
            'expiresAt' => $payload['expiresAt'],
            'activationProof' => $payloadPart . '.' . $signature,
        ], JSON_PRETTY_PRINT));
    }

    private function setEnv(string $name, string $value): void
    {
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($value === '' ? $name : $name . '=' . $value);
    }
}
