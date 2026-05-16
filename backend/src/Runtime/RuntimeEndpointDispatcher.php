<?php

namespace App\Runtime;

use App\Repository\RuntimeEndpointRepository;
use App\Entity\RuntimeEndpoint;

class RuntimeEndpointDispatcher
{
    public function __construct(
        private readonly RuntimeEndpointRepository $endpoints,
        private readonly PermissionResolver $permissions,
        private readonly ClienteRuntimeHandler $clientes,
        private readonly RuntimeProcessHandler $process,
        private readonly HomeRuntimeHandler $home,
        private readonly UserLayoutService $layouts,
        private readonly RuntimeSystemHandler $system,
        private readonly RuntimeSessionGuard $sessions,
        private readonly RuntimeTransactionService $transactions,
        private readonly RuntimeEntityActionService $entities,
        private readonly RuntimeApiEntityActionService $apiEntities,
        private readonly RuntimeOdooEntityActionService $odooEntities,
        private readonly RuntimeJobEnqueueService $jobEnqueue,
        private readonly RuntimeExecutionContext $executionContext,
        private readonly RuntimeAsyncJobService $asyncJobs,
        private readonly StructuralIntegrityService $integrity,
    ) {
    }

    public function dispatch(string $screenId, string $endpointId, array $payload): array
    {
        $endpoint = $this->resolveEndpoint($screenId, $endpointId);

        $this->sessions->ensureActive();
        $payload = $this->enrichPayloadFromEndpoint($endpoint, $payload);
        $this->transactions->begin($screenId, $endpointId, $endpoint->getHandler(), $payload);

        try {
            $response = $this->dispatchHandler($screenId, $endpoint, $payload);
            $queuedJobs = $this->asyncJobs->flushPending();
            $response = $this->withQueuedJobs($response, $queuedJobs);
            $this->transactions->success();

            return $response;
        } catch (\Throwable $error) {
            $this->asyncJobs->clearPending();
            $this->transactions->fail($error);
            throw $error;
        } finally {
            $this->asyncJobs->clearPending();
            $this->executionContext->clear();
        }
    }

    public function dispatchEvent(string $screenId, string $endpointId, array $payload): array
    {
        $endpoint = $this->resolveEndpoint($screenId, $endpointId);
        $this->sessions->ensureActive();
        $payload = $this->enrichPayloadFromEndpoint($endpoint, $payload);

        try {
            return $this->dispatchHandler($screenId, $endpoint, $payload);
        } finally {
            $this->executionContext->clear();
        }
    }

