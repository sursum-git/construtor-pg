<?php

namespace App\Runtime;

class ClienteBusinessRules extends AbstractRuntimeBusinessRuleHandler
{
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

        throw new RuntimeValidationException(
            'CLIENTE_OBSERVACAO_REQUIRED',
            'Existem inconsistencias no formulario.',
            [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'messages' => [
                    [
                        'field' => 'observacao',
                        'type' => 'error',
                        'message' => 'Observacao e obrigatoria para cliente inativo.',
                    ],
                ],
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
                ],
            ],
        );
    }
}
