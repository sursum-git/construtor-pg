<?php

namespace App\Runtime;

use App\Entity\RuntimeAsyncJob;
use App\Repository\ClienteRepository;
use App\Repository\RuntimeAsyncJobRepository;
use App\Runtime\CustomCode\ProdutoPdmCodeGenerator;

class RuntimeProcessHandler
{
    public function __construct(
        private readonly RuntimeAsyncJobService $asyncJobs,
        private readonly RuntimeAsyncJobRepository $jobs,
        private readonly ClienteRepository $clientes,
        private readonly PermissionResolver $permissions,
    ) {
    }

    public function startClientesProcess(string $screenId, array $payload): array
    {
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        $resultType = $this->normalizeResultType($parameters['resultado'] ?? $parameters['resultType'] ?? 'grid');
        $status = $this->normalizeStatus($parameters['status'] ?? 'TODOS');

        $parameters['resultado'] = $resultType;
        $parameters['status'] = $status;

        $reference = $this->asyncJobs->scheduleWithReference('clientes.processamento', [
            'parameters' => $parameters,
            'requestedBy' => [
                'tenantId' => $this->permissions->getTenantId(),
                'userId' => $this->permissions->getUserId(),
                'sessionId' => $this->permissions->getSessionId(),
            ],
        ], [
            'screenId' => $screenId,
            'programId' => (string) ($payload['programId'] ?? $payload['context']['programId'] ?? 'processamento-clientes'),
            'entityCode' => 'cliente',
            'actionId' => 'process',
            'message' => 'Processamento iniciado.',
        ]);

        if ($resultType === 'job') {
            return [
                'ok' => true,
                'status' => 'queued',
                'message' => 'Job iniciado. Acompanhe o termino pela tela de jobs.',
                'job' => [
                    'id' => '',
                    'status' => 'queued',
                    'type' => 'clientes.processamento',
                    'runtimePendingRef' => $reference,
                ],
                'wait' => [
                    'mode' => 'none',
                ],
                'result' => [
                    'type' => 'job',
                    'message' => 'O job foi iniciado e seguira em segundo plano.',
                    'job' => [
                        'id' => '',
                        'status' => 'queued',
                        'type' => 'clientes.processamento',
                        'runtimePendingRef' => $reference,
                    ],
                ],
            ];
        }

        return [
            'ok' => true,
            'status' => 'queued',
            'message' => 'Processamento iniciado.',
            'job' => [
                'id' => '',
                'status' => 'queued',
                'type' => 'clientes.processamento',
                'runtimePendingRef' => $reference,
            ],
            'wait' => [
                'mode' => 'polling',
                'pollIntervalSeconds' => 1,
            ],
        ];
    }

    public function getClientesProcessStatus(array $payload): array
    {
        $jobId = (int) ($payload['jobId'] ?? $payload['id'] ?? 0);
        if ($jobId <= 0) {
            throw new RuntimeHttpException('PROCESS_JOB_ID_REQUIRED', 'Informe o identificador do job de processamento.', 422);
        }

        $job = $this->findOwnedJob($jobId);
        $status = $job->getStatus();
        if (!in_array($status, ['succeeded', 'failed'], true)) {
            return [
                'ok' => true,
                'status' => $status,
                'message' => $status === 'running' ? 'Processamento em andamento.' : 'Job aguardando execucao.',
                'job' => $this->jobSummary($job),
            ];
        }

        $response = [
            'ok' => true,
            'status' => $status,
            'message' => $status === 'succeeded' ? 'Processamento concluido.' : 'Processamento falhou.',
            'job' => $this->jobSummary($job),
        ];

        if ($status === 'failed') {
            $response['result'] = [
                'type' => 'message',
                'message' => $job->getLastError() ?: 'Processamento falhou.',
            ];

            return $response;
        }

        $response['result'] = $this->buildResult($job);

        return $response;
    }

    public function startProdutoPdmAssistant(array $payload): array
    {
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        $values = [
            'familia' => $this->normalizeCodeSegment($parameters['familia'] ?? ''),
            'grupo' => $this->normalizeCodeSegment($parameters['grupo'] ?? ''),
            'linha' => $this->normalizeCodeSegment($parameters['linha'] ?? ''),
        ];

        foreach ($values as $field => $value) {
            if ($value === '') {
                throw new RuntimeHttpException('CUSTOM_CODE_PROPERTIES_REQUIRED', 'Informe familia, grupo e linha para montar o codigo PDM.', 422, [
                    'field' => $field,
                    'fields' => ['familia', 'grupo', 'linha'],
                ]);
            }
        }

        $previewCode = 'PDM-' . ProdutoPdmCodeGenerator::generate([
            'properties' => $values,
            'sequence' => [
                'current' => 1,
                'padded' => '0001',
            ],
        ]);

        return [
            'ok' => true,
            'status' => 'succeeded',
            'message' => 'Propriedades da codificacao prontas para aplicar.',
            'result' => [
                'type' => 'properties',
                'message' => 'Confira a previsao do codigo antes de aplicar.',
                'previewTitle' => 'Previsao do codigo PDM',
                'previewCode' => $previewCode,
                'values' => $values,
            ],
        ];
    }

