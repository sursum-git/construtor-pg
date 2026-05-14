<?php

namespace App\Builder;

use App\Runtime\RuntimeHttpException;
use Psr\Log\LoggerInterface;

class BuilderAiService
{
    public function __construct(
        private readonly BuilderAiSettingsService $settings,
        private readonly ExternalBuilderContextService $externalContext,
        private readonly ProgramBuilderService $builder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function startSession(array $payload = []): array
    {
        $settings = $this->settings->getUiSettings();

        return [
            'sessionId' => 'builder-ai-' . bin2hex(random_bytes(8)),
            'settings' => $settings,
            'messages' => [[
                'id' => 'builder-ai-welcome',
                'text' => 'Descreva a tabela ou o cadastro que voce quer criar. Eu monto um rascunho CRUD e voce revisa no construtor.',
                'authorId' => 'ia-builder',
                'authorName' => $settings['agentName'] ?: 'Assistente do construtor',
                'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]],
            'draft' => null,
            'diagnostics' => [],
            'readyToApply' => false,
        ];
    }

    public function sendMessage(array $payload): array
    {
        $settings = $this->settings->resolveOperationalSettings();
        $text = trim((string) (($payload['message']['text'] ?? null) ?: ($payload['text'] ?? '')));
        if ($text === '') {
            throw new RuntimeHttpException('BUILDER_AI_MESSAGE_REQUIRED', 'Informe a mensagem para o assistente de IA.', 422);
        }

        $history = $this->normalizeHistory($payload['history'] ?? []);
        $context = $this->externalContext->buildContextPayload();
        $result = $settings['provider'] === 'mock'
            ? $this->sendMock($text, $history, $context, $settings)
            : $this->sendOpenAiCompatible($text, $history, $context, $settings);

        $validatedDraft = null;
        $diagnostics = is_array($result['diagnostics'] ?? null) ? $result['diagnostics'] : [];
        $readyToApply = ($result['readyToApply'] ?? false) === true;
        $missingInputs = array_values(array_filter(array_map('strval', is_array($result['missingInputs'] ?? null) ? $result['missingInputs'] : [])));
        $draft = is_array($result['draft'] ?? null) ? $result['draft'] : null;

        if ($draft && is_array($draft['entityDraft'] ?? null) && is_array($draft['programDraft'] ?? null)) {
            try {
                $validatedDraft = $this->builder->validateExternalDraft(['payload' => $draft]);
                $diagnostics = array_merge($diagnostics, $validatedDraft['diagnostics'] ?? []);
                $readyToApply = true;
            } catch (\Throwable $error) {
                $readyToApply = false;
                $diagnostics[] = [
                    'level' => 'error',
                    'message' => $error->getMessage(),
                    'source' => 'builder',
                ];
            }
        }

        $assistantMessage = trim((string) ($result['assistantMessage'] ?? ''));
        if ($assistantMessage === '') {
            $assistantMessage = $readyToApply
                ? 'Montei um rascunho inicial. Revise e carregue na modelagem quando estiver satisfeito.'
                : 'Ainda faltam alguns dados para concluir o rascunho.';
        }

        return [
            'sessionId' => trim((string) ($payload['sessionId'] ?? '')),
            'messages' => [[
                'id' => 'builder-ai-' . bin2hex(random_bytes(8)),
                'text' => $assistantMessage,
                'authorId' => 'ia-builder',
                'authorName' => $settings['agentName'] ?: 'Assistente do construtor',
                'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]],
            'draft' => $validatedDraft ? ($validatedDraft['normalizedDraft'] ?? null) : $draft,
            'generatedDefinition' => $validatedDraft['generatedDefinition'] ?? null,
            'diagnostics' => $diagnostics,
            'readyToApply' => $readyToApply,
            'missingInputs' => $missingInputs,
        ];
    }

    public function transcribe(array $payload): array
    {
        $settings = $this->settings->resolveOperationalSettings();
        $transcriptText = trim((string) ($payload['transcriptText'] ?? ''));
        if ($transcriptText !== '') {
            return ['transcript' => $transcriptText];
        }

        if ($settings['provider'] === 'mock') {
            return ['transcript' => 'Crie uma tabela de tipos de produto com codigo, descricao e ativo.'];
        }

        if (!$settings['transcriptionEnabled']) {
            throw new RuntimeHttpException('BUILDER_AI_TRANSCRIPTION_DISABLED', 'A transcricao por audio nao esta habilitada para o assistente.', 422);
        }

        $audioBase64 = trim((string) ($payload['audioBase64'] ?? ''));
        if ($audioBase64 === '') {
            throw new RuntimeHttpException('BUILDER_AI_AUDIO_REQUIRED', 'Envie o audio para transcricao.', 422);
        }

        $bytes = base64_decode($audioBase64, true);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeHttpException('BUILDER_AI_AUDIO_INVALID', 'Audio invalido para transcricao.', 422);
        }

        $mimeType = trim((string) ($payload['mimeType'] ?? 'audio/webm'));
        $fileName = trim((string) ($payload['fileName'] ?? 'builder-ai-audio.webm'));
        $transcript = $this->requestOpenAiCompatibleTranscription($settings, $bytes, $mimeType, $fileName);

        return ['transcript' => $transcript];
    }

    public function finalizeDraft(array $payload): array
    {
        $draft = is_array($payload['payload'] ?? null) ? $payload['payload'] : (is_array($payload['draft'] ?? null) ? $payload['draft'] : []);
        if (!$draft) {
            throw new RuntimeHttpException('BUILDER_AI_DRAFT_REQUIRED', 'Nao existe rascunho para validar.', 422);
        }

        return $this->builder->validateExternalDraft(['payload' => $draft]);
    }

    private function sendMock(string $text, array $history, array $context, array $settings): array
    {
        $entityName = $this->extractEntityName($text);
        $module = $this->extractModuleCode($text, $context);
        $moduleInfo = $this->findModuleInfo($context, $module);
        $baseNumber = (int) ($moduleInfo['numberStart'] ?? 1);
        $abbreviation = (string) ($moduleInfo['abbreviation'] ?? 'cd');
        $slug = $this->slugify($entityName !== '' ? $entityName : 'Cadastro');
        $tableName = 't' . $baseNumber;
        $fields = $this->extractFieldsFromText($text);

        if (!$fields) {
            $fields = [
                ['code' => 'c_descr', 'label' => 'Descricao', 'dataType' => 'string', 'length' => 160, 'required' => true],
                ['code' => 'log_ativo', 'label' => 'Ativo', 'dataType' => 'boolean', 'required' => false],
            ];
        }

        array_unshift($fields, ['code' => 'id', 'label' => 'ID', 'dataType' => 'integer', 'primaryKey' => true, 'required' => true]);

        $entityDraft = [
            'code' => $slug,
            'name' => $entityName !== '' ? $entityName : 'Cadastro',
            'entityType' => 'persistence',
            'tableName' => $tableName,
            'structureModuleCode' => $module,
            'structureType' => 'main',
            'structureBaseNumber' => $baseNumber,
            'fields' => $fields,
        ];

        $programDraft = [
            'pageType' => 'crud',
            'module' => $module,
            'programCode' => strtolower($abbreviation) . str_pad((string) $baseNumber, 4, '0', STR_PAD_LEFT),
            'programTitle' => $entityName !== '' ? $entityName : 'Cadastro',
            'screenId' => $module . '.' . str_replace('_', '-', $slug),
            'version' => '1.0.0',
        ];

        return [
            'assistantMessage' => 'Montei um rascunho inicial com base no seu pedido. Revise nomes tecnicos, tabela e codigo do programa antes de salvar.',
            'readyToApply' => true,
            'missingInputs' => [],
            'draft' => [
                'entityDraft' => $entityDraft,
                'programDraft' => $programDraft,
            ],
            'diagnostics' => [[
                'level' => 'info',
                'message' => 'Resposta gerada pelo provedor mock para validacao local do fluxo.',
                'source' => 'mock',
            ]],
        ];
    }

    private function sendOpenAiCompatible(string $text, array $history, array $context, array $settings): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($context),
            ],
        ];

        foreach ($history as $item) {
            $messages[] = [
                'role' => $item['role'],
                'content' => $item['text'],
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $text];

        $payload = [
            'model' => $settings['model'],
            'messages' => $messages,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ];
        $data = $this->requestJson(
            rtrim($settings['baseUrl'], '/') . '/chat/completions',
            $settings['apiToken'],
            $payload,
            60
        );
        $statusCode = (int) ($data['_statusCode'] ?? 200);
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->error('builder_ai_provider_error', ['status' => $statusCode, 'response' => $data]);
            throw new RuntimeHttpException('BUILDER_AI_PROVIDER_ERROR', 'O provedor de IA nao respondeu com sucesso.', 502, [
                'status' => $statusCode,
            ]);
        }

        $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            throw new RuntimeHttpException('BUILDER_AI_EMPTY_RESPONSE', 'O provedor de IA nao retornou conteudo utilizavel.', 502);
        }

        $parsed = $this->extractJsonPayload($content);
        if (!is_array($parsed)) {
            throw new RuntimeHttpException('BUILDER_AI_INVALID_RESPONSE', 'O provedor de IA retornou um JSON invalido.', 502);
        }

        return [
            'assistantMessage' => trim((string) ($parsed['assistantMessage'] ?? $parsed['message'] ?? '')),
            'readyToApply' => ($parsed['readyToApply'] ?? false) === true,
            'missingInputs' => is_array($parsed['missingInputs'] ?? null) ? $parsed['missingInputs'] : [],
            'draft' => is_array($parsed['draft'] ?? null)
                ? $parsed['draft']
                : (isset($parsed['entityDraft'], $parsed['programDraft']) ? [
                    'entityDraft' => $parsed['entityDraft'],
                    'programDraft' => $parsed['programDraft'],
                    'diagnostics' => $parsed['diagnostics'] ?? [],
                    'sourcePrompt' => $text,
                ] : null),
            'diagnostics' => is_array($parsed['diagnostics'] ?? null) ? $parsed['diagnostics'] : [],
        ];
    }

