<?php

namespace App\Runtime;

class RuntimeSituationService
{
    public function __construct(
        private readonly RuntimeTransactionService $transactions,
    ) {
    }

    public function applyCreateDefaults(array $definition, array $values): array
    {
        $situation = $this->situationConfig($definition);
        if (!$situation) {
            return $values;
        }

        $field = (string) $situation['field'];
        if (array_key_exists($field, $values) && $values[$field] !== null && $values[$field] !== '') {
            return $values;
        }

        $initial = $situation['initial'] ?? null;
        if (is_string($initial) && $initial !== '' && ($definition['fields'][$field]['writable'] ?? false)) {
            $values[$field] = $initial;
        }

        return $values;
    }

    public function validateCreate(array $definition, array $values, string $actionId): ?array
    {
        $situation = $this->situationConfig($definition);
        if (!$situation) {
            return null;
        }

        $field = (string) $situation['field'];
        $to = $this->normalizeCode($values[$field] ?? null);
        if ($to === null) {
            return null;
        }

        $this->assertKnownSituation($definition, $to);
        $transition = $this->findTransition($situation, null, $to, $actionId);
        if ($transition) {
            $this->validateTransitionGuards($definition, $transition, [], $values);
        }

        return $transition;
    }

    public function validateUpdate(array $definition, array $before, array $values, string $actionId): ?array
    {
        $situation = $this->situationConfig($definition);
        if (!$situation) {
            return null;
        }

        $field = (string) $situation['field'];
        if (!array_key_exists($field, $values)) {
            return null;
        }

        $from = $this->normalizeCode($before[$field] ?? null);
        $to = $this->normalizeCode($values[$field] ?? null);
        if ($to === null || $from === $to) {
            return null;
        }

        $this->assertKnownSituation($definition, $to);
        $transition = $this->findTransition($situation, $from, $to, $actionId);
        if (!$transition && count($situation['transitions'] ?? []) > 0) {
            throw new RuntimeValidationException('SITUATION_TRANSITION_NOT_ALLOWED', 'Transicao de situacao nao permitida.', [
                'status' => 'blocked',
                'title' => 'Situacao nao permitida',
                'titleKey' => 'validation.title.situation_not_allowed',
                'messages' => [[
                    'field' => $field,
                    'type' => 'error',
                    'message' => sprintf('Nao e permitido mudar a situacao de %s para %s nesta acao.', $from ?? '(vazio)', $to),
                    'messageKey' => 'validation.message.situation_transition_not_allowed',
                    'messageParams' => [
                        'field' => $field,
                        'fieldCode' => $field,
                        'from' => $from ?? '(vazio)',
                        'to' => $to,
                    ],
                ]],
            ], effects: [[
                'action' => 'showMessage',
                'type' => 'error',
                'message' => 'Transicao de situacao nao permitida.',
                'messageKey' => 'validation.message.situation_transition_blocked',
            ]]);
        }
        if ($transition) {
            $this->validateTransitionGuards($definition, $transition, $before, array_replace($before, $values));
        }

        return $transition;
    }

    public function logTransition(array $definition, ?array $transition, array $before, array $after): void
    {
        if (!$transition) {
            return;
        }

        $situation = $this->situationConfig($definition);
        $field = $situation ? (string) $situation['field'] : 'situacao';
        $this->transactions->log($definition['entityCode'] . '.situation.transition', 'Situacao alterada pelo runtime.', before: [
            $field => $before[$field] ?? null,
        ], after: [
            $field => $after[$field] ?? null,
        ], metadata: [
            'entityCode' => $definition['entityCode'],
            'from' => $transition['from'] ?? null,
            'to' => $transition['to'] ?? null,
            'actionId' => $transition['actionId'] ?? null,
            'label' => $transition['label'] ?? null,
        ]);
    }

    public function applyTransitionEffects(array $response, ?array $transition): array
    {
        if (!$transition || !is_array($transition['effects'] ?? null) || !$transition['effects']) {
            return $response;
        }

        $effects = is_array($response['effects'] ?? null) ? $response['effects'] : [];
        foreach ($transition['effects'] as $effect) {
            if (is_array($effect)) {
                $effects[] = $effect;
            }
        }
        $response['effects'] = $effects;

        return $response;
    }