    private function dispatchHandler(string $screenId, RuntimeEndpoint $endpoint, array $payload): array
    {
        $handler = $endpoint->getHandler();

        return match ($handler) {
            'entity.crud' => $this->entities->handle($screenId, $endpoint->getEndpointId(), $endpoint->getConfig(), $payload),
            'entity.api.readonly' => $this->apiEntities->handle($screenId, $endpoint->getEndpointId(), $endpoint->getConfig(), $payload),
            'entity.api.crud' => $this->apiEntities->handle($screenId, $endpoint->getEndpointId(), $endpoint->getConfig(), $payload),
            'entity.api.odoo.readonly' => $this->odooEntities->handle($screenId, $endpoint->getEndpointId(), $endpoint->getConfig(), $payload),
            'cliente.read' => $this->entities->handle($screenId, 'read', ['entityCode' => 'cliente', 'operation' => 'read'], $payload),
            'cliente.get' => $this->entities->handle($screenId, 'get', ['entityCode' => 'cliente', 'operation' => 'get'], $payload),
            'cliente.create' => $this->entities->handle($screenId, 'create', ['entityCode' => 'cliente', 'operation' => 'create'], $payload),
            'cliente.update' => $this->entities->handle($screenId, 'update', ['entityCode' => 'cliente', 'operation' => 'update'], $payload),
            'cliente.delete' => $this->entities->handle($screenId, 'delete', ['entityCode' => 'cliente', 'operation' => 'delete'], $payload),
            'cliente.bulkActivate' => $this->clientes->handle('bulkActivate', $payload),
            'cliente.bulkInactivate' => $this->clientes->handle('bulkInactivate', $payload),
            'cliente.bulkDelete' => $this->clientes->handle('bulkDelete', $payload),
            'cliente.loadCidadesByUf' => $this->clientes->handle('loadCidadesByUf', $payload),
            'cliente.validateStatusCliente' => $this->clientes->handle('validateStatusCliente', $payload),
            'cliente.statusHistory' => $this->clientes->handle('statusHistory', $payload),
            'cliente.stepHistory' => $this->clientes->handle('stepHistory', $payload),
            'cliente.printClienteExcel' => $this->clientes->handle('printClienteExcel', $payload),
            'cliente.printClientePdf' => $this->clientes->handle('printClientePdf', $payload),
            'cliente.printClienteCsv' => $this->clientes->handle('printClienteCsv', $payload),
            'cliente.checkCredit' => $this->clientes->handle('checkCredit', $payload),
            'cliente.sendWelcome' => $this->clientes->handle('sendWelcome', $payload),
            'process.clientes.start' => $this->process->startClientesProcess($screenId, $payload),
            'process.clientes.status' => $this->process->getClientesProcessStatus($payload),
            'process.customCode.pdm' => $this->process->startProdutoPdmAssistant($payload),
            'runtime.job.enqueue' => $this->jobEnqueue->handle($screenId, $endpoint->getEndpointId(), $endpoint->getConfig(), $payload),
            'layout.save' => $this->layouts->saveLayout($screenId, $payload),
            'layout.restore' => $this->layouts->restoreLayout($screenId),
            'layout.saveSort' => $this->layouts->saveSort($screenId, $payload),
            'layout.deleteSort' => $this->layouts->deletePreference($screenId, 'sort', $payload['id'] ?? null, $payload),
            'layout.saveGroup' => $this->layouts->saveGroup($screenId, $payload),
            'layout.deleteGroup' => $this->layouts->deletePreference($screenId, 'group', $payload['id'] ?? null, $payload),
            'layout.saveFilter' => $this->layouts->saveFilter($screenId, $payload),
            'layout.deleteFilter' => $this->layouts->deletePreference($screenId, 'filter', $payload['id'] ?? null, $payload),
            'layout.saveMobileTemplate' => $this->layouts->saveMobileTemplate($screenId, $payload),
            'layout.deleteMobileTemplate' => $this->layouts->deleteMobileTemplate($screenId, $payload['id'] ?? null, $payload),
            'help.markAsRead' => ['ok' => true],
            'home.chat.contacts' => $this->home->handle('contacts', $payload),
            'home.chat.history' => $this->home->handle('history', $payload),
            'home.chat.send' => $this->home->handle('send', $payload),
            'home.chat.events' => $this->home->handle('chatEvents', $payload),
            'home.support.onlineUsers' => $this->home->handle('supportOnlineUsers', $payload),
            'home.support.history' => $this->home->handle('supportHistory', $payload),
            'home.support.send' => $this->home->handle('supportSend', $payload),
            'home.support.createRequest' => $this->home->handle('supportCreateRequest', $payload),
            'home.support.requestStatus' => $this->home->handle('supportRequestStatus', $payload),
            'home.support.events' => $this->home->handle('supportEvents', $payload),
            'home.aiChat.history' => $this->home->handle('aiHistory', $payload),
            'home.aiChat.send' => $this->home->handle('aiSend', $payload),
            'home.notifications.list' => $this->home->handle('notifications', $payload),
            'home.notifications.ack' => $this->home->handle('notificationsAck', $payload),
            'home.alerts.list' => $this->home->handle('alerts', $payload),
            'home.requests.list' => $this->home->handle('requests', $payload),
            'home.subscriber.change' => $this->home->handle('subscriberChange', $payload),
            'runtime.lock.acquire' => $this->system->handle('lockAcquire', $screenId, $payload),
            'runtime.lock.heartbeat' => $this->system->handle('lockHeartbeat', $screenId, $payload),
            'runtime.lock.release' => $this->system->handle('lockRelease', $screenId, $payload),
            'runtime.messages.poll' => $this->system->handle('messagesPoll', $screenId, $payload),
            'runtime.messages.ack' => $this->system->handle('messagesAck', $screenId, $payload),
            'runtime.admin.forceLogout' => $this->system->handle('adminForceLogout', $screenId, $payload),
            'runtime.admin.integrity.resign' => $this->system->handle('adminIntegrityResign', $screenId, $payload),
            default => throw new RuntimeHttpException('RUNTIME_HANDLER_NOT_FOUND', 'Handler runtime nao encontrado.', 404, [
                'handler' => $handler,
            ]),
        };
    }

