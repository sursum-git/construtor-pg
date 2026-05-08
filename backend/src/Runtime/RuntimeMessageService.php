<?php

namespace App\Runtime;

use App\Entity\RuntimeUserMessage;
use App\Repository\RuntimeUserMessageRepository;
use Doctrine\ORM\EntityManagerInterface;

class RuntimeMessageService
{
    public function __construct(
        private readonly RuntimeUserMessageRepository $messages,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeTransactionService $transactions,
    ) {
    }

    public function poll(bool $flush = false): array
    {
        $items = $this->messages->findPendingForTarget(
            $this->permissions->getTenantId(),
            $this->permissions->getUserId(),
            $this->permissions->getSessionId(),
        );

        foreach ($items as $message) {
            if ($message->getStatus() === 'pending') {
                $message->markDelivered();
            }
        }
        if ($flush) {
            $this->entityManager->flush();
        }

        return [
            'messages' => array_map(fn (RuntimeUserMessage $message) => $this->format($message), $items),
        ];
    }

    public function acknowledge(array $payload): array
    {
        $singleId = $payload['id'] ?? null;
        $ids = array_map('intval', $payload['ids'] ?? ($singleId ? [$singleId] : []));
        if (!$ids) {
            return ['ok' => true, 'count' => 0];
        }

        $items = $this->messages->createQueryBuilder('m')
            ->andWhere('m.id IN (:ids)')
            ->andWhere('m.tenantId = :tenantId')
            ->andWhere('(m.targetUserId = :userId OR m.targetSessionId = :sessionId)')
            ->setParameter('ids', $ids)
            ->setParameter('tenantId', $this->permissions->getTenantId())
            ->setParameter('userId', $this->permissions->getUserId())
            ->setParameter('sessionId', $this->permissions->getSessionId())
            ->getQuery()
            ->getResult();

        foreach ($items as $message) {
            $message->acknowledge();
        }

        return ['ok' => true, 'count' => count($items)];
    }

    public function createForceLogout(string $targetUserId, ?string $targetSessionId, string $reason): RuntimeUserMessage
    {
        $sender = $this->permissions->getCurrentUserPayload();
        $message = (new RuntimeUserMessage())
            ->setTenantId($this->permissions->getTenantId())
            ->setSenderUserId($this->permissions->getUserId())
            ->setSenderUserName($sender['name'] ?? null)
            ->setTargetUserId($targetUserId)
            ->setTargetSessionId($targetSessionId)
            ->setType('force_logout')
            ->setSeverity('error')
            ->setTitle('Sessao encerrada')
            ->setMessage($reason ?: 'Sua sessao foi encerrada pelo administrador.')
            ->setActionRequired(true)
            ->setMetadata([
                'reason' => $reason,
                'revokedBy' => $this->permissions->getUserId(),
            ]);

        $this->entityManager->persist($message);
        $this->transactions->log('message.force_logout', 'Mensagem de derrubada criada.', metadata: [
            'targetUserId' => $targetUserId,
            'targetSessionId' => $targetSessionId,
        ]);

        return $message;
    }

    private function format(RuntimeUserMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'type' => $message->getType(),
            'severity' => $message->getSeverity(),
            'title' => $message->getTitle(),
            'message' => $message->getMessage(),
            'actionRequired' => $message->isActionRequired(),
            'metadata' => $message->getMetadata(),
            'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