    public function decorateRow(array $definition, array $row): array
    {
        $situation = $this->situationConfig($definition);
        if (!$situation) {
            return $row;
        }

        $field = (string) $situation['field'];
        $code = $this->normalizeCode($row[$field] ?? null);
        $item = $code !== null ? ($situation['situations'][$code] ?? null) : null;
        $runtime = is_array($row['_runtime'] ?? null) ? $row['_runtime'] : [];
        $runtime['situation'] = [
            'field' => $field,
            'code' => $code,
            'label' => $item['label'] ?? $code,
            'initial' => (bool) ($item['initial'] ?? false),
            'final' => (bool) ($item['final'] ?? false),
        ];
        $row['_runtime'] = $runtime;

        return $row;
    }

    private function situationConfig(array $definition): ?array
    {
        $situation = is_array($definition['situation'] ?? null) ? $definition['situation'] : [];
        if (($situation['enabled'] ?? false) !== true || empty($situation['field'])) {
            return null;
        }

        return $situation;
    }

    private function assertKnownSituation(array $definition, string $code): void
    {
        $situation = $this->situationConfig($definition);
        if (!$situation || !$situation['situations']) {
            return;
        }
        if (isset($situation['situations'][$code])) {
            return;
        }

        throw new RuntimeValidationException('SITUATION_NOT_FOUND', 'Situacao invalida para a entidade.', [
            'status' => 'blocked',
            'title' => 'Situacao invalida',
            'titleKey' => 'validation.title.invalid_situation',
            'messages' => [[
                'field' => $situation['field'],
                'type' => 'error',
                'message' => 'A situacao informada nao esta cadastrada para esta entidade.',
                'messageKey' => 'validation.message.situation_not_registered',
                'messageParams' => [
                    'field' => $situation['field'],
                    'fieldCode' => $situation['field'],
                ],
            ]],
        ]);
    }

    private function findTransition(array $situation, ?string $from, string $to, string $actionId): ?array
    {
        foreach ($situation['transitions'] ?? [] as $transition) {
            if (!is_array($transition)) {
                continue;
            }
            $transitionFrom = $this->normalizeCode($transition['from'] ?? null);
            $transitionTo = $this->normalizeCode($transition['to'] ?? null);
            $transitionAction = (string) ($transition['actionId'] ?? '');
            if ($transitionFrom !== $from || $transitionTo !== $to) {
                continue;
            }
            if ($transitionAction === $actionId || $transitionAction === '*' || $transitionAction === '') {
                return $transition;
            }
        }

        return null;
    }

    private function validateTransitionGuards(array $definition, array $transition, array $before, array $after): void
    {
        $guard = is_array($transition['guardConfig'] ?? null) ? $transition['guardConfig'] : [];
        $requiredFields = is_array($guard['requiredFields'] ?? null) ? $guard['requiredFields'] : [];
        $messages = [];

        foreach ($requiredFields as $item) {
            $field = is_array($item) ? (string) ($item['field'] ?? '') : (string) $item;
            if ($field === '' || !isset($definition['fields'][$field])) {
                continue;
            }
            $value = $after[$field] ?? null;
            if ($value === null || trim((string) $value) === '') {
                $messages[] = [
                    'field' => $field,
                    'type' => 'error',
                    'message' => is_array($item) && !empty($item['message'])
                        ? (string) $item['message']
                        : ($definition['fields'][$field]['label'] ?: $field) . ' e obrigatorio para esta mudanca de situacao.',
                    'messageKey' => is_array($item) && !empty($item['message']) ? null : 'validation.message.field_required_for_situation',
                    'messageParams' => is_array($item) && !empty($item['message']) ? [] : [
                        'field' => $field,
                        'fieldCode' => $field,
                        'fieldLabel' => $definition['fields'][$field]['label'] ?: $field,
                    ],
                ];
            }
        }

        if ($messages) {
            throw new RuntimeValidationException('SITUATION_RULE_FAILED', 'Existem regras pendentes para mudar a situacao.', [
                'status' => 'blocked',
                'title' => 'Mudanca de situacao bloqueada',
                'titleKey' => 'validation.title.situation_blocked',
                'messages' => $messages,
            ], effects: $this->requiredEffects($messages));
        }
    }

    private function requiredEffects(array $messages): array
    {
        $effects = [];
        foreach ($messages as $message) {
            if (!empty($message['field'])) {
                $effects[] = [
                    'action' => 'required',
                    'target' => $message['field'],
                    'value' => true,
                ];
            }
        }

        return $effects;
    }

    private function normalizeCode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }

        return mb_substr(trim((string) $value), 0, 80);
    }
}
