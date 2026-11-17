<?php

declare(strict_types=1);

namespace App\Runtime;

final class RuntimeContainerNfLinkParameters
{
    public const FIELDS = ['nfs', 'expected_nf_stamp'];

    private const TEXT_LENGTHS = [
        'documento' => 60,
        'serie' => 20,
        'estab' => 20,
        'nat_operacao' => 60,
    ];

    public static function isOperation(array $operation): bool
    {
        $expectedAction = match ($operation['code'] ?? null) {
            'associar_nfs' => 'associar',
            'desassociar_nfs' => 'desassociar',
            default => null,
        };
        if ($expectedAction === null) {
            return false;
        }

        $path = parse_url((string) ($operation['path'] ?? ''), PHP_URL_PATH);
        $body = $operation['bodyTemplate'] ?? null;
        return in_array($path, [
            '/SursumPrograms/containers-processos',
            '/med/web/SursumPrograms/containers-processos',
        ], true)
            && ($operation['requestContextFields'] ?? null) === ['container_id']
            && is_array($body) && count($body) === 2
            && ($body['resource'] ?? null) === 'nfs'
            && ($body['operation'] ?? null) === $expectedAction;
    }

    public static function normalize(array $parameters): array
    {
        $stamp = $parameters['expected_nf_stamp'] ?? null;
        $notes = $parameters['nfs'] ?? null;
        if (count($parameters) !== 2 || !is_string($stamp) || preg_match('/\A[0-9a-f]{64}\z/i', $stamp) !== 1) {
            self::invalid('Atualize a consulta das notas vinculadas antes de continuar.');
        }
        if (!is_array($notes) || !array_is_list($notes) || count($notes) < 1 || count($notes) > 100) {
            self::invalid('Selecione de 1 a 100 notas fiscais.');
        }

        $normalized = [];
        $identities = [];
        foreach ($notes as $note) {
            if (!is_array($note) || count($note) !== 5) {
                self::invalid('Informe a chave completa de cada nota fiscal.');
            }
            $key = [];
            foreach (self::TEXT_LENGTHS as $field => $limit) {
                $value = $note[$field] ?? null;
                if (!is_string($value) || trim($value) === '' || mb_strlen($value, 'UTF-8') > $limit ||
                    preg_match('/[\x00-\x1f\x7f]/', $value) !== 0) {
                    self::invalid('Campo inválido na chave da nota: ' . $field);
                }
                $key[$field] = $value;
            }
            $issuer = $note['cod_emitente'] ?? null;
            if (!is_int($issuer) || $issuer < 1 || $issuer > 2147483647) {
                self::invalid('Código de emitente inválido.');
            }
            $key['cod_emitente'] = $issuer;
            $identity = serialize($key);
            if (isset($identities[$identity])) {
                self::invalid('A seleção contém uma nota repetida.');
            }
            $identities[$identity] = true;
            $normalized[] = $key;
        }

        return ['nfs' => $normalized, 'expected_nf_stamp' => $stamp];
    }

    private static function invalid(string $message): never
    {
        throw new RuntimeHttpException('CIW_NF_LINK_PARAMETERS_INVALID', $message, 422);
    }
}