    private function enrichPayloadFromEndpoint(RuntimeEndpoint $endpoint, array $payload): array
    {
        $config = $endpoint->getConfig();
        if (!in_array($endpoint->getHandler(), ['entity.crud', 'entity.api.readonly', 'entity.api.crud', 'entity.api.odoo.readonly', 'runtime.job.enqueue'], true)) {
            return $payload;
        }

        if (!empty($config['entityCode']) && empty($payload['entityCode'])) {
            $payload['entityCode'] = (string) $config['entityCode'];
        }
        if (!empty($config['operation']) && empty($payload['operation'])) {
            $payload['operation'] = (string) $config['operation'];
        }
        if (empty($payload['actionId'])) {
            $payload['actionId'] = (string) ($config['actionId'] ?? $config['operation'] ?? $endpoint->getEndpointId());
        }
        if (!empty($config['programId']) && empty($payload['programId'])) {
            $payload['programId'] = (string) $config['programId'];
        }
        $payload['_runtimeEndpoint'] = [
            'entityCode' => $payload['entityCode'] ?? null,
            'operation' => $payload['operation'] ?? null,
            'actionId' => $payload['actionId'] ?? null,
            'programId' => $payload['programId'] ?? null,
            'traceability' => is_array($config['traceability'] ?? null) ? $config['traceability'] : [],
        ];
        if (isset($config['jobs']) && is_array($config['jobs'])) {
            $payload['_runtimeEndpoint']['jobs'] = $config['jobs'];
        }
        if (isset($config['job']) && is_array($config['job'])) {
            $payload['_runtimeEndpoint']['job'] = $config['job'];
        }

        return $payload;
    }

    private function resolveEndpoint(string $screenId, string $endpointId): RuntimeEndpoint
    {
        $endpoint = $this->endpoints->findEnabled($screenId, $endpointId);
        if (!$endpoint) {
            throw new RuntimeHttpException('RUNTIME_ENDPOINT_NOT_FOUND', 'Endpoint runtime nao encontrado.', 404, [
                'screenId' => $screenId,
                'endpointId' => $endpointId,
                'minimumRequired' => [
                    'runtime_endpoint' => [
                        'screenId' => $screenId,
                        'endpointId' => $endpointId,
                        'enabled' => true,
                        'handler' => 'entity.crud ou outro handler fechado registrado',
                        'config' => [
                            'entityCode' => 'codigo da entidade quando usar entity.crud',
                            'operation' => 'read|get|create|update|delete',
                        ],
                    ],
                ],
            ]);
        }
        if (!$this->permissions->canExecuteEndpoint($endpoint)) {
            throw new RuntimeHttpException('RUNTIME_ENDPOINT_FORBIDDEN', 'Voce nao possui permissao para executar esta acao.', 403, [
                'screenId' => $screenId,
                'endpointId' => $endpointId,
            ]);
        }
        $this->integrity->assertEndpoint($endpoint);

        return $endpoint;
    }

    /**
     * @param list<array{id: int|null, type: string, status: string, message?: string}> $queuedJobs
     */
    private function withQueuedJobs(array $response, array $queuedJobs): array
    {
        if (!$queuedJobs) {
            return $response;
        }

        $runtime = is_array($response['_runtime'] ?? null) ? $response['_runtime'] : [];
        $runtime['asyncJobs'] = $queuedJobs;
        $response['_runtime'] = $runtime;
        $this->resolvePendingJobReference($response, $queuedJobs);

        $messages = [];
        $hasQueuedEmail = false;
        foreach ($queuedJobs as $job) {
            if ($job['status'] === 'queued' && !empty($job['message'])) {
                $messages[(string) $job['message']] = (string) $job['message'];
            }
            if ($job['type'] === 'cliente.email_confirmation' && $job['status'] === 'queued') {
                $hasQueuedEmail = true;
            }
        }

        if ($hasQueuedEmail && !$messages) {
            $messages['E-mail de confirmacao agendado.'] = 'E-mail de confirmacao agendado.';
        }

        if ($messages) {
            $effects = is_array($response['effects'] ?? null) ? $response['effects'] : [];
            foreach ($messages as $message) {
                $effects[] = [
                    'action' => 'showMessage',
                    'type' => 'info',
                    'message' => $message,
                ];
            }
            $response['effects'] = $effects;
        }

        return $response;
    }

    /**
     * @param list<array{id: int|null, type: string, status: string, message?: string, runtimePendingRef?: string}> $queuedJobs
     */
    private function resolvePendingJobReference(array &$response, array $queuedJobs): void
    {
        $pendingRef = (string) ($response['job']['runtimePendingRef'] ?? '');
        if ($pendingRef === '') {
            return;
        }

        foreach ($queuedJobs as $job) {
            if (($job['runtimePendingRef'] ?? '') !== $pendingRef) {
                continue;
            }
            $response['job']['id'] = $job['id'];
            $response['job']['status'] = $job['status'];
            unset($response['job']['runtimePendingRef']);
            if (isset($response['result']['job']) && is_array($response['result']['job'])) {
                $response['result']['job']['id'] = $job['id'];
                $response['result']['job']['status'] = $job['status'];
                unset($response['result']['job']['runtimePendingRef']);
            }
            return;
        }
    }
}