    public function renderClientesProcessDocument(int $jobId): string
    {
        $job = $this->findOwnedJob($jobId);
        $result = $this->buildResult($job);
        $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
        $title = htmlspecialchars((string) ($result['title'] ?? 'Relatorio'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $body = '<p>Nenhum dado encontrado para os parametros informados.</p>';
        if ($rows) {
            $lines = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $lines[] = sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars((string) ($row['uf'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars((string) ($row['valor_total'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars((string) ($row['qtde_pedidos'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                );
            }
            $body = '<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>ID</th><th>Nome</th><th>Status</th><th>UF</th><th>Valor total</th><th>Pedidos</th></tr></thead><tbody>'
                . implode('', $lines)
                . '</tbody></table>';
        }

        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>'
            . $title
            . '</title><style>body{font-family:Arial,sans-serif;padding:24px;color:#1f2937}h1{margin:0 0 16px}table{border-collapse:collapse;width:100%}th,td{text-align:left}th{background:#f3f4f6}</style></head><body><h1>'
            . $title
            . '</h1><p>Job '
            . (int) $job->getId()
            . '</p>'
            . $body
            . '</body></html>';
    }

    private function findOwnedJob(int $jobId): RuntimeAsyncJob
    {
        $job = $this->jobs->find($jobId);
        if (!$job || $job->getJobType() !== 'clientes.processamento') {
            throw new RuntimeHttpException('PROCESS_JOB_NOT_FOUND', 'Job de processamento nao encontrado.', 404, [
                'id' => $jobId,
            ]);
        }
        if ($job->getTenantId() !== $this->permissions->getTenantId() || $job->getUserId() !== $this->permissions->getUserId()) {
            throw new RuntimeHttpException('PROCESS_JOB_FORBIDDEN', 'Voce nao possui acesso a este job.', 403, [
                'id' => $jobId,
            ]);
        }

        return $job;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResult(RuntimeAsyncJob $job): array
    {
        $payload = $job->getPayload();
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        $resultType = $this->normalizeResultType($parameters['resultado'] ?? 'grid');

        if ($resultType === 'message') {
            return [
                'type' => 'message',
                'message' => 'Nenhuma inconsistencia encontrada para os parametros informados.',
            ];
        }

        if ($resultType === 'job') {
            return [
                'type' => 'job',
                'message' => 'Job concluido em segundo plano.',
                'job' => $this->jobSummary($job),
            ];
        }

        if ($resultType === 'report') {
            return [
                'type' => 'report',
                'title' => 'Relatorio preparado',
                'message' => 'O relatorio foi gerado em uma pagina separada.',
                'url' => '/api/runtime/screens/processamento.relatorio-clientes/documents/resultado?jobId=' . (int) $job->getId(),
                'linkText' => 'Abrir relatorio',
            ];
        }

        $rows = $this->buildRows($parameters);

        return [
            'type' => 'grid',
            'title' => 'Clientes processados',
            'data' => $rows,
            'pageSize' => 10,
            'columns' => [
                ['field' => 'id', 'title' => 'ID', 'width' => 80],
                ['field' => 'nome', 'title' => 'Nome'],
                ['field' => 'status', 'title' => 'Status', 'width' => 120],
                ['field' => 'uf', 'title' => 'UF', 'width' => 90],
                ['field' => 'valor_total', 'title' => 'Valor total', 'width' => 140],
                ['field' => 'qtde_pedidos', 'title' => 'Pedidos', 'width' => 120],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    private function buildRows(array $parameters): array
    {
        $status = $this->normalizeStatus($parameters['status'] ?? 'TODOS');
        $qb = $this->clientes->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(6);

        if ($status !== 'TODOS') {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }

        return array_map(function ($cliente): array {
            return [
                'id' => $cliente->getId(),
                'nome' => $cliente->getNome(),
                'status' => $cliente->getStatus(),
                'uf' => $cliente->getUf() ?? '',
                'valor_total' => $cliente->getValorTotal() ?? '0.00',
                'qtde_pedidos' => $cliente->getQtdePedidos() ?? 0,
            ];
        }, $qb->getQuery()->getResult());
    }

    /**
     * @return array<string, mixed>
     */
    private function jobSummary(RuntimeAsyncJob $job): array
    {
        return [
            'id' => $job->getId(),
            'status' => $job->getStatus(),
            'type' => $job->getJobType(),
            'title' => 'Processamento de clientes',
        ];
    }

    private function normalizeResultType(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['grid', 'message', 'report', 'job'], true) ? $value : 'grid';
    }

    private function normalizeStatus(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));

        return in_array($value, ['TODOS', 'ATIVO', 'INATIVO'], true) ? $value : 'TODOS';
    }

    private function normalizeCodeSegment(mixed $value): string
    {
        $text = strtoupper(trim((string) $value));
        $text = preg_replace('/[^A-Z0-9]+/', '-', $text) ?: '';

        return trim($text, '-');
    }
}
