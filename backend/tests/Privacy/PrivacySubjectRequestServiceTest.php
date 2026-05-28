<?php

namespace App\Tests\Privacy;

use App\Privacy\PrivacySubjectRequestService;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEventService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeTransactionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

class PrivacySubjectRequestServiceTest extends TestCase
{
    public function testPrepareManualRequestAddsProtocolDefaultsAndPriority(): void
    {
        $service = $this->service();

        $values = $service->prepareSubjectRequestValues([
            'source_channel' => 'email',
            'requester_email' => 'TITULAR@EXAMPLE.COM',
            'request_type' => 'anonymization',
        ], true);

        self::assertStringStartsWith('LGPD-', $values['protocol']);
        self::assertSame('default', $values['tenant_id']);
        self::assertSame('email', $values['source_channel']);
        self::assertSame('titular@example.com', $values['requester_email']);
        self::assertSame('anonymization', $values['request_type']);
        self::assertSame('pending', $values['status']);
        self::assertSame('high', $values['priority']);
        self::assertSame('{}', $values['analysis_result']);
        self::assertJson($values['evidence']);
    }

    public function testPrepareRejectsInvalidEmail(): void
    {
        $service = $this->service();

        $this->expectException(RuntimeHttpException::class);
        $service->prepareSubjectRequestValues([
            'requester_email' => 'sem-email',
            'request_type' => 'access',
        ], true);
    }

    public function testPrepareRejectsInvalidRequestType(): void
    {
        $service = $this->service();

        $this->expectException(RuntimeHttpException::class);
        $service->prepareSubjectRequestValues([
            'requester_email' => 'titular@example.com',
            'request_type' => 'php_script',
        ], true);
    }

    private function service(): PrivacySubjectRequestService
    {
        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('default');
        $permissions->method('getUserId')->willReturn('admin');

        return new PrivacySubjectRequestService(
            $this->createStub(Connection::class),
            $this->createStub(MailerInterface::class),
            $this->createStub(RuntimeNotificationService::class),
            $this->createStub(RuntimeEventService::class),
            $this->createStub(RuntimeTransactionService::class),
            $permissions,
        );
    }
}
