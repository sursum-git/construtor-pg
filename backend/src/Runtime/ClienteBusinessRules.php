<?php

namespace App\Runtime;

class ClienteBusinessRules extends AbstractRuntimeBusinessRuleHandler
{
    private const TITLE_KEY = 'validation.title.inconsistencies';
    private const FIELD_REQUIRED_KEY = 'validation.message.field_required';
    private const INACTIVE_WARNING_KEY = 'validation.message.inactive_customer_note_required';

    public function supports(string $entityCode, string $actionId): bool
    {
        return $entityCode === 'cliente' && in_array($actionId, ['create', 'update', 'edit'], true);
    }

    public function beforeValidate(RuntimeBusinessRuleContext $context): void
    {
        $values = $context->getValues();
        if (($values['status'] ?? '') !== 'INATIVO') {
            return;
        }

        if (trim((string) ($values['observacao'] ?? '')) !== '') {
            return;
        }

        $context->throwValidation(
            'CLIENTE_OBSERVACAO_REQUIRED',
            [
                $context->messageItem('observacao', self::FIELD_REQUIRED_KEY, [
                    'field' => 'observacao',
                    'fieldCode' => 'observacao',
                    'fieldLabel' => 'Observacao',
                ], 'error', 'Observacao e obrigatoria para cliente inativo.'),
            ],
            [
                [
                    'action' => 'required',
                    'target' => 'observacao',
                    'value' => true,
                ],
                [
                    'action' => 'showMessage',
                    'type' => 'warning',
                    'message' => 'Informe uma observacao ao inativar o cliente.',
                    'messageKey' => self::INACTIVE_WARNING_KEY,
                    'messageParams' => [
                        'field' => 'observacao',
                        'fieldCode' => 'observacao',
                        'fieldLabel' => 'Observacao',
                    ],
                ],
            ],
            self::TITLE_KEY,
            title: 'Inconsistencias encontradas',
        );
    }
}
