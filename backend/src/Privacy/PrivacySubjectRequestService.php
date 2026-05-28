<?php

namespace App\Privacy;

use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEventService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeTransactionService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PrivacySubjectRequestService
{
    private const REQUEST_TYPES = ['access', 'correction', 'portability', 'anonymization', 'erasure', 'blocking', 'opposition', 'consent_revocation'];
    private const SOURCE_CHANNELS = ['public_page', 'email', 'phone', 'whatsapp', 'external_form', 'in_person', 'logged_area', 'manual'];

    public function __construct(
        private readonly Connection $connection,
        private readonly MailerInterface $mailer,
        private readonly RuntimeNotificationService $notifications,
        private readonly RuntimeEventService $events,
        private readonly RuntimeTransactionService $transactions,
        private readonly PermissionResolver $permissions,
    ) {
    }

    public function startPublicRequest(array $payload): array
    {
        $tenantId = $this->clean($payload['tenantId'] ?? 'default', 80) ?: 'default';
        $email = mb_strtolower($this->clean($payload['requesterEmail'] ?? $payload['email'] ?? '', 180));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeHttpException('PRIVACY_REQUEST_EMAIL_INVALID', 'Informe um e-mail valido para validar a solicitacao.', 422);
        }

        $type = $this->normalizeRequestType($payload['requestType'] ?? '');
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+15 minutes');
        $protocol = $this->newProtocol();
        $code = (string) random_int(100000, 999999);

        $this->connection->insert('privacy_subject_request', [
            'protocol' => $protocol,
            'tenant_id' => $tenantId,
            'source_channel' => 'public_page',
            'requester_name' => $this->nullableClean($payload['requesterName'] ?? $payload['name'] ?? null, 160),
            'requester_email' => $email,
            'requester_document' => $this->nullableClean($payload['requesterDocument'] ?? $payload['document'] ?? null, 40),
            'subject_identifier' => $this->nullableClean($payload['subjectIdentifier'] ?? null, 160),
            'request_type' => $type,
            'description' => $this->nullableText($payload['description'] ?? null),
            'status' => 'awaiting_validation',
            'priority' => 'high',
            'due_at' => $now->modify('+15 days')->format('Y-m-d H:i:s'),
            'analysis_result' => '{}',
            'evidence' => $this->json(['publicValidation' => 'pending']),
            'metadata' => $this->json([
                'remoteAddr' => $payload['_request']['remoteAddr'] ?? null,
                'userAgent' => $payload['_request']['userAgent'] ?? null,
            ]),
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);
        $requestId = (int) $this->connection->lastInsertId();
        $this->connection->insert('privacy_subject_request_verification', [
            'request_id' => $requestId,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        $this->sendValidationEmail($email, $protocol, $code, $expiresAt);
        $this->logOperational('privacy.subject_request.validation_sent', 'Codigo de validacao LGPD enviado.', [
            'tenantId' => $tenantId,
            'protocol' => $protocol,
            'requestType' => $type,
        ]);

        return [
            'protocol' => $protocol,
            'status' => 'awaiting_validation',
            'expiresAt' => $expiresAt->format(DATE_ATOM),
            'message' => 'Enviamos um codigo de validacao para o e-mail informado.',
        ];
    }

    public function confirmPublicRequest(array $payload): array
    {
        $protocol = $this->clean($payload['protocol'] ?? '', 40);
        $email = mb_strtolower($this->clean($payload['requesterEmail'] ?? $payload['email'] ?? '', 180));
        $code = $this->clean($payload['code'] ?? '', 20);
        $request = $this->findRequestByProtocolAndEmail($protocol, $email);
        if (!$request) {
            throw new RuntimeHttpException('PRIVACY_REQUEST_NOT_FOUND', 'Solicitacao LGPD nao encontrada para o protocolo e e-mail informados.', 404);
        }

        $verification = $this->connection->fetchAssociative(
            'SELECT * FROM privacy_subject_request_verification WHERE request_id = :id ORDER BY id DESC LIMIT 1',
            ['id' => (int) $request['id']],
        );
        if (!$verification || !empty($verification['confirmed_at'])) {
            throw new RuntimeHttpException('PRIVACY_REQUEST_ALREADY_VALIDATED', 'Esta solicitacao ja foi validada.', 409);
        }
        if ((int) $verification['attempts'] >= (int) $verification['max_attempts']) {
            throw new RuntimeHttpException('PRIVACY_REQUEST_VALIDATION_BLOCKED', 'Limite de tentativas excedido.', 429);
        }
        if (new \DateTimeImmutable((string) $verification['expires_at']) < new \DateTimeImmutable()) {
            throw new RuntimeHttpException('PRIVACY_REQUEST_CODE_EXPIRED', 'Codigo de validacao expirado.', 410);
        }

        if (!password_verify($code, (string) $verification['code_hash'])) {
            $this->connection->update('privacy_subject_request_verification', [
                'attempts' => (int) $verification['attempts'] + 1,
            ], ['id' => (int) $verification['id']]);
            throw new RuntimeHttpException('PRIVACY_REQUEST_CODE_INVALID', 'Codigo de validacao incorreto.', 422);
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->update('privacy_subject_request_verification', [
            'confirmed_at' => $now,
        ], ['id' => (int) $verification['id']]);
        $this->connection->update('privacy_subject_request', [
            'status' => 'pending',
            'priority' => 'high',
            'validated_at' => $now,
            'updated_at' => $now,
            'evidence' => $this->json(['publicValidation' => 'confirmed', 'validatedAt' => $now]),
        ], ['id' => (int) $request['id']]);

        $request = $this->connection->fetchAssociative('SELECT * FROM privacy_subject_request WHERE id = :id', ['id' => (int) $request['id']]) ?: $request;
        $this->publishCreated($request, 'public_page');

        return [
            'protocol' => $protocol,
            'status' => 'pending',
            'priority' => 'high',
            'message' => 'Solicitacao validada e encaminhada para atendimento prioritario.',
        ];
    }

    public function publicStatus(string $protocol, string $email): array
    {
        $request = $this->findRequestByProtocolAndEmail($protocol, mb_strtolower($email));
        if (!$request || empty($request['validated_at'])) {
            throw new RuntimeHttpException('PRIVACY_REQUEST_STATUS_NOT_AVAILABLE', 'Nao foi possivel consultar o protocolo com os dados informados.', 404);
        }

        return [
            'protocol' => (string) $request['protocol'],
            'status' => (string) $request['status'],
            'priority' => (string) $request['priority'],
            'requestType' => (string) $request['request_type'],
            'dueAt' => $this->formatDate($request['due_at'] ?? null),
            'closedAt' => $this->formatDate($request['closed_at'] ?? null),
            'decision' => $request['decision'] ?? null,
            'retentionBlocked' => (bool) $request['retention_blocked'],
        ];
    }

    public function prepareSubjectRequestValues(array $values, bool $isCreate): array
    {
        if ($isCreate || array_key_exists('protocol', $values)) {
            $values['protocol'] = $this->clean($values['protocol'] ?? '', 40) ?: $this->newProtocol();
        }
        if ($isCreate || array_key_exists('tenant_id', $values)) {
            $values['tenant_id'] = $this->clean($values['tenant_id'] ?? $this->permissions->getTenantId(), 80) ?: $this->permissions->getTenantId();
        }
        if ($isCreate || array_key_exists('source_channel', $values)) {
            $values['source_channel'] = $this->normalizeSourceChannel($values['source_channel'] ?? 'manual');
        }
        if ($isCreate || array_key_exists('request_type', $values)) {
            $values['request_type'] = $this->normalizeRequestType($values['request_type'] ?? '');
        }
        if ($isCreate || array_key_exists('requester_email', $values)) {
            $email = mb_strtolower($this->clean($values['requester_email'] ?? '', 180));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeHttpException('PRIVACY_REQUEST_EMAIL_INVALID', 'Informe um e-mail valido.', 422);
            }
            $values['requester_email'] = $email;
        }
        if ($isCreate || array_key_exists('status', $values)) {
            $values['status'] = $this->normalizeStatus($values['status'] ?? 'pending');
        }
        if ($isCreate || array_key_exists('priority', $values)) {
            $values['priority'] = $this->normalizePriority($values['priority'] ?? 'high');
        }
        if ($isCreate && empty($values['due_at'])) {
            $values['due_at'] = (new \DateTimeImmutable('+15 days'))->format('Y-m-d H:i:s');
        }
        if ($isCreate && empty($values['analysis_result'])) {
            $values['analysis_result'] = '{}';
        }
        if ($isCreate && empty($values['evidence'])) {
            $values['evidence'] = $this->json(['manualEntry' => ($values['source_channel'] ?? '') !== 'public_page']);
        }
        if ($isCreate && empty($values['metadata'])) {
            $values['metadata'] = '{}';
        }

        return $values;
    }

    public function afterSubjectRequestCreated(array $request): void
    {
        if (($request['status'] ?? '') === 'awaiting_validation') {
            return;
        }
        $this->publishCreated($request, (string) ($request['source_channel'] ?? 'manual'));
    }

    private function publishCreated(array $request, string $sourceChannel): void
    {
        $protocol = (string) ($request['protocol'] ?? '');
        $tenantId = (string) ($request['tenant_id'] ?? 'default');
        $this->logOperational('privacy.subject_request.created', 'Solicitacao LGPD criada.', [
            'tenantId' => $tenantId,
            'protocol' => $protocol,
            'sourceChannel' => $sourceChannel,
            'requestType' => $request['request_type'] ?? null,
        ]);

        $this->notifications->createAdministrativeNotification(
            'Solicitacao LGPD prioritaria',
            'Protocolo ' . $protocol . ' aguarda triagem: ' . $this->requestTypeLabel((string) ($request['request_type'] ?? '')),
            [
                'tenantId' => $tenantId,
                'category' => 'privacidade',
                'severity' => 'error',
                'code' => 'privacy.subject_request.' . $protocol,
                'targetGroups' => ['admin', 'privacy', 'dpo'],
                'targetUserIds' => [],
                'actionRequired' => true,
                'linkProgramId' => 'admin-lgpd-solicitacoes',
                'linkScreenId' => 'admin.lgpd-solicitacoes',
                'metadata' => [
                    'protocol' => $protocol,
                    'requestType' => $request['request_type'] ?? null,
                    'sourceChannel' => $sourceChannel,
                    'actionLabel' => 'Abrir solicitacao LGPD',
                    'actionScreenId' => 'admin.lgpd-solicitacoes',
                    'actionQuery' => ['protocol' => $protocol],
                ],
            ]
        );

        $this->events->publish('privacy.subject_request.created', [
            'tenantId' => $tenantId,
            'userId' => $this->permissions->getUserId(),
            'screenId' => 'admin.lgpd-solicitacoes',
            'programCode' => 'admin-lgpd-solicitacoes',
            'entityCode' => 'privacy_subject_request',
            'recordId' => $request['id'] ?? null,
            'operation' => 'create',
            'protocol' => $protocol,
            'requestType' => $request['request_type'] ?? null,
            'sourceChannel' => $sourceChannel,
            'priority' => $request['priority'] ?? 'high',
            'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], [
            'tenantId' => $tenantId,
            'source' => 'privacy',
            'screenId' => 'admin.lgpd-solicitacoes',
            'programCode' => 'admin-lgpd-solicitacoes',
            'entityCode' => 'privacy_subject_request',
            'recordId' => $request['id'] ?? null,
            'operation' => 'create',
        ]);
    }

    private function logOperational(string $eventType, string $message, array $metadata): void
    {
        if ($this->transactions->getCurrent()) {
            $this->transactions->log($eventType, $message, metadata: $metadata);
            return;
        }

        $this->transactions->beginOperational([
            'tenantId' => $metadata['tenantId'] ?? 'default',
            'screenId' => 'admin.lgpd-solicitacoes',
            'programId' => 'admin-lgpd-solicitacoes',
            'entityCode' => 'privacy_subject_request',
            'recordId' => $metadata['protocol'] ?? null,
            'endpointId' => 'privacy.subject_request',
            'actionId' => $eventType,
            'operation' => $eventType,
            'source' => 'privacy',
        ]);
        $this->transactions->log($eventType, $message, metadata: $metadata);
        $this->transactions->success();
        $this->transactions->clear();
    }

    private function findRequestByProtocolAndEmail(string $protocol, string $email): ?array
    {
        if ($protocol === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM privacy_subject_request WHERE protocol = :protocol AND requester_email = :email LIMIT 1',
            ['protocol' => $protocol, 'email' => mb_strtolower($email)],
        );

        return $row ?: null;
    }

    private function sendValidationEmail(string $email, string $protocol, string $code, \DateTimeImmutable $expiresAt): void
    {
        $message = (new Email())
            ->from('nao-responda@construtor.local')
            ->to($email)
            ->subject('Codigo de validacao LGPD')
            ->text(
                "Recebemos uma solicitacao LGPD.\n\n"
                . "Protocolo: {$protocol}\n"
                . "Codigo: {$code}\n"
                . "Validade: " . $expiresAt->format('d/m/Y H:i') . "\n\n"
                . "Se voce nao solicitou, ignore esta mensagem."
            );
        $this->mailer->send($message);
    }

    private function newProtocol(): string
    {
        return 'LGPD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function normalizeRequestType(mixed $value): string
    {
        $type = mb_strtolower($this->clean($value, 40));
        if (!in_array($type, self::REQUEST_TYPES, true)) {
            throw new RuntimeHttpException('PRIVACY_REQUEST_TYPE_INVALID', 'Tipo de solicitacao LGPD invalido.', 422);
        }
        return $type;
    }

    private function normalizeSourceChannel(mixed $value): string
    {
        $channel = mb_strtolower($this->clean($value, 40));
        return in_array($channel, self::SOURCE_CHANNELS, true) ? $channel : 'manual';
    }

    private function normalizeStatus(mixed $value): string
    {
        $status = mb_strtolower($this->clean($value, 40));
        return in_array($status, ['awaiting_validation', 'pending', 'in_review', 'approved', 'partially_approved', 'rejected', 'executed', 'closed'], true) ? $status : 'pending';
    }

    private function normalizePriority(mixed $value): string
    {
        $priority = mb_strtolower($this->clean($value, 30));
        return in_array($priority, ['normal', 'high', 'urgent'], true) ? $priority : 'high';
    }

    private function requestTypeLabel(string $type): string
    {
        return match ($type) {
            'access' => 'acesso aos dados',
            'correction' => 'correcao',
            'portability' => 'portabilidade',
            'anonymization' => 'anonimizacao',
            'erasure' => 'eliminacao',
            'blocking' => 'bloqueio',
            'opposition' => 'oposicao',
            'consent_revocation' => 'revogacao de consentimento',
            default => 'solicitacao LGPD',
        };
    }

    private function clean(mixed $value, int $limit = 160): string
    {
        return mb_substr(trim(preg_replace('/[[:cntrl:]]+/', ' ', (string) $value) ?: ''), 0, $limit);
    }

    private function nullableClean(mixed $value, int $limit): ?string
    {
        $text = $this->clean($value, $limit);
        return $text === '' ? null : $text;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function json(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function formatDate(mixed $value): ?string
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
}
