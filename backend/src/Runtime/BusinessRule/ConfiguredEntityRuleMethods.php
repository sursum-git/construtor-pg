<?php

namespace App\Runtime\BusinessRule;

use App\Runtime\RuntimeBusinessRuleContext;

final class ConfiguredEntityRuleMethods
{
    private const TITLE_KEY = 'validation.title.inconsistencies';
    private const MIN_LENGTH_MESSAGE_KEY = 'validation.message.field_min_length';

    private function resolveFieldLabel(RuntimeBusinessRuleContext $context, string $field): string
    {
        $definition = $context->getDefinition();
        $fields = is_array($definition['fields'] ?? null) ? $definition['fields'] : [];
        if (is_array($fields[$field] ?? null)) {
            $label = trim((string) ($fields[$field]['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return $field;
    }

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

        $fieldLabel = $this->resolveFieldLabel($context, $field);

        return [
            'valid' => false,
            'titleKey' => self::TITLE_KEY,
            'message' => 'O campo precisa atender ao tamanho minimo configurado.',
            'messages' => [[
                'field' => $field,
                'type' => 'error',
                'message' => (string) ($params['message'] ?? ('O campo ' . $fieldLabel . ' precisa ter ao menos ' . $minimum . ' caracteres.')),
                'messageKey' => empty($params['message']) ? self::MIN_LENGTH_MESSAGE_KEY : null,
                'messageParams' => empty($params['message']) ? [
                    'field' => $field,
                    'fieldCode' => $field,
                    'fieldLabel' => $fieldLabel,
                    'min' => $minimum,
                ] : [],
            ]],
            'metadata' => [
                'field' => $field,
                'minimum' => $minimum,
                'length' => mb_strlen($value),
            ],
        ];
    }
}
