<?php

namespace App\Runtime;

use App\Entity\RuntimeEvent;
use App\Entity\RuntimeEventDelivery;
use App\Entity\RuntimeEventSubscription;
use App\Repository\RuntimeEventDeliveryRepository;
use App\Repository\RuntimeEventRepository;
use App\Repository\RuntimeEventSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class RuntimeEventService
{
    private const ALLOWED_HANDLER_TYPES = ['notification', 'job', 'log', 'integration', 'webhook'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly RuntimeEventRepository $events,
        private readonly RuntimeEventSubscriptionRepository $subscriptions,
        private readonly RuntimeEventDeliveryRepository $deliveries,
        private readonly RuntimeTransactionService $transactions,
        private readonly RuntimeExecutionContext $executionContext,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeNotificationService $notifications,
        private readonly RuntimeAsyncJobService $asyncJobs,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function publish(string $eventCode, array $payload, array $options = []): RuntimeEvent
    {
        $context = $this->executionContext->all();
        $transaction = $this->transactions->getCurrent();
        $createdOperationalTransaction = false;

        if (!$transaction && ($options['createTransaction'] ?? true) === true) {
            $transaction = $this->transactions->beginOperational([
                'tenantId' => $options['tenantId'] ?? $payload['tenantId'] ?? $this->permissions->getTenantId(),
                'sessionId' => $options['sessionId'] ?? $payload['sessionId'] ?? $this->permissions->getSessionId(),
                'screenId' => $options['screenId'] ?? $payload['screenId'] ?? 'system.eventbus',
                'programId' => $options['programCode'] ?? $payload['programCode'] ?? null,
                'entityCode' => $options['entityCode'] ?? $payload['entityCode'] ?? null,
                'recordId' => $options['recordId'] ?? $payload['recordId'] ?? null,
                'endpointId' => 'eventbus.publish',
                'actionId' => 'eventbus.publish',
                'operation' => 'runtime.event.publish',
                'source' => $options['source'] ?? 'eventbus',
                'eventCode' => $eventCode,
            ]);
            $createdOperationalTransaction = true;
        }

        $tenantId = (string) ($options['tenantId'] ?? $payload['tenantId'] ?? $context['tenantId'] ?? $this->permissions->getTenantId());
        $event = (new RuntimeEvent())
            ->setEventId($this->newEventId())
            ->setEventCode($eventCode)
            ->setSource((string) ($options['source'] ?? $payload['source'] ?? 'runtime'))
            ->setTenantId($tenantId)
            ->setUserId($this->optionalString($options['userId'] ?? $payload['userId'] ?? $context['userId'] ?? $this->permissions->getUserId()))
            ->setSessionId($this->optionalString($options['sessionId'] ?? $payload['sessionId'] ?? $context['sessionId'] ?? $this->permissions->getSessionId()))
            ->setScreenId($this->optionalString($options['screenId'] ?? $payload['screenId'] ?? $context['screenId'] ?? null))
            ->setProgramCode($this->optionalString($options['programCode'] ?? $payload['programCode'] ?? $context['programId'] ?? null))
            ->setEntityCode($this->optionalString($options['entityCode'] ?? $payload['entityCode'] ?? $context['entityCode'] ?? null))
            ->setRecordId($options['recordId'] ?? $payload['recordId'] ?? $context['recordId'] ?? null)
            ->setOperation($this->optionalString($options['operation'] ?? $payload['operation'] ?? $context['operation'] ?? null))
            ->setPayload($this->sanitizeValue($payload))
            ->setMetadata($this->sanitizeValue((array) ($options['metadata'] ?? [])))
            ->setTransaction($transaction);

        $this->entityManager->persist($event);
        $this->transactions->log('runtime.event.published', 'Evento runtime publicado.', after: [
            'eventId' => $event->getEventId(),
            'eventCode' => $eventCode,
        ], metadata: [
            'eventCode' => $eventCode,
            'source' => $event->getSource(),
            'entityCode' => $event->getEntityCode(),
            'recordId' => $event->getRecordId(),
        ]);
        $this->entityManager->flush();

        if (($options['dispatch'] ?? true) === true && $this->subscriptions->findEnabledForEvent($tenantId, $eventCode)) {
            $this->messageBus->dispatch(new RuntimeEventMessage((int) $event->getId()));
        }

        if ($createdOperationalTransaction) {
            $this->transactions->success(['eventCode' => $eventCode, 'eventId' => $event->getEventId()]);
            $this->transactions->clear();
        }

        return $event;
    }

    public function process(int $eventId): void
    {
        $event = $this->events->find($eventId);
        if (!$event) {
            throw new \RuntimeException('Evento runtime nao encontrado: ' . $eventId);
        }

        $failed = false;
        $subscriptions = $this->subscriptions->findEnabledForEvent($event->getTenantId(), $event->getEventCode());
        foreach ($subscriptions as $subscription) {
            if (!$this->matchesCondition($event, $subscription->getCondition())) {
                continue;
            }

            try {
                $this->processSubscription($event, $subscription);
            } catch (\Throwable) {
                $failed = true;
            }
        }

        if ($failed) {
            $event->markFailed();
        } else {
            $event->markProcessed();
        }
        $this->entityManager->flush();
    }

    private function processSubscription(RuntimeEvent $event, RuntimeEventSubscription $subscription): void
    {
        $handlerType = $subscription->getHandlerType();
        if (!in_array($handlerType, self::ALLOWED_HANDLER_TYPES, true)) {
            throw new RuntimeHttpException('RUNTIME_EVENT_HANDLER_INVALID', 'Handler de evento nao permitido.', 422, [
                'handlerType' => $handlerType,
            ]);
        }

        $idempotencyKey = $this->renderTemplate($subscription->getIdempotencyKeyTemplate(), $event, $subscription);
        $delivery = $this->deliveries->findOneByIdempotencyKey($idempotencyKey);
        if ($delivery && in_array($delivery->getStatus(), ['succeeded', 'skipped'], true)) {
            $this->transactions->beginOperational($this->deliveryContext($event, $subscription, 'runtime.event.subscription.idempotent'));
            $this->transactions->log('runtime.event.subscription.skipped_idempotent', 'Assinatura ignorada por idempotencia.', metadata: [
                'eventId' => $event->getEventId(),
                'subscriptionCode' => $subscription->getCode(),
                'idempotencyKey' => $idempotencyKey,
            ]);
            $this->transactions->success();
            $this->transactions->clear();
            return;
        }

        if (!$delivery) {
            $delivery = (new RuntimeEventDelivery($event, $subscription))->setIdempotencyKey($idempotencyKey);
            $this->entityManager->persist($delivery);
        }

        if ($delivery->getAttempts() >= $subscription->getMaxAttempts()) {
            return;
        }

        $transaction = $this->transactions->beginOperational($this->deliveryContext($event, $subscription, 'runtime.event.subscription.process'));
        $delivery->setTransaction($transaction)->markRunning();
        $this->transactions->log('runtime.event.subscription.started', 'Processamento de assinatura iniciado.', metadata: [
            'eventId' => $event->getEventId(),
            'subscriptionCode' => $subscription->getCode(),
            'handlerType' => $handlerType,
            'attempt' => $delivery->getAttempts(),
        ]);

        try {
            $result = $this->executeHandler($event, $subscription);
            $delivery->markSucceeded($result);
            $this->transactions->log('runtime.event.subscription.completed', 'Processamento de assinatura concluido.', metadata: [
                'eventId' => $event->getEventId(),
                'subscriptionCode' => $subscription->getCode(),
                'handlerType' => $handlerType,
            ]);
            $this->transactions->success();
        } catch (\Throwable $error) {
            $delivery->markFailed($error->getMessage(), [
                'exception' => $error::class,
                'message' => $error->getMessage(),
            ]);
            $this->transactions->log('runtime.event.subscription.failed', 'Processamento de assinatura falhou.', metadata: [
                'eventId' => $event->getEventId(),
                'subscriptionCode' => $subscription->getCode(),
                'handlerType' => $handlerType,
                'exception' => $error::class,
                'message' => $error->getMessage(),
            ]);
            $this->transactions->fail($error);
            throw $error;
        } finally {
            $this->entityManager->flush();
            $this->transactions->clear();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function executeHandler(RuntimeEvent $event, RuntimeEventSubscription $subscription): array
    {
        $config = $this->rejectExecutableConfig($subscription->getHandlerConfig());

        return match ($subscription->getHandlerType()) {
            'notification' => $this->executeNotification($event, $subscription, $config),
            'job' => $this->executeJob($event, $config),
            'log' => $this->executeLog($event, $subscription, $config),
            'integration', 'webhook' => $this->executeClosedExternalHandler($event, $subscription, $config),
            default => throw new RuntimeHttpException('RUNTIME_EVENT_HANDLER_INVALID', 'Handler de evento nao permitido.', 422),
        };
    }

    private function executeNotification(RuntimeEvent $event, RuntimeEventSubscription $subscription, array $config): array
    {
        $id = $this->notifications->createAdministrativeNotification(
            $this->renderString((string) ($config['title'] ?? 'Evento runtime'), $event, $subscription),
            $this->renderString((string) ($config['message'] ?? 'Um evento runtime foi processado.'), $event, $subscription),
            [
                'tenantId' => $event->getTenantId(),
                'category' => (string) ($config['category'] ?? 'eventbus'),
                'severity' => (string) ($config['severity'] ?? 'info'),
                'code' => (string) ($config['code'] ?? 'eventbus.' . $event->getEventId() . '.' . $subscription->getCode()),
                'targetUserIds' => is_array($config['targetUserIds'] ?? null) ? $config['targetUserIds'] : array_values(array_filter([$event->getUserId()])),
                'targetGroups' => is_array($config['targetGroups'] ?? null) ? $config['targetGroups'] : [],
                'actionRequired' => (bool) ($config['actionRequired'] ?? false),
                'linkProgramId' => $event->getProgramCode(),
                'linkScreenId' => $event->getScreenId(),
                'metadata' => [
                    'eventId' => $event->getEventId(),
                    'eventCode' => $event->getEventCode(),
                    'subscriptionCode' => $subscription->getCode(),
                ],
            ]
        );

        return ['notificationId' => $id];
    }

    private function executeJob(RuntimeEvent $event, array $config): array
    {
        $jobType = trim((string) ($config['jobType'] ?? ''));
        if ($jobType === '') {
            throw new RuntimeHttpException('RUNTIME_EVENT_JOB_TYPE_REQUIRED', 'Informe o tipo de job fechado para a assinatura.', 422);
        }
        $payload = is_array($config['payload'] ?? null) ? $config['payload'] : [];
        $payload['_event'] = $this->eventSummary($event);
        $this->asyncJobs->schedule($jobType, $payload, [
            'screenId' => $event->getScreenId(),
            'programId' => $event->getProgramCode(),
            'entityCode' => $event->getEntityCode(),
            'recordId' => $event->getRecordId(),
            'actionId' => 'eventbus.' . $event->getEventCode(),
            'message' => 'Job enfileirado por evento ' . $event->getEventCode() . '.',
        ]);

        return ['queuedJobs' => $this->asyncJobs->flushPending()];
    }

    private function executeLog(RuntimeEvent $event, RuntimeEventSubscription $subscription, array $config): array
    {
        $message = $this->renderString((string) ($config['message'] ?? 'Evento runtime processado.'), $event, $subscription);
        $this->transactions->log('runtime.event.handler.log', $message, metadata: [
            'eventId' => $event->getEventId(),
            'eventCode' => $event->getEventCode(),
            'subscriptionCode' => $subscription->getCode(),
        ]);

        return ['logged' => true];
    }

    private function executeClosedExternalHandler(RuntimeEvent $event, RuntimeEventSubscription $subscription, array $config): array
    {
        if (!isset($config['integrationCode']) && !isset($config['webhookCode'])) {
            throw new RuntimeHttpException('RUNTIME_EVENT_EXTERNAL_CONTRACT_REQUIRED', 'Informe uma integracao ou webhook ja cadastrado.', 422);
        }

        $this->transactions->log('runtime.event.external.prepared', 'Integracao fechada preparada pelo EventBus.', metadata: [
            'eventId' => $event->getEventId(),
            'subscriptionCode' => $subscription->getCode(),
            'integrationCode' => $config['integrationCode'] ?? null,
            'webhookCode' => $config['webhookCode'] ?? null,
        ]);

        return ['prepared' => true];
    }

    private function matchesCondition(RuntimeEvent $event, array $condition): bool
    {
        if (!$condition) {
            return true;
        }
        $conditions = is_array($condition['conditions'] ?? null) ? $condition['conditions'] : [$condition];
        foreach ($conditions as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!$this->matchesConditionItem($event, $item)) {
                return false;
            }
        }

        return true;
    }

    private function matchesConditionItem(RuntimeEvent $event, array $condition): bool
    {
        $operator = (string) ($condition['operator'] ?? 'eq');
        $field = (string) ($condition['field'] ?? '');
        $value = $this->pathValue($event->getPayload(), $field);

        return match ($operator) {
            'neq' => $value !== ($condition['value'] ?? null),
            'exists' => $value !== null,
            'not_empty' => $value !== null && $value !== '',
            'changed' => $this->pathValue($event->getPayload(), 'before.' . $field) !== $this->pathValue($event->getPayload(), 'after.' . $field),
            default => $value === ($condition['value'] ?? null),
        };
    }

    private function deliveryContext(RuntimeEvent $event, RuntimeEventSubscription $subscription, string $operation): array
    {
        return [
            'tenantId' => $event->getTenantId(),
            'sessionId' => $event->getSessionId() ?? '',
            'screenId' => $event->getScreenId() ?? 'system.eventbus',
            'programId' => $event->getProgramCode(),
            'entityCode' => $event->getEntityCode(),
            'recordId' => $event->getRecordId(),
            'endpointId' => 'eventbus.subscription',
            'actionId' => $subscription->getCode(),
            'operation' => $operation,
            'source' => 'eventbus',
            'eventId' => $event->getEventId(),
            'eventCode' => $event->getEventCode(),
            'subscriptionCode' => $subscription->getCode(),
        ];
    }

    private function renderTemplate(string $template, RuntimeEvent $event, RuntimeEventSubscription $subscription): string
    {
        return $this->renderString($template, $event, $subscription);
    }

    private function renderString(string $template, RuntimeEvent $event, RuntimeEventSubscription $subscription): string
    {
        $tokens = [
            '{eventId}' => $event->getEventId(),
            '{eventCode}' => $event->getEventCode(),
            '{tenantId}' => $event->getTenantId(),
            '{subscriptionCode}' => $subscription->getCode(),
            '{entityCode}' => $event->getEntityCode() ?? '',
            '{recordId}' => $event->getRecordId() ?? '',
            '{programCode}' => $event->getProgramCode() ?? '',
            '{operation}' => $event->getOperation() ?? '',
        ];

        return strtr($template, $tokens);
    }

    private function rejectExecutableConfig(array $config): array
    {
        $encoded = strtolower((string) json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (preg_match('/(<\\?php|function\\s*\\(|=>|\\beval\\b|\\bselect\\b|\\binsert\\b|\\bupdate\\b|\\bdelete\\b|javascript:|https?:\\/\\/)/', $encoded)) {
            throw new RuntimeHttpException('RUNTIME_EVENT_EXECUTABLE_HANDLER_BLOCKED', 'Handler de evento deve ser declarativo e fechado.', 422);
        }

        return $config;
    }

    private function eventSummary(RuntimeEvent $event): array
    {
        return [
            'eventId' => $event->getEventId(),
            'eventCode' => $event->getEventCode(),
            'tenantId' => $event->getTenantId(),
            'screenId' => $event->getScreenId(),
            'programCode' => $event->getProgramCode(),
            'entityCode' => $event->getEntityCode(),
            'recordId' => $event->getRecordId(),
            'operation' => $event->getOperation(),
            'occurredAt' => $event->getOccurredAt()->format(DATE_ATOM),
        ];
    }

    private function pathValue(array $payload, string $path): mixed
    {
        if ($path === '') {
            return null;
        }
        $current = $payload;
        foreach (explode('.', $path) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }
        if (!is_array($value)) {
            return null;
        }
        $result = [];
        foreach ($value as $key => $item) {
            $keyString = is_scalar($key) ? (string) $key : '';
            if (preg_match('/(senha|password|token|authorization|api[_-]?key|secret|private[_-]?key)/i', $keyString)) {
                continue;
            }
            $result[$key] = $this->sanitizeValue($item);
        }

        return $result;
    }

    private function newEventId(): string
    {
        return 'evt_' . bin2hex(random_bytes(12));
    }
}
