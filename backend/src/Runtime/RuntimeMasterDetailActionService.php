<?php

namespace App\Runtime;

use Doctrine\DBAL\Connection;

class RuntimeMasterDetailActionService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RuntimeEntityActionService $entities,
    ) {
    }

    public function handle(string $screenId, string $endpointId, array $config, array $payload): array
    {
        if ($endpointId !== 'createGraph') {
            throw new RuntimeHttpException('MASTER_DETAIL_OPERATION_NOT_FOUND', 'Operacao mestre-detalhe nao encontrada.', 404, [
                'endpointId' => $endpointId,
            ]);
        }

        $masterEntityCode = $this->safeIdentifier((string) ($config['masterEntityCode'] ?? $config['entityCode'] ?? ''));
        $masterIdField = $this->safeIdentifier((string) ($config['masterIdField'] ?? 'id'));
        $details = $this->normalizeDetails($config['details'] ?? null);
        if ($masterEntityCode === '' || $masterIdField === '' || !$details) {
            throw new RuntimeHttpException('MASTER_DETAIL_CONFIG_INVALID', 'Configuracao runtime mestre-detalhe invalida.', 500);
        }

        $masterValues = is_array($payload['master'] ?? null)
            ? $payload['master']
            : (is_array($payload['values'] ?? null) ? $payload['values'] : []);
        $detailValues = is_array($payload['details'] ?? null) ? $payload['details'] : [];
        $allowedDetailIds = array_fill_keys(array_column($details, 'id'), true);
        foreach (array_keys($detailValues) as $detailId) {
            if (!isset($allowedDetailIds[(string) $detailId])) {
                throw new RuntimeHttpException('MASTER_DETAIL_DETAIL_NOT_ALLOWED', 'Filha nao permitida no createGraph.', 422, [
                    'detailId' => (string) $detailId,
                ]);
            }
        }

        return $this->connection->transactional(function () use ($screenId, $config, $payload, $masterEntityCode, $masterIdField, $masterValues, $detailValues, $details): array {
            $master = $this->entities->handle($screenId, 'master.create', [
                'entityCode' => $masterEntityCode,
                'operation' => 'create',
                'actionId' => 'master.create',
                'programId' => $config['programId'] ?? null,
            ], $this->createPayload($payload, $masterValues, $masterEntityCode, 'master.create'));
            $masterId = $master[$masterIdField] ?? null;
            if ($masterId === null || $masterId === '') {
                throw new RuntimeHttpException('MASTER_DETAIL_MASTER_ID_REQUIRED', 'O createGraph nao recebeu o identificador do mestre criado.', 500, [
                    'masterIdField' => $masterIdField,
                ]);
            }

            $savedDetails = [];
            foreach ($details as $detail) {
                $savedDetails[$detail['id']] = [];
                $records = $detailValues[$detail['id']] ?? [];
                if (!is_array($records)) {
                    throw new RuntimeHttpException('MASTER_DETAIL_VALUES_INVALID', 'Os registros da filha devem formar uma lista.', 422, [
                        'detailId' => $detail['id'],
                    ]);
                }
                foreach ($records as $record) {
                    if (!is_array($record)) {
                        throw new RuntimeHttpException('MASTER_DETAIL_VALUES_INVALID', 'Cada registro da filha deve ser um objeto.', 422, [
                            'detailId' => $detail['id'],
                        ]);
                    }
                    $record[$detail['parentField']] = $masterId;
                    $detailEndpointId = 'detail.' . $detail['id'] . '.create';
                    $savedDetails[$detail['id']][] = $this->entities->handle($screenId, $detailEndpointId, [
                        'entityCode' => $detail['entityCode'],
                        'operation' => 'create',
                        'actionId' => $detailEndpointId,
                        'programId' => $config['programId'] ?? null,
                    ], $this->createPayload($payload, $record, $detail['entityCode'], $detailEndpointId));
                }
            }

            return [
                'master' => $master,
                'details' => $savedDetails,
            ];
        });
    }

    private function normalizeDetails(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $details = [];
        foreach ($value as $detail) {
            if (!is_array($detail)) {
                return [];
            }
            $id = $this->safeIdentifier((string) ($detail['id'] ?? ''));
            $entityCode = $this->safeIdentifier((string) ($detail['entityCode'] ?? ''));
            $parentField = $this->safeIdentifier((string) ($detail['parentField'] ?? ''));
            if ($id === '' || $entityCode === '' || $parentField === '' || isset($details[$id])) {
                return [];
            }
            $details[$id] = compact('id', 'entityCode', 'parentField');
        }

        return array_values($details);
    }

    private function createPayload(array $source, array $values, string $entityCode, string $actionId): array
    {
        $payload = $source;
        unset($payload['master'], $payload['details']);
        $payload['entityCode'] = $entityCode;
        $payload['values'] = $values;
        $payload['operation'] = 'create';
        $payload['actionId'] = $actionId;

        return $payload;
    }

    private function safeIdentifier(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z][a-z0-9_]*$/', $value) === 1 ? $value : '';
    }
}
