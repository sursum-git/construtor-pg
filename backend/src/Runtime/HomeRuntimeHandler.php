<?php

namespace App\Runtime;

class HomeRuntimeHandler
{
    private const CONTEXT_STRING_FIELDS = [
        'appId',
        'appTitle',
        'programId',
        'programCode',
        'programTitle',
        'programScreenId',
        'programType',
        'moduleId',
    ];

    private const CURRENT_PROGRAM_STRING_FIELDS = [
        'id',
        'code',
        'programId',
        'programCode',
        'title',
        'screenId',
        'type',
        'moduleId',
        'version',
        'subtitle',
    ];

    public function handle(string $operation, array $payload): array
    {
        return match ($operation) {
            'contacts' => $this->contacts($payload),
            'history' => $this->history($payload),
            'send' => $this->send($payload),
            'supportOnlineUsers' => $this->supportOnlineUsers($payload),
            'supportCreateRequest' => $this->supportCreateRequest($payload),
            'supportRequestStatus' => $this->supportRequestStatus($payload),
            'alerts' => $this->alerts($payload),
            'requests' => $this->requests($payload),
            'aiHistory' => $this->aiHistory($payload),
            'aiSend' => $this->aiSend($payload),
            'subscriberChange' => $this->subscriberChange($payload),
            default => throw new RuntimeHttpException('HOME_OPERATION_NOT_FOUND', 'Operacao da Home nao encontrada.', 404, [
                'operation' => $operation,
            ]),
        };
    }

    private function contacts(array $payload): array
    {
        return [
            'items' => [
                ['id' => 'ana', 'name' => 'Ana Suporte', 'email' => 'ana@example.com', 'initials' => 'AS'],
                ['id' => 'bruno', 'name' => 'Bruno Operacoes', 'email' => 'bruno@example.com', 'initials' => 'BO'],
            ],
            'context' => $this->normalizeContext($payload),
        ];
    }

    private function history(array $payload): array
    {
        return [
            'messages' => [],
            'context' => $this->normalizeContext($payload),
        ];
    }

