<?php

namespace App\Runtime\BusinessRule;

use App\Runtime\RuntimeBusinessRuleContext;

final class ConfiguredEntityRuleMethods
{
    public function validateMinLength(RuntimeBusinessRuleContext $context, array $rule = []): array
    {
        $params = is_array($rule['params'] ?? null) ? $rule['params'] : [];
        $field = strtolower(trim((string) ($params['field'] ?? '')));
        $minimum = max(1, (int) ($params['min'] ?? 1));
        if ($field === '') {
            return ['valid' => true];
        }

        $values = array_merge($context->getBefore(), $context->getValues(), $context->getAfter());
        $value = trim((string) ($values[$field] ?? ''));
        if (mb_strlen($value) >= $minimum) {
            $context->log('entity.rule.detail', 'Regra de tamanho minimo validada.', metadata: [
                'field' => $field,
                'minimum' => $minimum,
                'length' => mb_strlen($value),
            ]);

            return [
                'valid' => true,
                'message' => 'Regra de tamanho minimo validada.',
                'metadata' => [
                    'field' => $field,
                    'minimum' => $minimum,
                ],
            ];
        }

        return [
            'valid' => false,
            'message' => 'O campo precisa atender ao tamanho minimo configurado.',
            'messages' => [[
                'field' => $field,
                'type' => 'error',
                'message' => (string) ($params['message'] ?? ('O campo ' . $field . ' precisa ter ao menos ' . $minimum . ' caracteres.')),
            ]],
            'metadata' => [
                'field' => $field,
                'minimum' => $minimum,
                'length' => mb_strlen($value),
            ],
        ];
    }
}
