<?php

namespace App\Builder;

use App\Entity\RuntimeAiMessage;
use App\Entity\RuntimeAiSession;
use App\Repository\RuntimeAiMessageRepository;
use App\Repository\RuntimeAiSessionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use Doctrine\ORM\EntityManagerInterface;

class BuilderAiSessionService
{
    public const PURPOSE_PROGRAM_BUILDER = 'program_builder';
    private const SESSION_TTL = '+2 hours';

    public function __construct(
        private readonly RuntimeAiSessionRepository $sessions,
        private readonly RuntimeAiMessageRepository $messages,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionResolver $permissions,
        private readonly ExternalBuilderContextService $externalContext,
    ) {
    }

    public function startOrResume(array $payload = []): RuntimeAiSession
    {
        $requestedSessionId = trim((string) ($payload['sessionId'] ?? ''));
        $forceNew = ($payload['forceNew'] ?? false) === true;
        $catalog = $this->catalogMetadata();

        if ($requestedSessionId !== '' && !$forceNew) {
            $session = $this->requireOwnedActive($requestedSessionId);
            $session
                ->setCatalogHash($catalog['hash'])
                ->setCatalogVersion($catalog['version'])
                ->setExpiresAt((new \DateTimeImmutable())->modify(self::SESSION_TTL))
                ->touch();
            $this->entityManager->flush();

            return $session;
        }

        if ($requestedSessionId !== '' && $forceNew) {
            try {
                $this->requireOwnedActive($requestedSessionId)
                    ->setStatus('closed')
                    ->touch();
                $this->entityManager->flush();
            } catch (RuntimeHttpException) {
                // Sessao antiga invalida/expirada nao deve impedir a abertura de uma nova conversa.
            }
        }

        $session = (new RuntimeAiSession())
            ->setSessionId('builder-ai-' . bin2hex(random_bytes(16)))
            ->setTenantId($this->permissions->getTenantId())
            ->setUserId($this->permissions->getUserId())
            ->setSubscriberCode($this->currentSubscriberCode())
            ->setPurpose(self::PURPOSE_PROGRAM_BUILDER)
            ->setCatalogHash($catalog['hash'])
            ->setCatalogVersion($catalog['version'])
            ->setExpiresAt((new \DateTimeImmutable())->modify(self::SESSION_TTL));

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    public function requireOwnedActive(string $sessionId): RuntimeAiSession
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new RuntimeHttpException('BUILDER_AI_SESSION_REQUIRED', 'Informe a sessao do assistente de IA.', 422);
        }

        $session = $this->sessions->findOneBySessionId($sessionId);
        if (!$session) {
            throw new RuntimeHttpException('BUILDER_AI_SESSION_NOT_FOUND', 'Sessao do assistente de IA nao encontrada.', 404);
        }

        $tenantId = $this->permissions->getTenantId();
        $userId = $this->permissions->getUserId();
        $subscriberCode = $this->currentSubscriberCode();
        if ($session->getTenantId() !== $tenantId || $session->getUserId() !== $userId || $session->getPurpose() !== self::PURPOSE_PROGRAM_BUILDER || (string) $session->getSubscriberCode() !== (string) $subscriberCode) {
            throw new RuntimeHttpException('BUILDER_AI_SESSION_FORBIDDEN', 'Esta sessao do assistente nao pertence ao usuario ou assinante atual.', 403);
        }

        if ($session->getStatus() !== 'active') {
            throw new RuntimeHttpException('BUILDER_AI_SESSION_INACTIVE', 'Sessao do assistente de IA encerrada.', 409, [
                'status' => $session->getStatus(),
            ]);
        }

        if ($session->isExpired()) {
            $session->setStatus('expired');
            $this->entityManager->flush();
            throw new RuntimeHttpException('BUILDER_AI_SESSION_EXPIRED', 'Sessao do assistente de IA expirada.', 409);
        }

        return $session;
    }

    public function appendMessage(RuntimeAiSession $session, string $role, string $content, array $normalizedPayload = [], array $diagnostics = []): RuntimeAiMessage
    {
        $message = (new RuntimeAiMessage())
            ->setSessionId($session->getSessionId())
            ->setRole($role)
            ->setContent($content)
            ->setNormalizedPayload($normalizedPayload)
            ->setDiagnostics($diagnostics);

        $session
            ->setExpiresAt((new \DateTimeImmutable())->modify(self::SESSION_TTL))
            ->touch();

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    public function updateDraft(RuntimeAiSession $session, ?array $draft, array $diagnostics = []): void
    {
        $session
            ->setCurrentDraft(is_array($draft) ? $draft : [])
            ->setCurrentDiagnostics($diagnostics)
            ->setExpiresAt((new \DateTimeImmutable())->modify(self::SESSION_TTL))
            ->touch();

        $this->entityManager->flush();
    }

    public function recentHistory(RuntimeAiSession $session, int $limit = 12): array
    {
        $history = [];
        foreach ($this->messages->findRecentForSession($session->getSessionId(), $limit) as $message) {
            $content = trim($message->getContent());
            if ($content === '') {
                continue;
            }
            $history[] = [
                'role' => $message->getRole() === 'assistant' ? 'assistant' : ($message->getRole() === 'system' ? 'system' : 'user'),
                'text' => $content,
            ];
        }

        return $history;
    }

    public function sessionPayload(RuntimeAiSession $session): array
    {
        return [
            'sessionId' => $session->getSessionId(),
            'purpose' => $session->getPurpose(),
            'catalogHash' => $session->getCatalogHash(),
            'catalogVersion' => $session->getCatalogVersion(),
            'status' => $session->getStatus(),
            'expiresAt' => $session->getExpiresAt()->format(DATE_ATOM),
            'lastSeenAt' => $session->getLastSeenAt()->format(DATE_ATOM),
            'currentDraft' => $session->getCurrentDraft(),
            'currentDiagnostics' => $session->getCurrentDiagnostics(),
        ];
    }

    public function catalogMetadata(): array
    {
        $context = $this->externalContext->buildContextPayload();
        $version = (string) ($context['catalog']['version'] ?? $context['schemaVersion'] ?? '1.0');
        $hash = hash('sha256', (string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'version' => $version,
            'hash' => $hash,
            'context' => $context,
        ];
    }

    private function currentSubscriberCode(): ?string
    {
        $user = $this->permissions->getCurrentUserPayload();
        $subscriber = is_array($user['currentSubscriber'] ?? null) ? $user['currentSubscriber'] : [];
        $code = trim((string) ($subscriber['code'] ?? $subscriber['id'] ?? ''));

        return $code !== '' ? $code : null;
    }
}
