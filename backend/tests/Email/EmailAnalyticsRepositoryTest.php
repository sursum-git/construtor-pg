<?php

namespace App\Tests\Email;

use App\Email\EmailAnalyticsRepository;
use PHPUnit\Framework\TestCase;

final class EmailAnalyticsRepositoryTest extends TestCase
{
    public function testNormalizesSesEventFromJsonBeforeLegacyCode(): void
    {
        self::assertSame('DELIVERY', EmailAnalyticsRepository::normalizeEvent([
            'cod_tipo' => 'send',
            'json_evento' => '{"eventType":"Delivery"}',
        ]));
    }

    public function testGroupsFailureEventsInSummary(): void
    {
        $repository = new EmailAnalyticsRepository(static fn (): array => [
            ['cod_tipo' => 'SEND', 'json_evento' => null],
            ['cod_tipo' => 'DELIVERY', 'json_evento' => null],
            ['cod_tipo' => 'BOUNCE', 'json_evento' => null],
            ['cod_tipo' => 'REJECT', 'json_evento' => null],
            ['cod_tipo' => 'Rendering Failure', 'json_evento' => null],
        ]);

        self::assertSame(['sent' => 5, 'delivery' => 1, 'failures' => 3, 'delay' => 0, 'complaint' => 0, 'open' => 0, 'click' => 0], $repository->summary());
    }

    public function testRejectsSortOutsideThePublishedColumns(): void
    {
        $repository = new EmailAnalyticsRepository(static fn (): array => []);

        $this->expectException(\InvalidArgumentException::class);
        $repository->search(['sort' => 'json_evento; drop table notificacoes']);
    }

    public function testExtractsFailureReasonFromSnsSesBounce(): void
    {
        $detail = EmailAnalyticsRepository::eventDetail([
            'json_evento' => json_encode(['Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => ['bounceType' => 'Permanent', 'bounceSubType' => 'General'],
            ])]),
        ]);

        self::assertSame('Falha na entrega', $detail['status']);
        self::assertSame('Permanent / General', $detail['reason']);
        self::assertSame('Permanente — tratar cadastro', $detail['classification']);
    }
}