    private function send(array $payload): array
    {
        $context = $this->normalizeContext($payload);
        $programTitle = $this->contextProgramTitle($context);

        return [
            'messages' => [
                [
                    'text' => 'Mensagem recebida pelo backend para ' . $programTitle . '.',
                    'authorId' => $payload['recipient']['id'] ?? 'sistema',
                    'authorName' => $payload['recipient']['name'] ?? 'Sistema',
                    'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
            ],
            'context' => $context,
        ];
    }

    private function supportOnlineUsers(array $payload): array
    {
        $context = $this->normalizeContext($payload);
        $sectorId = (string) ($payload['sector']['id'] ?? '');
        if ($sectorId === 'financeiro') {
            return [
                'items' => [],
                'context' => $context,
            ];
        }

        return [
            'items' => [
                ['id' => 'suporte-1', 'name' => 'Atendente Online', 'sectorId' => $sectorId ?: 'suporte'],
            ],
            'context' => $context,
        ];
    }

    private function supportCreateRequest(array $payload): array
    {
        $context = $this->normalizeContext($payload);

        return [
            'ok' => true,
            'protocol' => 'ATD-' . (new \DateTimeImmutable())->format('YmdHis'),
            'sectorId' => $payload['sector']['id'] ?? 'suporte',
            'status' => 'aberta',
            'programId' => $context['programId'],
            'programCode' => $context['programCode'],
            'programTitle' => $this->contextProgramTitle($context),
            'context' => $context,
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    private function supportRequestStatus(array $payload): array
    {
        $context = $this->normalizeContext($payload);

        return [
            'status' => 'aberta',
            'assignedTo' => null,
            'context' => $context,
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    private function alerts(array $payload): array
    {
        $context = $this->normalizeContext($payload);
        $programTitle = $this->contextProgramTitle($context);

        return [
            'items' => [
                [
                    'id' => 'runtime-online',
                    'title' => 'Backend runtime ativo',
                    'description' => 'A Home esta consumindo dados do Symfony/API Platform para ' . $programTitle . '.',
                    'type' => 'Sistema',
                    'status' => 'Info',
                    'programId' => $context['programId'],
                    'programTitle' => $programTitle,
                    'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
            ],
            'context' => $context,
        ];
    }

    private function requests(array $payload): array
    {
        $context = $this->normalizeContext($payload);
        $programTitle = $this->contextProgramTitle($context);

        return [
            'items' => [
                [
                    'id' => 'builder-next-step',
                    'title' => 'Cadastrar entidade no construtor',
                    'description' => 'Proximo passo: criar telas para cadastrar entidades e gerar metadados. Contexto: ' . $programTitle . '.',
                    'type' => 'Construtor',
                    'status' => 'Pendente',
                    'programId' => $context['programId'],
                    'programTitle' => $programTitle,
                    'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
            ],
            'context' => $context,
        ];
    }

    private function aiHistory(array $payload): array
    {
        return [
            'messages' => [],
            'context' => $this->normalizeContext($payload),
        ];
    }

    private function aiSend(array $payload): array
    {
        $context = $this->normalizeContext($payload);
        $programTitle = $this->contextProgramTitle($context);
        $text = trim((string) ($payload['message']['text'] ?? ''));

        return [
            'messages' => [
                [
                    'text' => $text === '' ? 'Informe uma mensagem para a IA.' : 'Recebi sua pergunta sobre ' . $programTitle . ': ' . $text,
                    'authorId' => 'ia',
                    'authorName' => 'IA',
                    'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
            ],
            'context' => $context,
        ];
    }

    private function subscriberChange(array $payload): array
    {
        $subscriber = is_array($payload['subscriber'] ?? null) ? $payload['subscriber'] : [];
        $subscriberId = (string) ($payload['subscriberId'] ?? $subscriber['id'] ?? '');

        if ($subscriberId === '') {
            throw new RuntimeHttpException('SUBSCRIBER_REQUIRED', 'Informe o assinante.', 422);
        }

        return [
            'ok' => true,
            'currentSubscriber' => array_replace([
                'id' => $subscriberId,
                'name' => $subscriber['name'] ?? $subscriber['displayName'] ?? $subscriberId,
                'label' => $subscriber['label'] ?? 'Assinante',
            ], $subscriber),
        ];
    }

    private function normalizeContext(array $payload): array
    {
        $source = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $currentProgram = is_array($source['currentProgram'] ?? null) ? $source['currentProgram'] : [];

        $context = [];
        foreach (self::CONTEXT_STRING_FIELDS as $field) {
            $context[$field] = $this->cleanContextString($source[$field] ?? '');
        }

        $program = [];
        foreach (self::CURRENT_PROGRAM_STRING_FIELDS as $field) {
            $program[$field] = $this->cleanContextString($currentProgram[$field] ?? '');
        }

        $context['programId'] = $context['programId'] ?: $program['programId'] ?: $program['id'];
        $context['programCode'] = $context['programCode'] ?: $program['programCode'] ?: $program['code'] ?: $context['programId'];
        $context['programTitle'] = $context['programTitle'] ?: $program['title'];
        $context['programScreenId'] = $context['programScreenId'] ?: $program['screenId'];
        $context['programType'] = $context['programType'] ?: $program['type'];
        $context['moduleId'] = $context['moduleId'] ?: $program['moduleId'];

        $program['id'] = $program['id'] ?: $context['programId'];
        $program['programId'] = $program['programId'] ?: $context['programId'];
        $program['code'] = $program['code'] ?: $context['programCode'];
        $program['programCode'] = $program['programCode'] ?: $context['programCode'];
        $program['title'] = $program['title'] ?: $context['programTitle'];
        $program['screenId'] = $program['screenId'] ?: $context['programScreenId'];
        $program['type'] = $program['type'] ?: $context['programType'];
        $program['moduleId'] = $program['moduleId'] ?: $context['moduleId'];
        $context['currentProgram'] = $program;

        return $context;
    }

    private function cleanContextString(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 160);
    }

    private function contextProgramTitle(array $context): string
    {
        return $context['programTitle'] !== '' ? $context['programTitle'] : 'programa atual';
    }
}
