<?php
namespace App\Ciw;

use App\Runtime\RuntimeHttpException;

/** Cliente reutilizavel: consulta somente o SGS oficial, sem persistencia ou cache. */
class BancoCentralSgsClient
{
    public const DOLLAR_URL = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.1/dados/ultimos/1?formato=json';

    public function fetchLatestDollar(): array
    {
        $response = $this->request();
        if ($response['status'] !== 200) {
            throw new RuntimeHttpException('BCB_UNAVAILABLE', 'O Banco Central não respondeu à consulta. Tente novamente mais tarde.', 502);
        }
        try { $rows = json_decode($response['body'], true, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { $rows = null; }
        $row = is_array($rows) && array_is_list($rows) && count($rows) === 1 ? $rows[0] : null;
        if (!is_array($row) || count($row) !== 2 || !is_string($row['data'] ?? null) || !is_string($row['valor'] ?? null)) {
            $this->invalidResponse();
        }
        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $row['data'], new \DateTimeZone('America/Sao_Paulo'));
        if (!$date || $date->format('d/m/Y') !== $row['data'] || $date > new \DateTimeImmutable('today', new \DateTimeZone('America/Sao_Paulo'))
            || !preg_match('/\A[0-9]{1,10}(?:\.[0-9]{1,5})?\z/', $row['valor']) || (float) $row['valor'] <= 0) {
            $this->invalidResponse();
        }
        return ['serie'=>1, 'data'=>$date->format('Y-m-d'), 'valor'=>(float) $row['valor'], 'nome_serie'=>'Dólar americano (venda) - diário'];
    }

    protected function request(): array
    {
        $handle = curl_init(self::DOLLAR_URL);
        if ($handle === false) { throw new RuntimeHttpException('BCB_UNAVAILABLE', 'Não foi possível iniciar a consulta ao Banco Central.', 502); }
        $body = '';
        try {
            // OpenSSL no PHP Windows pode nao ter curl.cainfo; usar as CAs do SO.
            if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
                curl_setopt($handle, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
            }
            curl_setopt_array($handle, [
                CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>10,
                CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_HTTPHEADER=>['Accept: application/json'],
                CURLOPT_USERAGENT=>'Construtor-PG/CIW-SGS',
                CURLOPT_WRITEFUNCTION=>static function ($curl, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 65536) { return 0; }
                    $body .= $chunk; return strlen($chunk);
                },
            ]);
            if (curl_exec($handle) === false) {
                throw new RuntimeHttpException('BCB_UNAVAILABLE', 'Falha de comunicação com o Banco Central. Nenhuma cotação foi gravada.', 502);
            }
            return ['status'=>(int) curl_getinfo($handle, CURLINFO_HTTP_CODE), 'body'=>$body];
        } finally { curl_close($handle); }
    }

    private function invalidResponse(): never
    {
        throw new RuntimeHttpException('BCB_RESPONSE_INVALID', 'O Banco Central retornou uma cotação inválida. Nenhuma cotação foi gravada.', 502);
    }
}
