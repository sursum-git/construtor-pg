<?php

namespace App\Runtime;

use App\Entity\RuntimeUserMessage;
use App\Repository\AuthUserRepository;
use App\Repository\RuntimeUserMessageRepository;
use App\Repository\RuntimeUserSessionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class HomeSupportService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeTransactionService $transactions,
        private readonly RuntimeUserMessageRepository $messages,
        private readonly RuntimeUserSessionRepository $sessions,
        private readonly AuthUserRepository $users,
    ) {
    }

    public function listChatContacts(): array
    {
        $tenantId = $this->permissions->getTenantId();
        $currentUserId = $this->permissions->getUserId();
        $activeSessions = $this->sessions->findActiveByTenant($tenantId, $currentUserId);
        $profiles = $this->loadUserProfiles($tenantId, array_map(static fn ($item) => $item->getUserId(), $activeSessions));

        $items = [];
        foreach ($activeSessions as $session) {
            $userId = $session->getUserId();
            if ($userId === '' || isset($items[$userId])) {
                continue;
            }
            $profile = $profiles[$userId] ?? $profiles[mb_strtolower($userId)] ?? [];
            $name = (string) ($profile['name'] ?? $session->getUserName() ?? $userId);
            $items[$userId] = [
                'id' => $userId,
                'name' => $name,
                'email' => (string) ($profile['email'] ?? ''),
                'initials' => $this->initials($name),
            ];
        }

        return [
            'items' => array_values($items),
        ];
    }

    public function chatHistory(string $recipientId): array
    {
        return [
            'messages' => $this->formatConversation($recipientId, ['chat']),
        ];
    }

    public function chatEvents(string $recipientId, int $afterId = 0): array
    {
        return [
            'recipientId' => trim($recipientId),
            'messages' => $this->formatNewConversationMessages($recipientId, ['chat'], $afterId),
        ];
    }

    public function sendChatMessage(array $message, array $recipient, array $context): array
    {
        $recipientId = trim((string) ($recipient['id'] ?? ''));
        if ($recipientId === '') {
            throw new RuntimeHttpException('CHAT_RECIPIENT_REQUIRED', 'Selecione um usuario para conversar.', 422);
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            throw new RuntimeHttpException('CHAT_MESSAGE_REQUIRED', 'Informe a mensagem do chat.', 422);
        }

        $this->persistMessage('chat', $recipientId, (string) ($recipient['name'] ?? ''), $text, $context, 'Chat');

        return [
            'ok' => true,
            'deliveredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'recipientId' => $recipientId,
        ];
    }

    public function listSupportOnlineUsers(?string $sectorId = null): array
    {
        $tenantId = $this->permissions->getTenantId();
        $currentUserId = $this->permissions->getUserId();
        $activeSessions = $this->sessions->findActiveByTenant($tenantId, $currentUserId);
        $profiles = $this->loadUserProfiles($tenantId, array_map(static fn ($item) => $item->getUserId(), $activeSessions));

        $users = [];
        $sectors = [];
        foreach ($activeSessions as $session) {
            $userId = $session->getUserId();
            $profile = $profiles[$userId] ?? $profiles[mb_strtolower($userId)] ?? [];
            $sectorIds = $this->extractSupportSectors($profile['groups'] ?? []);
            if ($sectorIds === []) {
                continue;
            }
            $name = (string) ($profile['name'] ?? $session->getUserName() ?? $userId);
            foreach ($sectorIds as $currentSectorId) {
                $sectors[$currentSectorId] = [
                    'id' => $currentSectorId,
                    'name' => $this->sectorName($currentSectorId),
                ];
                if ($sectorId !== null && $sectorId !== '' && $currentSectorId !== $sectorId) {
                    continue;
                }
                $key = $userId . '::' . $currentSectorId;
                if (isset($users[$key])) {
                    continue;
                }
                $users[$key] = [
                    'id' => $userId,
                    'name' => $name,
                    'email' => (string) ($profile['email'] ?? ''),
                    'sectorId' => $currentSectorId,
                    'sectorName' => $this->sectorName($currentSectorId),
                    'status' => 'online',
                ];
            }
        }

        if ($sectorId !== null && $sectorId !== '' && !isset($sectors[$sectorId])) {
            $sectors[$sectorId] = [
                'id' => $sectorId,
                'name' => $this->sectorName($sectorId),
            ];
        }
        if ($sectors === []) {
            $sectors['suporte'] = [
                'id' => 'suporte',
                'name' => 'Suporte',
            ];
        }

        return [
            'onlineUsers' => array_values($users),
            'sectors' => array_values($sectors),
        ];
    }

    public function supportHistory(string $attendantId): array
    {
        return [
            'messages' => $this->formatConversation($attendantId, ['support_chat']),
        ];
    }

    public function supportEvents(string $attendantId, ?string $sectorId = null, ?string $protocol = null, int $afterId = 0): array
    {
        $availability = $this->listSupportOnlineUsers($sectorId);
        return [
            'attendantId' => trim($attendantId),
            'onlineUsers' => $availability['onlineUsers'] ?? [],
            'sectors' => $availability['sectors'] ?? [],
            'messages' => $this->formatNewConversationMessages($attendantId, ['support_chat'], $afterId),
            'requestStatus' => $this->supportRequestStatus($protocol),
        ];
    }

    public function sendSupportMessage(array $message, array $attendant, array $context): array
    {
        $attendantId = trim((string) ($attendant['id'] ?? ''));
        if ($attendantId === '') {
            throw new RuntimeHttpException('SUPPORT_ATTENDANT_REQUIRED', 'Selecione um atendente online.', 422);
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            throw new RuntimeHttpException('SUPPORT_MESSAGE_REQUIRED', 'Informe a mensagem do atendimento.', 422);
        }

        $this->persistMessage('support_chat', $attendantId, (string) ($attendant['name'] ?? ''), $text, $context, 'Atendimento');

        return [
            'ok' => true,
            'deliveredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'attendantId' => $attendantId,
        ];
    }

    public function createSupportRequest(array $sector, string $priority, string $subject, string $description, array $context): array
    {
        $sectorId = trim((string) ($sector['id'] ?? ''));
        if ($sectorId === '') {
            throw new RuntimeHttpException('SUPPORT_SECTOR_REQUIRED', 'Selecione o setor da solicitacao.', 422);
        }
        if ($subject === '' || $description === '') {
            throw new RuntimeHttpException('SUPPORT_REQUEST_REQUIRED_FIELDS', 'Informe assunto e descricao da solicitacao.', 422);
        }

        $tenantId = $this->permissions->getTenantId();
        $currentUser = $this->permissions->getCurrentUserPayload();
        $assigned = $this->firstOnlineSupportUser($sectorId);
        $now = new \DateTimeImmutable();
        $protocol = 'ATD-' . $now->format('YmdHis');
        $status = $assigned ? 'assigned' : 'open';

        $this->connection->insert('runtime_support_request', [
            'tenant_id' => $tenantId,
            'protocol' => $protocol,
            'requester_user_id' => $this->permissions->getUserId(),
            'requester_user_name' => mb_substr((string) ($currentUser['name'] ?? $this->permissions->getUserId()), 0, 160),
            'sector_id' => mb_substr($sectorId, 0, 80),
            'sector_name' => mb_substr((string) ($sector['name'] ?? $this->sectorName($sectorId)), 0, 160),
            'priority' => mb_substr($priority !== '' ? $priority : 'normal', 0, 30),
            'subject' => mb_substr($subject, 0, 200),
            'description' => $description,
            'status' => mb_substr($status, 0, 30),
            'assigned_user_id' => $assigned['id'] ?? null,
            'assigned_user_name' => $assigned['name'] ?? null,
            'request_context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'metadata' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        $this->transactions->log('home.support.request.create', 'Solicitacao de atendimento criada.', metadata: [
            'protocol' => $protocol,
            'sectorId' => $sectorId,
            'assignedUserId' => $assigned['id'] ?? null,
        ]);

        return [
            'ok' => true,
            'protocol' => $protocol,
            'sectorId' => $sectorId,
            'status' => $status,
            'assignedTo' => $assigned,
            'createdAt' => $now->format(DATE_ATOM),
        ];
    }

    public function supportRequestStatus(?string $protocol = null): array
    {
        $tenantId = $this->permissions->getTenantId();
        $userId = $this->permissions->getUserId();

        if ($protocol !== null && $protocol !== '') {
            $row = $this->connection->fetchAssociative(
                'SELECT protocol, status, assigned_user_id, assigned_user_name, updated_at
                   FROM runtime_support_request
                  WHERE tenant_id = :tenantId AND requester_user_id = :userId AND protocol = :protocol',
                [
                    'tenantId' => $tenantId,
                    'userId' => $userId,
                    'protocol' => $protocol,
                ]
            );
        } else {
            $row = $this->connection->fetchAssociative(
                'SELECT protocol, status, assigned_user_id, assigned_user_name, updated_at
                   FROM runtime_support_request
                  WHERE tenant_id = :tenantId AND requester_user_id = :userId
               ORDER BY created_at DESC
                  LIMIT 1',
                [
                    'tenantId' => $tenantId,
                    'userId' => $userId,
                ]
            );
        }

        if (!$row) {
            return [
                'status' => 'none',
                'assignedTo' => null,
                'updatedAt' => null,
            ];
        }

        return [
            'protocol' => (string) $row['protocol'],
            'status' => (string) $row['status'],
            'assignedTo' => ($row['assigned_user_id'] ?? null) ? [
                'id' => (string) $row['assigned_user_id'],
                'name' => (string) ($row['assigned_user_name'] ?? $row['assigned_user_id']),
            ] : null,
            'updatedAt' => $this->formatTimestamp($row['updated_at'] ?? null),
        ];
    }

    /**
     * @param string[] $types
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatConversation(string $otherUserId, array $types): array
    {
        $items = $this->messages->findConversation(
            $this->permissions->getTenantId(),
            $this->permissions->getUserId(),
            trim($otherUserId),
            $types
        );
        $shouldFlush = false;
        foreach ($items as $item) {
            if ($item->getTargetUserId() === $this->permissions->getUserId() && $item->getStatus() === 'pending') {
                $item->markDelivered();
                $shouldFlush = true;
            }
        }
        if ($shouldFlush) {
            $this->entityManager->flush();
        }

        return array_map(function (RuntimeUserMessage $message): array {
            return $this->toMessagePayload($message);
        }, $items);
    }

    /**
     * @param string[] $types
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatNewConversationMessages(string $otherUserId, array $types, int $afterId): array
    {
        $items = $this->messages->findConversationAfterId(
            $this->permissions->getTenantId(),
            $this->permissions->getUserId(),
            trim($otherUserId),
            $types,
            $afterId
        );
        $shouldFlush = false;
        foreach ($items as $item) {
            if ($item->getTargetUserId() === $this->permissions->getUserId() && $item->getStatus() === 'pending') {
                $item->markDelivered();
                $shouldFlush = true;
            }
        }
        if ($shouldFlush) {
            $this->entityManager->flush();
        }

        return array_map(fn (RuntimeUserMessage $message): array => $this->toMessagePayload($message), $items);
    }

    private function persistMessage(string $type, string $targetUserId, string $targetUserName, string $text, array $context, string $title): void
    {
        $currentUser = $this->permissions->getCurrentUserPayload();
        $message = (new RuntimeUserMessage())
            ->setTenantId($this->permissions->getTenantId())
            ->setSenderUserId($this->permissions->getUserId())
            ->setSenderUserName($currentUser['name'] ?? $this->permissions->getUserId())
            ->setTargetUserId($targetUserId)
            ->setType($type)
            ->setSeverity('info')
            ->setTitle($title)
            ->setMessage($text)
            ->setActionRequired(false)
            ->setMetadata([
                'channel' => $type,
                'context' => $context,
                'targetUserName' => $targetUserName,
            ]);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->transactions->log('home.chat.send', 'Mensagem registrada.', metadata: [
            'type' => $type,
            'targetUserId' => $targetUserId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toMessagePayload(RuntimeUserMessage $message): array
    {
        return [
            'id' => 'msg-' . (string) $message->getId(),
            'messageId' => $message->getId(),
            'text' => $message->getMessage(),
            'authorId' => $message->getSenderUserId(),
            'authorName' => $message->getSenderUserName() ?: $message->getSenderUserId(),
            'timestamp' => $message->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param string[] $userIds
     *
     * @return array<string, array{name: string, email: string, groups: array<int, string>}>
     */
    private function loadUserProfiles(string $tenantId, array $userIds): array
    {
        $items = $this->users->findActiveByTenantAndUsernames($tenantId, $userIds);
        $profiles = [];
        foreach ($items as $item) {
            $profile = [
                'name' => $item->getDisplayName() ?: $item->getUsername(),
                'email' => $item->getEmail() ?: '',
                'groups' => $item->getGroups(),
            ];
            $profiles[$item->getUsername()] = $profile;
            $profiles[mb_strtolower($item->getUsername())] = $profile;
        }

        return $profiles;
    }

    /**
     * @param array<int, string> $groups
     *
     * @return string[]
     */
    private function extractSupportSectors(array $groups): array
    {
        $sectors = [];
        foreach ($groups as $group) {
            $normalized = strtolower(trim((string) $group));
            if ($normalized === 'support') {
                $sectors[] = 'suporte';
                continue;
            }
            if (str_starts_with($normalized, 'support.')) {
                $sectorId = trim(substr($normalized, 8));
                if ($sectorId !== '') {
                    $sectors[] = $sectorId;
                }
            }
        }

        return array_values(array_unique($sectors));
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function firstOnlineSupportUser(string $sectorId): ?array
    {
        $response = $this->listSupportOnlineUsers($sectorId);
        $items = is_array($response['onlineUsers'] ?? null) ? $response['onlineUsers'] : [];
        $item = $items[0] ?? null;
        if (!is_array($item) || !($item['id'] ?? null)) {
            return null;
        }

        return [
            'id' => (string) $item['id'],
            'name' => (string) ($item['name'] ?? $item['id']),
        ];
    }

    private function sectorName(string $sectorId): string
    {
        $normalized = trim($sectorId);
        if ($normalized === '') {
            return 'Suporte';
        }
        $text = str_replace(['_', '-'], ' ', $normalized);

        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : 'US';
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }
}
