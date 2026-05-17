<?php

namespace App\Runtime;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class RuntimeNotificationService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PermissionResolver $permissions,
    ) {
    }

    public function prepareValues(array $values, bool $isCreate): array
    {
        if ($isCreate || array_key_exists('tenant_id', $values)) {
            $tenantId = trim((string) ($values['tenant_id'] ?? $this->permissions->getTenantId()));
            $values['tenant_id'] = $tenantId !== '' ? $tenantId : $this->permissions->getTenantId();
        }
        if ($isCreate || array_key_exists('code', $values)) {
            $values['code'] = $this->normalizeCode($values['code'] ?? null, $isCreate);
        }
        if ($isCreate || array_key_exists('status', $values)) {
            $values['status'] = $this->normalizeStatus($values['status'] ?? 'draft');
        }
        if ($isCreate || array_key_exists('severity', $values)) {
            $values['severity'] = $this->normalizeSeverity($values['severity'] ?? 'info');
        }
        if ($isCreate || array_key_exists('category', $values)) {
            $category = trim((string) ($values['category'] ?? 'notificacao'));
            $values['category'] = $category !== '' ? $category : 'notificacao';
        }
        if ($isCreate || array_key_exists('target_user_ids', $values)) {
            $values['target_user_ids'] = $this->normalizeStringList($values['target_user_ids'] ?? []);
        }
        if ($isCreate || array_key_exists('target_groups', $values)) {
            $values['target_groups'] = $this->normalizeStringList($values['target_groups'] ?? []);
        }
        if ($isCreate || array_key_exists('action_required', $values)) {
            $values['action_required'] = (bool) ($values['action_required'] ?? false);
        }
        if ($isCreate || array_key_exists('link_program_id', $values)) {
            $values['link_program_id'] = $this->nullableTrimmed($values['link_program_id'] ?? null);
        }
        if ($isCreate || array_key_exists('link_screen_id', $values)) {
            $values['link_screen_id'] = $this->nullableTrimmed($values['link_screen_id'] ?? null);
        }
        if ($isCreate) {
            $values['created_by'] = $this->nullableTrimmed($values['created_by'] ?? null) ?: $this->permissions->getUserId();
        } elseif (array_key_exists('created_by', $values)) {
            $values['created_by'] = $this->nullableTrimmed($values['created_by'] ?? null);
        }
        if ($isCreate || array_key_exists('expires_at', $values)) {
            $values['expires_at'] = $this->normalizeDateTime($values['expires_at'] ?? null);
        }
        if ($isCreate || array_key_exists('published_at', $values) || array_key_exists('status', $values)) {
            $status = (string) ($values['status'] ?? 'draft');
            $publishedAt = $this->normalizeDateTime($values['published_at'] ?? null);
            if ($status === 'published') {
                $values['published_at'] = $publishedAt ?: (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            } else {
                $values['published_at'] = $publishedAt;
            }
        }

        return $values;
    }

    public function createAdministrativeNotification(string $title, string $message, array $options = []): ?int
    {
        $tenantId = trim((string) ($options['tenantId'] ?? $this->permissions->getTenantId()));
        $targetUserIds = $this->normalizeStringList($options['targetUserIds'] ?? [$this->permissions->getUserId()]);
        $targetGroups = $this->normalizeStringList($options['targetGroups'] ?? []);
        if (!$targetUserIds && !$targetGroups) {
            $targetUserIds = [$this->permissions->getUserId()];
        }

        $category = trim((string) ($options['category'] ?? 'governanca'));
        $severity = $this->normalizeSeverity($options['severity'] ?? 'warning');
        $code = trim((string) ($options['code'] ?? ''));
        if ($code === '') {
            $code = $this->normalizeCode($category . '.' . date('YmdHis') . '.' . substr(md5($title . $message), 0, 8), true);
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $metadata = is_array($options['metadata'] ?? null) ? $options['metadata'] : [];
        $existingId = $this->connection->fetchOne(
            'SELECT id FROM runtime_notification WHERE tenant_id = :tenantId AND code = :code LIMIT 1',
            [
                'tenantId' => $tenantId,
                'code' => $code,
            ]
        );
        if ($existingId) {
            $this->connection->update('runtime_notification', [
                'title' => mb_substr(trim($title), 0, 255),
                'message' => trim($message),
                'category' => $category !== '' ? $category : 'governanca',
                'severity' => $severity,
                'status' => 'published',
                'action_required' => ($options['actionRequired'] ?? false) === true,
                'target_user_ids' => json_encode($targetUserIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'target_groups' => json_encode($targetGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'link_program_id' => $this->nullableTrimmed($options['linkProgramId'] ?? null),
                'link_screen_id' => $this->nullableTrimmed($options['linkScreenId'] ?? null),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'expires_at' => $this->normalizeDateTime($options['expiresAt'] ?? null),
                'published_at' => $now,
                'updated_at' => $now,
            ], ['id' => (int) $existingId]);
            $this->syncRecipients((int) $existingId);
            return (int) $existingId;
        }

        $this->connection->insert('runtime_notification', [
            'tenant_id' => $tenantId,
            'code' => $code,
            'title' => mb_substr(trim($title), 0, 255),
            'message' => trim($message),
            'category' => $category !== '' ? $category : 'governanca',
            'severity' => $severity,
            'status' => 'published',
            'action_required' => ($options['actionRequired'] ?? false) === true,
            'target_user_ids' => json_encode($targetUserIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'target_groups' => json_encode($targetGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'link_program_id' => $this->nullableTrimmed($options['linkProgramId'] ?? null),
            'link_screen_id' => $this->nullableTrimmed($options['linkScreenId'] ?? null),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expires_at' => $this->normalizeDateTime($options['expiresAt'] ?? null),
            'published_at' => $now,
            'created_by' => $this->permissions->getUserId(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $notificationId = (int) $this->connection->lastInsertId();
        $this->syncRecipients($notificationId);

        return $notificationId > 0 ? $notificationId : null;
    }

    public function validateValues(array $values): void
    {
        if (($values['status'] ?? 'draft') !== 'published') {
            return;
        }

        $targetUsers = $this->normalizeStringList($values['target_user_ids'] ?? []);
        $targetGroups = $this->normalizeStringList($values['target_groups'] ?? []);
        if (!$targetUsers && !$targetGroups) {
            throw new RuntimeValidationException('NOTIFICATION_TARGET_REQUIRED', 'Informe ao menos um usuario ou grupo destinatario.', [
                'status' => 'blocked',
                'titleKey' => 'validation.title.inconsistencies',
                'messages' => [[
                    'type' => 'error',
                    'message' => 'Informe ao menos um usuario ou grupo destinatario.',
                ]],
            ]);
        }

        $resolved = $this->resolveRecipients((string) ($values['tenant_id'] ?? $this->permissions->getTenantId()), $targetUsers, $targetGroups);
        if (!$resolved) {
            throw new RuntimeValidationException('NOTIFICATION_RECIPIENTS_NOT_FOUND', 'Nenhum destinatario ativo foi encontrado para a notificacao.', [
                'status' => 'blocked',
                'titleKey' => 'validation.title.inconsistencies',
                'messages' => [[
                    'type' => 'error',
                    'message' => 'Nenhum destinatario ativo foi encontrado para os usuarios ou grupos informados.',
                ]],
            ]);
        }
    }

    public function syncRecipients(int $notificationId): int
    {
        $notification = $this->connection->fetchAssociative(
            'SELECT id, tenant_id, status, target_user_ids, target_groups FROM runtime_notification WHERE id = :id',
            ['id' => $notificationId],
        );
        if (!$notification) {
            return 0;
        }

        $tenantId = (string) ($notification['tenant_id'] ?? $this->permissions->getTenantId());
        if ((string) ($notification['status'] ?? 'draft') !== 'published') {
            $this->connection->delete('runtime_notification_recipient', ['notification_id' => $notificationId]);
            return 0;
        }

        $resolved = $this->resolveRecipients(
            $tenantId,
            $this->decodeStringList($notification['target_user_ids'] ?? null),
            $this->decodeStringList($notification['target_groups'] ?? null),
        );

        $existing = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT id, user_id, status, delivered_at, read_at FROM runtime_notification_recipient WHERE notification_id = :notificationId',
            ['notificationId' => $notificationId],
        ) as $row) {
            $existing[(string) $row['user_id']] = $row;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($resolved as $userId => $recipient) {
            if (isset($existing[$userId])) {
                $row = $existing[$userId];
                $this->connection->update('runtime_notification_recipient', [
                    'user_name' => $recipient['user_name'],
                    'source_type' => $recipient['source_type'],
                    'source_key' => $recipient['source_key'],
                    'updated_at' => $now,
                ], ['id' => (int) $row['id']]);
                unset($existing[$userId]);
                continue;
            }

            $this->connection->insert('runtime_notification_recipient', [
                'tenant_id' => $tenantId,
                'notification_id' => $notificationId,
                'user_id' => $recipient['user_id'],
                'user_name' => $recipient['user_name'],
                'source_type' => $recipient['source_type'],
                'source_key' => $recipient['source_key'],
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($existing as $row) {
            $this->connection->delete('runtime_notification_recipient', ['id' => (int) $row['id']]);
        }

        return count($resolved);
    }

    public function deleteRecipients(int $notificationId): void
    {
        $this->connection->delete('runtime_notification_recipient', ['notification_id' => $notificationId]);
    }

    public function listForCurrentUser(bool $includeRead = false, array $filters = []): array
    {
        $tenantId = $this->permissions->getTenantId();
        $userId = $this->permissions->getUserId();
        $statuses = $includeRead ? ['pending', 'delivered', 'read'] : ['pending', 'delivered'];
        if (($filters['unreadOnly'] ?? false) === true) {
            $statuses = ['pending', 'delivered'];
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 30)));
        $query = $this->connection->createQueryBuilder()
            ->select(
                'r.id AS recipient_id',
                'r.status AS recipient_status',
                'r.delivered_at',
                'r.read_at',
                'n.id AS notification_id',
                'n.code',
                'n.title',
                'n.message',
                'n.category',
                'n.severity',
                'n.link_program_id',
                'n.link_screen_id',
                'n.action_required',
                'n.metadata',
                'n.created_at',
                'n.expires_at'
            )
            ->from('runtime_notification_recipient', 'r')
            ->innerJoin('r', 'runtime_notification', 'n', 'n.id = r.notification_id')
            ->where('r.tenant_id = :tenantId')
            ->andWhere('r.user_id = :userId')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('n.status = :notificationStatus')
            ->andWhere('(n.expires_at IS NULL OR n.expires_at > :now)')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('userId', $userId)
            ->setParameter('statuses', $statuses, ArrayParameterType::STRING)
            ->setParameter('notificationStatus', 'published')
            ->setParameter('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
            ->orderBy('n.created_at', 'DESC')
            ->setMaxResults($limit);

        $severity = trim((string) ($filters['severity'] ?? ''));
        if ($severity !== '') {
            $query
                ->andWhere('n.severity = :severity')
                ->setParameter('severity', $severity);
        }
        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $query
                ->andWhere('n.category = :category')
                ->setParameter('category', $category);
        }
        if (array_key_exists('actionRequired', $filters) && $filters['actionRequired'] !== null && $filters['actionRequired'] !== '') {
            $query
                ->andWhere('n.action_required = :actionRequired')
                ->setParameter('actionRequired', ($filters['actionRequired'] ?? false) === true);
        }

        $rows = $query->executeQuery()->fetchAllAssociative();

        $pendingIds = [];
        $items = [];
        foreach ($rows as $row) {
            $metadata = $this->decodeJsonMap($row['metadata'] ?? null);
            $actionLabel = trim((string) ($metadata['actionLabel'] ?? ''));
            $resolvedProgramId = $this->nullableTrimmed($metadata['actionProgramId'] ?? $row['link_program_id'] ?? null);
            $resolvedScreenId = $this->nullableTrimmed($metadata['actionScreenId'] ?? $row['link_screen_id'] ?? null);
            $navigationQuery = $this->normalizeNavigationQuery($metadata['actionQuery'] ?? null);
            if ((string) ($row['recipient_status'] ?? '') === 'pending') {
                $pendingIds[] = (int) $row['recipient_id'];
            }
            $items[] = [
                'id' => (int) $row['notification_id'],
                'recipientId' => (int) $row['recipient_id'],
                'title' => (string) ($row['title'] ?? 'Notificacao'),
                'description' => (string) ($row['message'] ?? ''),
                'type' => (string) ($row['category'] ?? 'Notificacao'),
                'status' => $this->formatRecipientStatus((string) ($row['recipient_status'] ?? 'pending')),
                'severity' => (string) ($row['severity'] ?? 'info'),
                'programId' => $resolvedProgramId,
                'screenId' => $resolvedScreenId,
                'linkText' => $actionLabel !== '' ? $actionLabel : ($resolvedProgramId || $resolvedScreenId ? 'Abrir item' : null),
                'actionRequired' => (bool) ($row['action_required'] ?? false),
                'createdAt' => $this->formatDateTime($row['created_at'] ?? null),
                'updatedAt' => $this->formatDateTime($row['read_at'] ?? $row['delivered_at'] ?? $row['created_at'] ?? null),
                'metadata' => $metadata,
                'navigation' => array_filter([
                    'programId' => $resolvedProgramId,
                    'screenId' => $resolvedScreenId,
                    'query' => $navigationQuery ?: null,
                ], static fn ($value) => $value !== null && $value !== ''),
                'technicalProperties' => [
                    ['section' => 'Notificacao', 'labelKey' => 'technical.label.code', 'label' => 'Codigo', 'value' => (string) ($row['code'] ?? '')],
                    ['section' => 'Notificacao', 'labelKey' => 'technical.label.severity', 'label' => 'Severidade', 'value' => (string) ($row['severity'] ?? 'info'), 'critical' => strtolower((string) ($row['severity'] ?? '')) === 'error'],
                    ['section' => 'Destinatario', 'labelKey' => 'technical.label.status', 'label' => 'Status', 'value' => (string) ($row['recipient_status'] ?? 'pending'), 'critical' => (bool) ($row['action_required'] ?? false) && strtolower((string) ($row['recipient_status'] ?? '')) !== 'read'],
                    array_filter([
                        'section' => 'Navegacao',
                        'labelKey' => 'technical.label.program',
                        'label' => 'Programa',
                        'value' => (string) ($resolvedProgramId ?: '-'),
                        'action' => $resolvedProgramId ? [
                            'type' => 'openProgram',
                            'programId' => (string) $resolvedProgramId,
                            'label' => $actionLabel !== '' ? $actionLabel : 'Abrir programa',
                        ] : null,
                    ], static fn ($value) => $value !== null),
                    array_filter([
                        'section' => 'Navegacao',
                        'labelKey' => 'technical.label.screen_id',
                        'label' => 'Screen ID',
                        'value' => (string) ($resolvedScreenId ?: '-'),
                        'action' => $resolvedScreenId ? [
                            'type' => 'openScreen',
                            'screenId' => (string) $resolvedScreenId,
                            'label' => $actionLabel !== '' ? $actionLabel : 'Abrir tela',
                        ] : null,
                    ], static fn ($value) => $value !== null),
                ],
            ];
        }

        if ($pendingIds) {
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->connection->executeStatement(
                'UPDATE runtime_notification_recipient SET status = :status, delivered_at = COALESCE(delivered_at, :now), updated_at = :now WHERE id IN (:ids)',
                ['status' => 'delivered', 'now' => $now, 'ids' => $pendingIds],
                ['ids' => ArrayParameterType::INTEGER],
            );
        }

        return ['items' => $items];
    }

    public function acknowledgeForCurrentUser(array $ids, array $filters = []): int
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(static fn ($id) => (int) $id, $ids), static fn ($id) => $id > 0)));
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($normalizedIds) {
            return $this->connection->executeStatement(
                'UPDATE runtime_notification_recipient
                    SET status = :status,
                        read_at = COALESCE(read_at, :now),
                        delivered_at = COALESCE(delivered_at, :now),
                        updated_at = :now
                  WHERE tenant_id = :tenantId
                    AND user_id = :userId
                    AND notification_id IN (:ids)',
                [
                    'status' => 'read',
                    'now' => $now,
                    'tenantId' => $this->permissions->getTenantId(),
                    'userId' => $this->permissions->getUserId(),
                    'ids' => $normalizedIds,
                ],
                ['ids' => ArrayParameterType::INTEGER],
            );
        }

        return $this->acknowledgeFilteredForCurrentUser($filters, $now);
    }

    /**
     * @return array<string, array{user_id: string, user_name: string, source_type: string, source_key: string}>
     */
    private function resolveRecipients(string $tenantId, array $targetUsers, array $targetGroups): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT username, display_name, groups FROM auth_user WHERE tenant_id = :tenantId AND status = :status',
            ['tenantId' => $tenantId, 'status' => 'active'],
        );

        $normalizedUsers = array_fill_keys(array_map(static fn (string $item) => mb_strtolower($item), $targetUsers), true);
        $normalizedGroups = array_fill_keys(array_map(static fn (string $item) => mb_strtolower($item), $targetGroups), true);
        $resolved = [];

        foreach ($rows as $row) {
            $username = trim((string) ($row['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            $normalizedUsername = mb_strtolower($username);
            $groupList = $this->decodeStringList($row['groups'] ?? null);
            $matchedGroup = null;
            foreach ($groupList as $group) {
                if (isset($normalizedGroups[mb_strtolower($group)])) {
                    $matchedGroup = $group;
                    break;
                }
            }

            if (!isset($normalizedUsers[$normalizedUsername]) && $matchedGroup === null) {
                continue;
            }

            $resolved[$username] = [
                'user_id' => $username,
                'user_name' => trim((string) ($row['display_name'] ?? '')) ?: $username,
                'source_type' => isset($normalizedUsers[$normalizedUsername]) ? 'user' : 'group',
                'source_key' => isset($normalizedUsers[$normalizedUsername]) ? $username : (string) $matchedGroup,
            ];
        }

        return $resolved;
    }

    private function normalizeStatus(mixed $value): string
    {
        $status = mb_strtolower(trim((string) $value));
        return in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft';
    }

    private function normalizeSeverity(mixed $value): string
    {
        $severity = mb_strtolower(trim((string) $value));
        return in_array($severity, ['info', 'warning', 'error', 'success'], true) ? $severity : 'info';
    }

    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $value = $decoded;
                } else {
                    $value = preg_split('/[,;\\n]+/', $value) ?: [];
                }
            } catch (\Throwable) {
                $value = preg_split('/[,;\\n]+/', $value) ?: [];
            }
        }
        if (!is_array($value)) {
            return [];
        }

        $items = array_map(static fn ($item) => trim((string) $item), $value);
        $items = array_values(array_filter($items, static fn ($item) => $item !== ''));

        return array_values(array_unique($items));
    }

    private function decodeStringList(mixed $value): array
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                return $this->normalizeStringList($decoded);
            } catch (\Throwable) {
                return $this->normalizeStringList($value);
            }
        }

        return $this->normalizeStringList($value);
    }

    private function decodeJsonMap(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function normalizeNavigationQuery(mixed $query): array
    {
        if (!is_array($query)) {
            return [];
        }

        $normalized = [];
        foreach ($query as $key => $value) {
            $name = trim((string) $key);
            if ($name === '' || $value === null) {
                continue;
            }
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $normalized[$name] = $text;
        }

        return $normalized;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return (new \DateTimeImmutable((string) $value))->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatRecipientStatus(string $status): string
    {
        return match ($status) {
            'read' => 'Lida',
            'delivered' => 'Entregue',
            default => 'Pendente',
        };
    }

    private function normalizeCode(mixed $value, bool $isCreate): ?string
    {
        $code = trim((string) $value);
        if ($code === '' && $isCreate) {
            $code = 'notif_' . (new \DateTimeImmutable())->format('YmdHis');
        }
        return $code === '' ? null : mb_substr($code, 0, 120);
    }

    private function acknowledgeFilteredForCurrentUser(array $filters, string $now): int
    {
        if (!$this->hasNotificationFilter($filters)) {
            return $this->connection->executeStatement(
                'UPDATE runtime_notification_recipient
                    SET status = :status,
                        read_at = COALESCE(read_at, :now),
                        delivered_at = COALESCE(delivered_at, :now),
                        updated_at = :now
                  WHERE tenant_id = :tenantId
                    AND user_id = :userId
                    AND status IN (:statuses)',
                [
                    'status' => 'read',
                    'now' => $now,
                    'tenantId' => $this->permissions->getTenantId(),
                    'userId' => $this->permissions->getUserId(),
                    'statuses' => ['pending', 'delivered'],
                ],
                ['statuses' => ArrayParameterType::STRING],
            );
        }

        $query = $this->connection->createQueryBuilder()
            ->select('r.id')
            ->from('runtime_notification_recipient', 'r')
            ->innerJoin('r', 'runtime_notification', 'n', 'n.id = r.notification_id')
            ->where('r.tenant_id = :tenantId')
            ->andWhere('r.user_id = :userId')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('n.status = :notificationStatus')
            ->andWhere('(n.expires_at IS NULL OR n.expires_at > :filterNow)')
            ->setParameter('tenantId', $this->permissions->getTenantId())
            ->setParameter('userId', $this->permissions->getUserId())
            ->setParameter('statuses', ['pending', 'delivered'], ArrayParameterType::STRING)
            ->setParameter('notificationStatus', 'published')
            ->setParameter('filterNow', $now);

        $severity = trim((string) ($filters['severity'] ?? ''));
        if ($severity !== '') {
            $query->andWhere('n.severity = :severity')->setParameter('severity', $severity);
        }
        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $query->andWhere('n.category = :category')->setParameter('category', $category);
        }
        if (array_key_exists('actionRequired', $filters) && $filters['actionRequired'] !== null && $filters['actionRequired'] !== '') {
            $query->andWhere('n.action_required = :actionRequired')->setParameter('actionRequired', ($filters['actionRequired'] ?? false) === true);
        }

        $ids = array_map('intval', array_column($query->executeQuery()->fetchAllAssociative(), 'id'));
        if (!$ids) {
            return 0;
        }

        return $this->connection->executeStatement(
            'UPDATE runtime_notification_recipient
                SET status = :status,
                    read_at = COALESCE(read_at, :now),
                    delivered_at = COALESCE(delivered_at, :now),
                    updated_at = :now
              WHERE id IN (:ids)',
            [
                'status' => 'read',
                'now' => $now,
                'ids' => $ids,
            ],
            ['ids' => ArrayParameterType::INTEGER],
        );
    }

    private function hasNotificationFilter(array $filters): bool
    {
        return trim((string) ($filters['severity'] ?? '')) !== ''
            || trim((string) ($filters['category'] ?? '')) !== ''
            || array_key_exists('actionRequired', $filters);
    }
}
