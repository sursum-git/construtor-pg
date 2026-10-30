<?php
namespace App\Tests\Ciw;
use App\Ciw\BancoCentralSgsClient;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;

final class BancoCentralSgsClientTest extends TestCase
{
    private function client(int $status, string $body): BancoCentralSgsClient
    {
        $client = $this->getMockBuilder(BancoCentralSgsClient::class)->onlyMethods(['request'])->getMock();
        $client->expects(self::once())->method('request')->willReturn(['status'=>$status,'body'=>$body]);
        return $client;
    }
    public function testLatestQuoteUsesObservationDateAndFiveDecimalPrecision(): void
    {
        $quote = $this->client(200, '[{"data":"20/10/2026","valor":"5.12345"}]')->fetchLatestDollar();
        self::assertSame(1, $quote['serie']);
        self::assertSame('2026-10-20', $quote['data']);
        self::assertSame(5.12345, $quote['valor']);
        self::assertSame('Dólar americano (venda) - diário', $quote['nome_serie']);
        self::assertSame(['serie','data','valor','nome_serie'], array_keys($quote));
    }
    public function testInvalidOrUnavailableResponsesCannotBecomeQuotes(): void
    {
        foreach ([[503,'[]'],[302,'[]'],[200,'<html>indisponivel</html>'],[200,'[]'],[200,'{}'],
            [200,'[{"data":"30/02/2026","valor":"5"}]'],[200,'[{"data":"01/01/2099","valor":"5"}]'],
            [200,'[{"data":"20/10/2026","valor":"0"}]'],[200,'[{"data":"20/10/2026","valor":"-1"}]'],
            [200,'[{"data":"20/10/2026","valor":"5.123456"}]'],[200,'[{"data":"20/10/2026","valor":"1e2"}]'],
            [200,'[{"data":"20/10/2026","valor":5}]'],[200,'[{"data":"20/10/2026","valor":"5","url":"evil"}]'],
            [200,'[{"data":"20/10/2026","valor":"5"},{"data":"21/10/2026","valor":"6"}]']] as [$status,$body]) {
            try { $this->client($status,$body)->fetchLatestDollar(); self::fail('Resposta invalida aceita'); }
            catch (RuntimeHttpException $e) { self::assertStringStartsWith('BCB_', $e->getErrorCode()); }
        }
    }
}
