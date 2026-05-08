<?php

namespace App\Runtime;

use App\Entity\RuntimeRecordLock;
use App\Entity\RuntimeAsyncJob;
use App\Entity\RuntimeTransaction;
use App\Entity\RuntimeTransactionLog;
use App\Entity\RuntimeUserMessage;
use App\Entity\RuntimeUserSession;
use App\Repository\BuilderEntityRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class RuntimeAuditSubscriber implements EventSubscriber
{
    /** @var array<int, array<string, mixed>> */
    private array $pendingLogs = [];
    /** @var array<string, string>|null */
    private ?array $classMap = null;
    private bool $writingAudit = false;

    public function __construct(
        private readonly BuilderEntityRepository $builderEntities,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeExecutionContext $executionContext,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->writingAudit) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->collectEntityLog($entity, 'doctrine.insert', [], $this->normalizeEntity($entity));
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $before = [];
            $after = [];
            foreach ($uow->getEntityChangeSet($entity) as $field => $change) {
                $before[$field] = $this->normalizeValue($change[0] ?? null);
                $after[$field] = $this->normalizeValue($change[1] ?? null);
            }
            $this->collectEntityLog($entity, 'doctrine.update', $before, $after);
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->collectEntityLog($entity, 'doctrine.delete', $this->normalizeEntity($entity), []);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->pendingLogs || $this->writingAudit) {
            return;
        }

        $this->writingAudit = true;
        try {
            $em = $args->getObjectManager();
            $transaction = $this->executionContext->getTransaction() ?: $this->createFallbackTransaction($em, $this->pendingLogs[0]);
            foreach ($this->pendingLogs as $item) {
                $log = (new RuntimeTransactionLog())
                    ->setTransaction($transaction)
                    ->setEventType($item['eventType'])
                    ->setMessage($item['message'])
                    ->setBeforeData($item['before'])
                    ->setAfterData($item['after'])
                    ->setDiffData($this->diff($item['before'], $item['after']))
                    ->setMetadata($item['metadata']);
                $em->persist($log);
            }
            $this->pendingLogs = [];
            $em->flush();
        } finally {
            $this->writingAudit = false;
        }
    }

    private function collectEntityLog(object $entity, string $eventType, array $before, array $after): void
    {
        if ($this->isIgnoredEntity($entity)) {
            return;
        }

        $entityCode = $this->entityCodeFor($entity::class);
        if ($entityCode === null) {
            return;
        }

        $recordId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $this->pendingLogs[] = [
            'eventType' => $eventType,
            'message' => 'Alteracao capturada pelo subscriber Doctrine.',
            'before' => $before,
            'after' => $after,
            'metadata' => [
                'source' => 'doctrine_fallback',
                'entityCode' => $entityCode,
                'recordId' => $recordId,
                'class' => $entity::class,
            ],
        ];
    }

    private function createFallbackTransaction(EntityManagerInterface $em, array $item): RuntimeTransaction
    {
        $transaction = (new RuntimeTransaction())
            ->setTenantId($this->permissions->getTenantId())
            ->setSessionId($this->permissions->getSessionId() ?: 'system')
            ->setScreenId((string) ($this->executionContext->get('screenId', 'doctrine')))
            ->setProgramId($this->executionContext->get('programId'))
            ->setEntityCode((string) ($item['metadata']['entityCode'] ?? ''))
            ->setRecordId($item['metadata']['recordId'] ?? null)
            ->setEndpointId('doctrine.flush')
            ->setActionId('doctrine.flush')
            ->setOperation('doctrine.flush')
            ->setRequestContext([
                'source' => 'doctrine_fallback',
            ]);

        $em->persist($transaction);

        return $transaction;
    }

    private function entityCodeFor(string $class): ?string
    {
        $map = $this->classMap();
        return $map[$class] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function classMap(): array
    {
        if ($this->classMap !== null) {
            return $this->classMap;
        }

        $map = [];
        foreach ($this->builderEntities->findAll() as $entity) {
            $class = $entity->getMetadata()['audit']['doctrineClass'] ?? null;
            if (is_string($class) && $class !== '') {
                $map[$class] = $entity->getCode();
            }
        }

        return $this->classMap = $map;
    }

    private function isIgnoredEntity(object $entity): bool
    {
        return $entity instanceof RuntimeTransaction
            || $entity instanceof RuntimeTransactionLog
            || $entity instanceof RuntimeAsyncJob
            || $entity instanceof RuntimeRecordLock
            || $entity instanceof RuntimeUserMessage
            || $entity instanceof RuntimeUserSession;
    }

    private function normalizeEntity(object $entity): array
    {
        $result = [];
        foreach ((array) $entity as $key => $value) {
            $name = preg_replace('/^\x00.+\x00/', '', (string) $key);
            $result[$name] = $this->normalizeValue($value);
        }

        return $result;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if (is_object($value)) {
            return method_exists($value, 'getId') ? $value->getId() : $value::class;
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->normalizeValue($item), $value);
        }

        return $value;
    }

    private function diff(array $before, array $after): array
    {
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $diff = [];
        foreach ($keys as $key) {
            $left = $before[$key] ?? null;
            $right = $after[$key] ?? null;
            if ($left !== $right) {
                $diff[$key] = [
                    'before' => $left,
                    'after' => $right,
                ];
            }
        }

        return $diff;
    }
}
