<?php

namespace App\Runtime;

final class RuntimeConfiguredRuleExecutor
{
    public function __construct(
        private readonly RuntimeTransactionService $transactions,
    ) {
    }

    public function runPhase(string $phase, RuntimeBusinessRuleContext $context): void
    {
        $rules = $this->rulesForPhase($context->getDefinition(), $context->getOperation(), $context->getActionId(), $phase);
        if (!$rules) {
            return;
        }

        $messages = [];
        $effects = [];
        $validationTitle = 'Inconsistencias encontradas';
        $validationTitleKey = 'validation.title.inconsistencies';
        $validationTitleParams = [];
        foreach ($rules as $rule) {
            try {
                $result = $this->executeRule($rule, $context);
                $normalized = $this->normalizeResult($rule, $result);
                if (!empty($normalized['values']) && is_array($normalized['values'])) {
                    $context->setValues(array_replace($context->getValues(), $normalized['values']));
                }
                if (!empty($normalized['valid'])) {
                    $this->logRuleSuccess($rule, $normalized);
                    continue;
                }

                $ruleMessages = $this->normalizeMessages($rule, $normalized['messages'] ?? [], $normalized['message'] ?? null);
                $ruleEffects = is_array($normalized['effects'] ?? null) ? $normalized['effects'] : [];
                if (!empty($normalized['title']) || !empty($normalized['titleKey'])) {
                    $validationTitle = trim((string) ($normalized['title'] ?? '')) !== '' ? trim((string) ($normalized['title'] ?? '')) : $validationTitle;
                    $validationTitleKey = trim((string) ($normalized['titleKey'] ?? '')) !== '' ? trim((string) ($normalized['titleKey'] ?? '')) : $validationTitleKey;
                    $validationTitleParams = is_array($normalized['titleParams'] ?? null) ? $normalized['titleParams'] : [];
                }
                $this->logRuleFailure($rule, $ruleMessages, $normalized['metadata'] ?? []);
                $messages = array_merge($messages, $ruleMessages);
                $effects = array_merge($effects, $ruleEffects);

                if (($rule['continueOnError'] ?? false) !== true) {
                    throw $this->buildValidationException($messages, $effects, $validationTitle, $validationTitleKey, $validationTitleParams);
                }
            } catch (RuntimeValidationException $error) {
                $validation = $error->getValidation();
                $ruleMessages = is_array($validation['messages'] ?? null)
                    ? $validation['messages']
                    : [[
                        'field' => null,
                        'type' => 'error',
                        'message' => $error->getMessage(),
                    ]];
                if (!empty($validation['title']) || !empty($validation['titleKey'])) {
                    $validationTitle = trim((string) ($validation['title'] ?? '')) !== '' ? trim((string) ($validation['title'] ?? '')) : $validationTitle;
                    $validationTitleKey = trim((string) ($validation['titleKey'] ?? '')) !== '' ? trim((string) ($validation['titleKey'] ?? '')) : $validationTitleKey;
                    $validationTitleParams = is_array($validation['titleParams'] ?? null) ? $validation['titleParams'] : [];
                }
                $this->logRuleFailure($rule, $ruleMessages, [
                    'exception' => $error::class,
                    'code' => $error->getErrorCode(),
                ]);
                $messages = array_merge($messages, $ruleMessages);
                $effects = array_merge($effects, $error->getEffects());
                if (($rule['continueOnError'] ?? false) !== true) {
                    throw $this->buildValidationException($messages, $effects, $validationTitle, $validationTitleKey, $validationTitleParams);
                }
            }
        }

        if ($messages) {
            throw $this->buildValidationException($messages, $effects, $validationTitle, $validationTitleKey, $validationTitleParams);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rulesForPhase(array $definition, string $operation, string $actionId, string $phase): array
    {
        $metadata = is_array($definition['metadata'] ?? null) ? $definition['metadata'] : [];
        $items = is_array($metadata['rules'] ?? null) ? $metadata['rules'] : [];
        $rules = [];
        foreach ($items as $index => $item) {
            $rule = $this->normalizeRule($item, $index + 1);
            if (!$rule || ($rule['enabled'] ?? true) !== true) {
                continue;
            }
            if (($rule['phase'] ?? 'beforeValidate') !== $phase) {
                continue;
            }
            $operations = is_array($rule['operations'] ?? null) ? $rule['operations'] : [];
            if ($operations && !in_array($operation, $operations, true)) {
                continue;
            }
            $actionIds = is_array($rule['actionIds'] ?? null) ? $rule['actionIds'] : [];
            if ($actionIds && !in_array($actionId, $actionIds, true)) {
                continue;
            }
            $rules[] = $rule;
        }

        usort($rules, static function (array $left, array $right): int {
            $order = (int) ($left['order'] ?? 0) <=> (int) ($right['order'] ?? 0);
            if ($order !== 0) {
                return $order;
            }

            return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
        });

        return $rules;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeRule(mixed $item, int $sequence): ?array
    {
        if (!is_array($item)) {
            return null;
        }

        $type = strtolower(trim((string) ($item['type'] ?? 'requiredWhen')));
        if (!in_array($type, ['requiredwhen', 'class_method'], true)) {
            return null;
        }

        $phase = trim((string) ($item['phase'] ?? 'beforeValidate'));
        if (!in_array($phase, ['beforeValidate', 'beforePersist', 'afterPersist', 'afterCommit'], true)) {
            $phase = 'beforeValidate';
        }

        $rule = [
            'id' => $this->safeCode((string) ($item['id'] ?? ('regra-' . $sequence))),
            'label' => trim((string) ($item['label'] ?? $item['message'] ?? ('Regra ' . $sequence))),
            'type' => $type === 'requiredwhen' ? 'requiredWhen' : 'class_method',
            'phase' => $phase,
            'order' => max(0, (int) ($item['order'] ?? ($sequence * 10))),
            'enabled' => ($item['enabled'] ?? true) !== false,
            'continueOnError' => ($item['continueOnError'] ?? false) === true,
            'field' => $this->safeFieldCode((string) ($item['field'] ?? '')),
            'when' => is_array($item['when'] ?? null) ? $item['when'] : [],
            'message' => trim((string) ($item['message'] ?? '')),
            'className' => trim((string) ($item['className'] ?? $item['class'] ?? '')),
            'methodName' => trim((string) ($item['methodName'] ?? $item['method'] ?? '')),
            'params' => is_array($item['params'] ?? null) ? $item['params'] : [],
            'operations' => $this->normalizeList($item['operations'] ?? []),
            'actionIds' => $this->normalizeList($item['actionIds'] ?? []),
        ];
        $rule['when']['field'] = $this->safeFieldCode((string) ($rule['when']['field'] ?? ''));

        return $rule;
    }

    private function executeRule(array $rule, RuntimeBusinessRuleContext $context): mixed
    {
        return match ($rule['type']) {
            'requiredWhen' => $this->executeRequiredWhen($rule, $context),
            'class_method' => $this->executeClassMethod($rule, $context),
            default => null,
        };
    }

    private function executeRequiredWhen(array $rule, RuntimeBusinessRuleContext $context): ?array
    {
        $field = (string) ($rule['field'] ?? '');
        $when = is_array($rule['when'] ?? null) ? $rule['when'] : [];
        $whenField = (string) ($when['field'] ?? '');
        if ($field === '' || $whenField === '') {
            return ['valid' => true];
        }

        $values = array_merge($context->getBefore(), $context->getValues(), $context->getAfter());
        $expected = $when['equals'] ?? null;
        if (($values[$whenField] ?? null) !== $expected) {
            return ['valid' => true];
        }

        $current = $values[$field] ?? null;
        if ($current !== null && trim((string) $current) !== '') {
            return ['valid' => true];
        }

        return [
            'valid' => false,
            'message' => (string) ($rule['message'] ?? ($field . ' e obrigatorio.')),
            'messages' => [[
                'field' => $field,
                'type' => 'error',
                'message' => (string) ($rule['message'] ?? ($field . ' e obrigatorio.')),
            ]],
            'metadata' => [
                'whenField' => $whenField,
                'equals' => $expected,
            ],
        ];
    }

    private function executeClassMethod(array $rule, RuntimeBusinessRuleContext $context): mixed
    {
        $className = trim((string) ($rule['className'] ?? ''));
        $methodName = trim((string) ($rule['methodName'] ?? ''));
        if (!preg_match('/^App\\\\Runtime\\\\BusinessRule\\\\[A-Za-z0-9_\\\\]+$/', $className)) {
            throw new RuntimeHttpException('BUSINESS_RULE_CLASS_INVALID', 'Classe de regra invalida.', 422, [
                'className' => $className,
                'ruleId' => $rule['id'] ?? null,
            ]);
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $methodName)) {
            throw new RuntimeHttpException('BUSINESS_RULE_METHOD_INVALID', 'Metodo de regra invalido.', 422, [
                'methodName' => $methodName,
                'ruleId' => $rule['id'] ?? null,
            ]);
        }
        if (!class_exists($className)) {
            throw new RuntimeHttpException('BUSINESS_RULE_CLASS_NOT_FOUND', 'Classe de regra nao encontrada.', 422, [
                'className' => $className,
                'ruleId' => $rule['id'] ?? null,
            ]);
        }
        if (!method_exists($className, $methodName)) {
            throw new RuntimeHttpException('BUSINESS_RULE_METHOD_NOT_FOUND', 'Metodo de regra nao encontrado.', 422, [
                'className' => $className,
                'methodName' => $methodName,
                'ruleId' => $rule['id'] ?? null,
            ]);
        }

        $reflection = new \ReflectionMethod($className, $methodName);
        $arguments = $this->buildMethodArguments($reflection, $context, $rule);
        if ($reflection->isStatic()) {
            return $reflection->invokeArgs(null, $arguments);
        }

        $class = new \ReflectionClass($className);
        $constructor = $class->getConstructor();
        if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new RuntimeHttpException('BUSINESS_RULE_CLASS_NOT_INSTANTIABLE', 'A classe de regra precisa ter construtor sem argumentos obrigatorios.', 422, [
                'className' => $className,
                'ruleId' => $rule['id'] ?? null,
            ]);
        }

        return $reflection->invokeArgs($class->newInstance(), $arguments);
    }

    /**
     * @return list<mixed>
     */
    private function buildMethodArguments(\ReflectionMethod $reflection, RuntimeBusinessRuleContext $context, array $rule): array
    {
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            if ($typeName === RuntimeBusinessRuleContext::class) {
                $arguments[] = $context;
                continue;
            }
            if ($typeName === 'array' || ($typeName === null && $parameter->getName() === 'rule')) {
                $arguments[] = $rule;
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeHttpException('BUSINESS_RULE_METHOD_SIGNATURE_INVALID', 'Assinatura do metodo de regra nao suportada.', 422, [
                'className' => $reflection->getDeclaringClass()->getName(),
                'methodName' => $reflection->getName(),
                'parameter' => $parameter->getName(),
            ]);
        }

        return $arguments;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResult(array $rule, mixed $result): array
    {
        if ($result === null || $result === true) {
            return [
                'valid' => true,
                'message' => '',
                'messages' => [],
                'effects' => [],
                'metadata' => [],
                'values' => [],
            ];
        }
        if ($result === false) {
            return [
                'valid' => false,
                'message' => (string) ($rule['message'] ?? ($rule['label'] ?? 'A regra falhou.')),
                'messages' => [],
                'effects' => [],
                'metadata' => [],
                'values' => [],
            ];
        }
        if (is_string($result)) {
            return [
                'valid' => false,
                'message' => $result,
                'messages' => [],
                'effects' => [],
                'metadata' => [],
                'values' => [],
            ];
        }
        if (!is_array($result)) {
            return [
                'valid' => true,
                'message' => '',
                'messages' => [],
                'effects' => [],
                'metadata' => [],
                'values' => [],
            ];
        }

        return [
            'valid' => ($result['valid'] ?? true) !== false,
            'title' => trim((string) ($result['title'] ?? '')),
            'message' => trim((string) ($result['message'] ?? '')),
            'messages' => is_array($result['messages'] ?? null) ? $result['messages'] : [],
            'effects' => is_array($result['effects'] ?? null) ? $result['effects'] : [],
            'metadata' => is_array($result['metadata'] ?? null) ? $result['metadata'] : [],
            'values' => is_array($result['values'] ?? null) ? $result['values'] : [],
            'titleKey' => trim((string) ($result['titleKey'] ?? '')),
            'titleParams' => is_array($result['titleParams'] ?? null) ? $result['titleParams'] : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeMessages(array $rule, array $messages, ?string $fallbackMessage): array
    {
        $normalized = [];
        foreach ($messages as $item) {
            if (!is_array($item)) {
                continue;
            }
            $message = trim((string) ($item['message'] ?? ''));
            $messageKey = trim((string) ($item['messageKey'] ?? ''));
            if ($message === '' && $messageKey === '') {
                continue;
            }
            $normalized[] = [
                'field' => $this->safeFieldCode((string) ($item['field'] ?? '')) ?: null,
                'type' => (string) ($item['type'] ?? 'error'),
                'message' => $message,
                'messageKey' => $messageKey !== '' ? $messageKey : null,
                'messageParams' => is_array($item['messageParams'] ?? null) ? $item['messageParams'] : [],
            ];
        }
        if ($normalized) {
            return $normalized;
        }

        return [[
            'field' => $this->safeFieldCode((string) ($rule['field'] ?? '')) ?: null,
            'type' => 'error',
            'message' => $fallbackMessage && trim($fallbackMessage) !== ''
                ? trim($fallbackMessage)
                : ((string) ($rule['label'] ?? 'A regra falhou.')),
            'messageKey' => empty($rule['message']) && !empty($rule['field']) ? 'validation.message.field_required' : null,
            'messageParams' => empty($rule['message']) && !empty($rule['field']) ? [
                'field' => $this->safeFieldCode((string) ($rule['field'] ?? '')),
                'fieldCode' => $this->safeFieldCode((string) ($rule['field'] ?? '')),
                'fieldLabel' => (string) ($rule['field'] ?? ''),
            ] : [],
        ]];
    }

    private function buildValidationException(
        array $messages,
        array $effects,
        string $title = 'Inconsistencias encontradas',
        string $titleKey = 'validation.title.inconsistencies',
        array $titleParams = [],
    ): RuntimeValidationException
    {
        return new RuntimeValidationException(
            'BUSINESS_VALIDATION_FAILED',
            'Existem inconsistencias no formulario.',
            [
                'status' => 'blocked',
                'title' => $title,
                'titleKey' => $titleKey,
                'titleParams' => $titleParams,
                'messages' => $messages,
            ],
            $effects,
        );
    }

    private function logRuleSuccess(array $rule, array $normalized): void
    {
        $message = trim((string) ($normalized['message'] ?? ''));
        $this->transactions->log('entity.rule.ok', $message !== '' ? $message : ((string) ($rule['label'] ?? 'Regra executada com sucesso.')), metadata: [
            'ruleId' => $rule['id'] ?? null,
            'label' => $rule['label'] ?? null,
            'ruleType' => $rule['type'] ?? null,
            'phase' => $rule['phase'] ?? null,
            'order' => $rule['order'] ?? null,
            'continueOnError' => $rule['continueOnError'] ?? false,
            'className' => $rule['className'] ?? null,
            'methodName' => $rule['methodName'] ?? null,
            'metadata' => $normalized['metadata'] ?? [],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed> $metadata
     */
    private function logRuleFailure(array $rule, array $messages, array $metadata = []): void
    {
        $message = trim((string) (($messages[0]['message'] ?? '') ?: ($rule['label'] ?? 'Regra falhou.')));
        $this->transactions->log('entity.rule.fail', $message, metadata: [
            'ruleId' => $rule['id'] ?? null,
            'label' => $rule['label'] ?? null,
            'ruleType' => $rule['type'] ?? null,
            'phase' => $rule['phase'] ?? null,
            'order' => $rule['order'] ?? null,
            'continueOnError' => $rule['continueOnError'] ?? false,
            'className' => $rule['className'] ?? null,
            'methodName' => $rule['methodName'] ?? null,
            'messages' => $messages,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            $items[] = $text;
        }

        return array_values(array_unique($items));
    }

    private function safeCode(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?: '';

        return trim($value, '-');
    }

    private function safeFieldCode(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '', $value) ?: '';

        return $value;
    }
}