    private function requestOpenAiCompatibleTranscription(array $settings, string $bytes, string $mimeType, string $fileName): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'builder-ai-audio-');
        if ($tempFile === false) {
            throw new RuntimeHttpException('BUILDER_AI_AUDIO_TEMPFILE_ERROR', 'Nao foi possivel preparar o arquivo de audio para transcricao.', 500);
        }
        file_put_contents($tempFile, $bytes);

        $ch = curl_init(rtrim($settings['baseUrl'], '/') . '/audio/transcriptions');
        if ($ch === false) {
            @unlink($tempFile);
            throw new RuntimeHttpException('BUILDER_AI_AUDIO_HTTP_ERROR', 'Nao foi possivel iniciar a chamada de transcricao.', 500);
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $settings['apiToken'],
                ],
                CURLOPT_POSTFIELDS => [
                    'model' => $settings['transcriptionModel'],
                    'file' => curl_file_create($tempFile, $mimeType, $fileName),
                ],
                CURLOPT_TIMEOUT => 120,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeHttpException('BUILDER_AI_AUDIO_HTTP_ERROR', 'Falha ao enviar o audio para transcricao.', 502, [
                    'curlError' => curl_error($ch),
                ]);
            }
            $decoded = json_decode($raw, true);
            if ($status < 200 || $status >= 300) {
                $this->logger->error('builder_ai_transcription_error', ['status' => $status, 'response' => $decoded ?: $raw]);
                throw new RuntimeHttpException('BUILDER_AI_TRANSCRIPTION_ERROR', 'O provedor de transcricao retornou erro.', 502, [
                    'status' => $status,
                ]);
            }
            $text = trim((string) (($decoded['text'] ?? $decoded['transcript'] ?? '')));
            if ($text === '') {
                throw new RuntimeHttpException('BUILDER_AI_TRANSCRIPTION_EMPTY', 'A transcricao retornou vazia.', 502);
            }
            return $text;
        } finally {
            curl_close($ch);
            @unlink($tempFile);
        }
    }

    private function requestJson(string $url, string $token, array $payload, int $timeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeHttpException('BUILDER_AI_HTTP_ERROR', 'Nao foi possivel iniciar a chamada HTTP do assistente.', 500);
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => $this->jsonEncode($payload),
                CURLOPT_TIMEOUT => $timeout,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeHttpException('BUILDER_AI_HTTP_ERROR', 'Falha ao chamar o provedor de IA.', 502, [
                    'curlError' => curl_error($ch),
                ]);
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $decoded = ['raw' => $raw];
            }
            $decoded['_statusCode'] = $status;
            return $decoded;
        } finally {
            curl_close($ch);
        }
    }

    private function buildSystemPrompt(array $context): string
    {
        return <<<TXT
Voce e um assistente de modelagem CRUD do Construtor PG.
Responda somente em JSON.
Nao gere HTML, JavaScript, SQL, PHP, templates ou codigo executavel.
Objetivo: propor rascunhos para entityDraft e programDraft compativeis com o builder.
Limitacoes:
- pageType permitido: crud
- sem publicacao automatica
- sem regras PHP arbitrarias
- nomes tecnicos devem seguir o padrao informado no contexto
Formato de resposta:
{
  "assistantMessage": "texto curto em pt-BR",
  "readyToApply": true ou false,
  "missingInputs": ["..."],
  "diagnostics": [{"level":"info|warn|error","message":"...","source":"ia"}],
  "draft": {
    "entityDraft": {...},
    "programDraft": {...}
  }
}
Se faltar informacao relevante, deixe readyToApply=false, preencha missingInputs e nao invente dados criticos.
Contexto tecnico do builder:
{$this->jsonEncode($context)}
TXT;
    }

    private function normalizeHistory(array $history): array
    {
        $items = [];
        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $authorId = strtolower(trim((string) ($item['authorId'] ?? '')));
            $role = str_contains($authorId, 'ia') ? 'assistant' : 'user';
            $items[] = ['role' => $role, 'text' => $text];
        }

        return array_slice($items, -12);
    }

    private function extractJsonPayload(string $content): ?array
    {
        $trimmed = trim($content);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z0-9_-]*\s*|\s*```$/', '', $trimmed) ?: $trimmed;
            $trimmed = trim($trimmed);
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        try {
            $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractEntityName(string $text): string
    {
        $normalized = trim($text);
        if (preg_match('/(?:tabela|cadastro|entidade)\s+de\s+([a-zA-ZÀ-ÿ0-9\s_-]+)/iu', $normalized, $matches)) {
            return $this->titleize(trim($matches[1]));
        }
        if (preg_match('/criar\s+([a-zA-ZÀ-ÿ0-9\s_-]+)/iu', $normalized, $matches)) {
            return $this->titleize(trim($matches[1]));
        }
        return '';
    }

    private function extractFieldsFromText(string $text): array
    {
        $normalized = $this->slugify($text);
        $fields = [];
        if (str_contains($normalized, 'codigo')) {
            $fields[] = ['code' => 'c_cod', 'label' => 'Codigo', 'dataType' => 'string', 'length' => 40, 'required' => true, 'unique' => true];
        }
        if (str_contains($normalized, 'descricao')) {
            $fields[] = ['code' => 'c_descr', 'label' => 'Descricao', 'dataType' => 'string', 'length' => 160, 'required' => true];
        }
        if (str_contains($normalized, 'nome')) {
            $fields[] = ['code' => 'c_nome', 'label' => 'Nome', 'dataType' => 'string', 'length' => 160, 'required' => true];
        }
        if (str_contains($normalized, 'ativo')) {
            $fields[] = ['code' => 'log_ativo', 'label' => 'Ativo', 'dataType' => 'boolean', 'required' => false];
        }
        if (str_contains($normalized, 'data')) {
            $fields[] = ['code' => 'dt_cadastro', 'label' => 'Data', 'dataType' => 'date', 'required' => false];
        }

        $unique = [];
        foreach ($fields as $field) {
            $unique[$field['code']] = $field;
        }

        return array_values($unique);
    }

    private function extractModuleCode(string $text, array $context): string
    {
        $normalized = $this->slugify($text);
        if (str_contains($normalized, 'administr')) {
            return 'administracao';
        }
        if (str_contains($normalized, 'operac')) {
            return 'operacional';
        }

        return 'cadastros';
    }

    private function findModuleInfo(array $context, string $moduleCode): array
    {
        foreach (is_array($context['modules'] ?? null) ? $context['modules'] : [] as $item) {
            if (($item['code'] ?? '') === $moduleCode) {
                return $item;
            }
        }

        return [
            'code' => $moduleCode,
            'abbreviation' => 'cd',
            'numberStart' => 1,
        ];
    }

    private function slugify(string $text): string
    {
        $value = mb_strtolower(trim($text));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';
        return trim($value, '_');
    }

    private function titleize(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?: '';
        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }

    private function jsonEncode(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
