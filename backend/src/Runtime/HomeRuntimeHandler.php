<?php

namespace App\Runtime;

class HomeRuntimeHandler
{
    public function __construct(
        private readonly RuntimeNotificationService $notifications,
        private readonly HomeSupportService $support,
    ) {
    }

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
            'chatEvents' => $this->chatEvents($payload),
            'supportOnlineUsers' => $this->supportOnlineUsers($payload),
            'supportHistory' => $this->supportHistory($payload),
            'supportSend' => $this->supportSend($payload),
            'supportCreateRequest' => $this->supportCreateRequest($payload),
            'supportRequestStatus' => $this->supportRequestStatus($payload),
            'supportEvents' => $this->supportEvents($payload),
            'notifications' => $this->notifications($payload),
            'notificationsAck' => $this->notificationsAck($payload),
            'alerts' => $this->alerts($payload),
            'requests' => $this->requests($payload),
            'jobs' => $this->jobs($payload),
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
        $response = $this->support->listChatContacts();
        $response['context'] = $this->normalizeContext($payload);

        return $response;
    }

    private function history(array $payload): array
    {
        $recipient = is_array($payload['recipient'] ?? null) ? $payload['recipient'] : [];
        $response = $this->support->chatHistory((string) ($recipient['id'] ?? ''));
        $response['context'] = $this->normalizeContext($payload);

        return $response;
    }

    private function send(array $payload): array
    {
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $recipient = is_array($payload['recipient'] ?? null) ? $payload['recipient'] : [];
        $context = $this->normalizeContext($payload);
        $response = $this->support->sendChatMessage($message, $recipient, $context);
        $response['context'] = $context;

        return $response;
    }

    private function chatEvents(array $payload): array
    {
        $recipient = is_array($payload['recipient'] ?? null) ? $payload['recipient'] : [];
        $afterId = max(0, (int) ($payload['afterId'] ?? 0));
        $response = $this->support->chatEvents((string) ($recipient['id'] ?? ''), $afterId);
        $response['context'] = $this->normalizeContext($payload);

        return $response;
    }

    private function supportOnlineUsers(array $payload): array
    {
        $context = $this->normalizeContext($payload);
        $sector = is_array($payload['sector'] ?? null) ? $payload['sector'] : [];
        $response = $this->support->listSupportOnlineUsers((string) ($sector['id'] ?? ''));
        $response['context'] = $context;

        return $response;
    }

    private function supportHistory(array $payload): array
    {
        $attendant = is_array($payload['attendant'] ?? null) ? $payload['attendant'] : [];
        $response = $this->support->supportHistory((string) ($attendant['id'] ?? ''));
        $response['context'] = $this->normalizeContext($payload);

        return $response;
    }

    private function supportSend(array $payload): array
    {
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $attendant = is_array($payload['attendant'] ?? null) ? $payload['attendant'] : [];
        $context = $this->normalizeContext($payload);
        $response = $this->support->sendSupportMessage($message, $attendant, $context);
        $response['context'] = $context;

        return $response;
    }

    private function supportCreateRequest(array $payload): array
    {
        $context = $this->normalizeContext($payload);
        $sector = is_array($payload['sector'] ?? null) ? $payload['sector'] : [];
        $priority = trim((string) ($payload['priority'] ?? 'normal'));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $response = $this->support->createSupportRequest($sector, $priority, $subject, $description, $context);
        $response['programId'] = $context['programId'];
        $response['programCode'] = $context['programCode'];
        $response['programTitle'] = $this->contextProgramTitle($context);
        $response['context'] = $context;

        return $response;
    }

    private function supportRequestStatus(array $payload): array
    {
        $response = $this->support->supportRequestStatus(trim((string) ($payload['protocol'] ?? '')));
        $response['context'] = $this->normalizeContext($payload);

        return $response;
    }

    private function supportEvents(array $payload): array
    {
        $attendant = is_array($payload['attendant'] ?? null) ? $payload['attendant'] : [];
        $sector = is_array($payload['sector'] ?? null) ? $payload['sector'] : [];
        $afterId = max(0, (int) ($payload['afterId'] ?? 0));
        $protocol = trim((string) ($payload['protocol'] ?? ''));
        $response = $this->support->supportEvents(
            (string) ($attendant['id'] ?? ''),
            (string) ($sector['id'] ?? ''),
            $protocol !== '' ? $protocol : null,
            $afterId
        );
        $response['context'] = $this->normalizeContext($payload);

        return $response;
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
                    'technicalProperties' => [
                        array_filter([
                            'section' => 'Contexto',
                            'labelKey' => 'technical.label.program',
                            'label' => 'Programa',
                            'value' => $context['programId'] ?: '-',
                            'action' => $context['programId'] ? [
                                'type' => 'openProgram',
                                'programId' => $context['programId'],
                                'label' => 'Abrir programa',
                            ] : null,
                        ], static fn ($value) => $value !== null),
                        array_filter([
                            'section' => 'Contexto',
                            'labelKey' => 'technical.label.screen_id',
                            'label' => 'Screen ID',
                            'value' => $context['programScreenId'] ?: '-',
                            'action' => $context['programScreenId'] ? [
                                'type' => 'openScreen',
                                'screenId' => $context['programScreenId'],
                                'label' => 'Abrir tela',
                            ] : null,
                        ], static fn ($value) => $value !== null),
                        ['section' => 'Runtime', 'labelKey' => 'technical.label.origin', 'label' => 'Origem', 'value' => 'Symfony/API Platform'],
                    ],
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
                    'technicalProperties' => [
                        array_filter([
                            'section' => 'Contexto',
                            'labelKey' => 'technical.label.program',
                            'label' => 'Programa',
                            'value' => $context['programId'] ?: '-',
                            'action' => $context['programId'] ? [
                                'type' => 'openProgram',
                                'programId' => $context['programId'],
                                'label' => 'Abrir programa',
                            ] : null,
                        ], static fn ($value) => $value !== null),
                        ['section' => 'Contexto', 'labelKey' => 'technical.label.module', 'label' => 'Modulo', 'value' => $context['moduleId'] ?: '-'],
                        ['section' => 'Fluxo', 'labelKey' => 'technical.label.type', 'label' => 'Tipo', 'value' => 'Solicitacao pendente', 'critical' => true],
                    ],
                ],
            ],
            'context' => $context,
        ];
    }

    private function jobs(array $payload): array
    {
        $context = $this->normalizeContext($payload);

        return [
            'items' => [
                [
                    'id' => 'runtime-jobs-entry',
                    'title' => 'Consulta de jobs disponivel',
                    'description' => 'Abra a tela Meus Jobs para consultar execucoes assincronas do runtime.',
                    'type' => 'Runtime',
                    'status' => 'Info',
                    'programId' => 'runtime-jobs',
                    'programTitle' => 'Jobs Assincronos',
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

    private function notifications(array $payload): array
    {
        $includeRead = ($payload['includeRead'] ?? false) === true;
        $response = $this->notifications->listForCurrentUser($includeRead, [
            'severity' => trim((string) ($payload['severity'] ?? '')),
            'category' => trim((string) ($payload['category'] ?? '')),
            'actionRequired' => array_key_exists('actionRequired', $payload) ? ($payload['actionRequired'] ?? false) === true : null,
            'unreadOnly' => ($payload['unreadOnly'] ?? false) === true,
            'limit' => (int) ($payload['limit'] ?? 30),
        ]);
        $response['context'] = $this->normalizeContext($payload);

        return $response;
    }

    private function notificationsAck(array $payload): array
    {
        $ids = is_array($payload['ids'] ?? null) ? $payload['ids'] : [$payload['id'] ?? null];
        $count = $this->notifications->acknowledgeForCurrentUser($ids, [
            'severity' => trim((string) ($payload['severity'] ?? '')),
            'category' => trim((string) ($payload['category'] ?? '')),
            'actionRequired' => array_key_exists('actionRequired', $payload) ? ($payload['actionRequired'] ?? false) === true : null,
        ]);

        return [
            'ok' => true,
            'count' => $count,
            'context' => $this->normalizeContext($payload),
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
