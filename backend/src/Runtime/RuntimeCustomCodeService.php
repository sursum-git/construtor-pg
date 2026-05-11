<?php

namespace App\Runtime;

use Doctrine\DBAL\Connection;

class RuntimeCustomCodeService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function applyCreateValues(array $definition, array $values, array $payload): array
    {
        foreach ($definition['fields'] as $fieldCode => $fieldConfig) {
            $customCode = is_array($fieldConfig['customCode'] ?? null) ? $fieldConfig['customCode'] : null;
            if (!$customCode) {
                continue;
            }
            if (array_key_exists($fieldCode, $values) && trim((string) $values[$fieldCode]) !== '') {
                continue;
            }

            $values[$fieldCode] = $this->generateCode($definition, $fieldCode, $fieldConfig, $values, $payload);
        }

        return $values;
    }

    private function generateCode(array $definition, string $fieldCode, array $fieldConfig, array $values, array $payload): string
    {
        $config = is_array($fieldConfig['customCode'] ?? null) ? $fieldConfig['customCode'] : [];
        $mode = (string) ($config['mode'] ?? 'pattern');
        $properties = $this->resolvePromptProperties($fieldCode, $config, $payload);
        $prefix = trim((string) ($config['prefix'] ?? ''));
        $sequence = null;
        $patternUsesSequence = $mode === 'pattern' && str_contains((string) ($config['pattern'] ?? ''), '{SEQ');
        if (($config['sequenceEnabled'] ?? false) === true || $patternUsesSequence) {
            $sequence = $this->nextSequenceState($definition, $fieldCode, $config, $values, $properties);
        }

        return match ($mode) {
            'static_method' => $this->generateByStaticMethod($definition, $fieldCode, $fieldConfig, $values, $payload, $config, $properties, $sequence),
            default => $prefix . $this->renderPattern(
                (string) ($config['pattern'] ?? '{YYYY}{MM}{DD}-{SEQ:4}'),
                $values,
                $properties,
                $sequence
            ),
        };
    }

    private function resolvePromptProperties(string $fieldCode, array $config, array $payload): array
    {
        $requested = is_array($payload['_customCode'][$fieldCode] ?? null) ? $payload['_customCode'][$fieldCode] : [];
        $properties = is_array($requested['properties'] ?? null) ? $requested['properties'] : [];
        $normalized = [];

        foreach (is_array($config['promptFields'] ?? null) ? $config['promptFields'] : [] as $promptField) {
            if (!is_array($promptField)) {
                continue;
            }
            $name = trim((string) ($promptField['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $value = $properties[$name] ?? null;
            if (($promptField['required'] ?? false) === true && ($value === null || trim((string) $value) === '')) {
                throw new RuntimeValidationException('CUSTOM_CODE_PROPERTIES_REQUIRED', 'Existem inconsistencias no formulario.', [
                    'status' => 'blocked',
                    'title' => 'Inconsistencias encontradas',
                    'messages' => [[
                        'field' => $fieldCode,
                        'type' => 'error',
                        'message' => 'Preencha as propriedades da codificacao de ' . (($promptField['label'] ?? $fieldCode) ?: $fieldCode) . '.',
                    ]],
                ]);
            }
            $normalized[$name] = $this->normalizePromptValue($promptField, $value);
        }

        return $normalized;
    }

    private function normalizePromptValue(array $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ((string) ($field['type'] ?? 'string')) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            default => trim((string) $value),
        };
    }

    private function nextSequenceState(array $definition, string $fieldCode, array $config, array $values, array $properties): array
    {
        $scopeKey = $this->buildSequenceScopeKey($config, $values, $properties);
        $padding = max(1, min(12, (int) ($config['sequencePadding'] ?? 4)));
        $current = (int) $this->connection->fetchOne(
            'INSERT INTO runtime_custom_code_sequence (entity_code, field_code, scope_key, next_value, created_at, updated_at)
             VALUES (:entityCode, :fieldCode, :scopeKey, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON CONFLICT (entity_code, field_code, scope_key)
             DO UPDATE SET next_value = runtime_custom_code_sequence.next_value + 1, updated_at = CURRENT_TIMESTAMP
             RETURNING next_value - 1',
            [
                'entityCode' => $definition['entityCode'],
                'fieldCode' => $fieldCode,
                'scopeKey' => $scopeKey,
            ]
        );

        return [
            'value' => $current,
            'padded' => str_pad((string) $current, $padding, '0', STR_PAD_LEFT),
            'scopeKey' => $scopeKey,
        ];
    }

    private function buildSequenceScopeKey(array $config, array $values, array $properties): string
    {
        $now = new \DateTimeImmutable();
        $scope = (string) ($config['sequenceScope'] ?? 'global');

        return match ($scope) {
            'year' => $now->format('Y'),
            'month' => $now->format('Ym'),
            'day' => $now->format('Ymd'),
            default => 'global',
        };
    }

    private function generateByStaticMethod(
        array $definition,
        string $fieldCode,
        array $fieldConfig,
        array $values,
        array $payload,
        array $config,
        array $properties,
        ?array $sequence
    ): string {
        $class = trim((string) ($config['staticClass'] ?? ''));
        $method = trim((string) ($config['staticMethod'] ?? ''));
        if ($class === '' || $method === '') {
            throw new RuntimeHttpException('CUSTOM_CODE_METHOD_NOT_CONFIGURED', 'Metodo estatico da codificacao customizada nao configurado.', 422, [
                'field' => $fieldCode,
            ]);
        }
        if (!str_starts_with($class, 'App\\Runtime\\CustomCode\\') || !class_exists($class) || !is_callable([$class, $method])) {
            throw new RuntimeHttpException('CUSTOM_CODE_METHOD_INVALID', 'Metodo estatico da codificacao customizada nao encontrado.', 422, [
                'field' => $fieldCode,
                'class' => $class,
                'method' => $method,
            ]);
        }

        $result = $class::$method([
            'definition' => $definition,
            'fieldCode' => $fieldCode,
            'field' => $fieldConfig,
            'values' => $values,
            'payload' => $payload,
            'properties' => $properties,
            'sequence' => $sequence,
            'now' => new \DateTimeImmutable(),
        ]);
        $code = trim((string) $result);
        if ($code === '') {
            throw new RuntimeHttpException('CUSTOM_CODE_METHOD_EMPTY', 'O metodo estatico da codificacao customizada retornou vazio.', 422, [
                'field' => $fieldCode,
                'class' => $class,
                'method' => $method,
            ]);
        }

        return $code;
    }

    private function renderPattern(string $pattern, array $values, array $properties, ?array $sequence): string
    {
        $now = new \DateTimeImmutable();

        return preg_replace_callback('/\{([A-Z_]+)(?::([^}]+))?\}/', function (array $matches) use ($now, $values, $properties, $sequence): string {
            $token = strtoupper((string) ($matches[1] ?? ''));
            $argument = trim((string) ($matches[2] ?? ''));

            return match ($token) {
                'YYYY' => $now->format('Y'),
                'YY' => $now->format('y'),
                'MM' => $now->format('m'),
                'DD' => $now->format('d'),
                'SEQ' => $this->resolveSequenceToken($argument, $sequence),
                'ENTITY' => $this->sanitizeCodeToken($values[$argument] ?? ''),
                'PROMPT' => $this->sanitizeCodeToken($properties[$argument] ?? ''),
                default => '',
            };
        }, $pattern) ?? $pattern;
    }

    private function resolveSequenceToken(string $argument, ?array $sequence): string
    {
        if (!$sequence) {
            return '';
        }
        if ($argument === '') {
            return (string) $sequence['padded'];
        }

        $padding = max(1, min(12, (int) $argument));
        return str_pad((string) ($sequence['value'] ?? 0), $padding, '0', STR_PAD_LEFT);
    }

    private function sanitizeCodeToken(mixed $value): string
    {
        $text = strtoupper(trim((string) $value));
        $text = preg_replace('/[^A-Z0-9]+/', '-', $text) ?: '';
        return trim($text, '-');
    }
}
