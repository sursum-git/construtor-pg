<?php

namespace App\Runtime;

use App\Repository\ScreenDefinitionRepository;

class RuntimeSpecialDocumentService
{
    public function __construct(
        private readonly ScreenDefinitionRepository $screens,
        private readonly PermissionResolver $permissions,
        private readonly StructuralIntegrityService $integrity,
        private readonly ProgramCustomizationResolver $customizations,
        private readonly ?RuntimeAnalyticsAuditStore $auditStore = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(string $screenId): array
    {
        $definition = $this->loadDefinition($screenId);

        return [
            'screenId' => $screenId,
            'pageType' => 'special_document',
            'program' => is_array($definition['program'] ?? null) ? $definition['program'] : [],
            'specialDocument' => $definition['specialDocument'],
            'runtime' => [
                'specialDocument' => [
                    'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function render(string $screenId, array $payload): array
    {
        $definition = $this->loadDefinition($screenId);
        $document = is_array($definition['specialDocument'] ?? null) ? $definition['specialDocument'] : [];
        $source = is_array($document['source'] ?? null) ? $document['source'] : [];
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        $renderEngine = (string) ($document['renderEngine'] ?? 'native_stub');
        $generatedAt = (new \DateTimeImmutable())->format(DATE_ATOM);

        $result = [
            'screenId' => $screenId,
            'documentId' => (string) ($definition['program']['id'] ?? $screenId),
            'title' => (string) ($definition['program']['title'] ?? 'Documento especial'),
            'subtitle' => (string) ($definition['program']['subtitle'] ?? ''),
            'documentKind' => (string) ($document['classification']['documentKind'] ?? 'special'),
            'renderEngine' => $renderEngine,
            'sourceType' => (string) ($source['type'] ?? 'operational'),
            'parameters' => $parameters,
            'summary' => [
                ['label' => 'Renderer', 'value' => 'native_stub'],
                ['label' => 'Status', 'value' => 'Estrutura pronta para implementacao especifica'],
            ],
            'sections' => [
                [
                    'id' => 'placeholder',
                    'title' => 'Placeholder controlado',
                    'lines' => [
                        'Esta camada separa documentos especiais da trilha reports.',
                        'Nenhum layout fiscal/oficial e emitido nesta fase.',
                        'A definicao segura, os parametros e a auditoria ja ficam preparados para a futura engine especifica.',
                    ],
                ],
            ],
            'artifact' => [
                'recommendedFormat' => (($document['outputs']['pdf'] ?? true) === true) ? 'pdf' : 'html',
                'status' => 'stub',
            ],
            'generatedAt' => $generatedAt,
        ];

        $this->recordAudit($definition, $result, $payload);

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function export(string $screenId, array $payload): array
    {
        $result = $this->render($screenId, $payload);
        $format = strtolower(trim((string) ($payload['format'] ?? 'pdf')));
        if (!in_array($format, ['pdf', 'html'], true)) {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_EXPORT_FORMAT_NOT_SUPPORTED', 'A exportacao inicial do documento especial aceita apenas PDF/HTML.', 422, [
                'format' => $format,
            ]);
        }

        if ($format === 'html') {
            $html = '<section><h1>' . htmlspecialchars((string) $result['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>'
                . '<p>' . htmlspecialchars((string) $result['subtitle'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
                . '<p>Placeholder controlado da camada de documentos especiais.</p></section>';

            return [
                'ok' => true,
                'format' => 'html',
                'fileName' => $this->safeFileName((string) ($result['documentId'] ?? 'documento-especial')) . '.html',
                'contentType' => 'text/html; charset=utf-8',
                'contentBase64' => base64_encode($html),
            ];
        }

        $pdf = $this->buildPlaceholderPdf($result);

        return [
            'ok' => true,
            'format' => 'pdf',
            'fileName' => $this->safeFileName((string) ($result['documentId'] ?? 'documento-especial')) . '.pdf',
            'contentType' => 'application/pdf',
            'contentBase64' => base64_encode($pdf),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDefinition(string $screenId): array
    {
        $screen = $this->screens->findPublishedByScreenId($screenId);
        if (!$screen) {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_SCREEN_NOT_FOUND', 'Tela de documento especial nao encontrada.', 404, [
                'screenId' => $screenId,
            ]);
        }
        $this->integrity->assertScreen($screen);

        $definition = $screen->getDefinition();
        $customized = $this->customizations->resolve($screenId, $definition);
        if (is_array($customized) && $customized) {
            $definition = $customized;
        }

        $definition['screenId'] = $screen->getScreenId();
        $definition['pageType'] = $definition['pageType'] ?? $screen->getPageType();
        if (($definition['pageType'] ?? '') !== 'special_document') {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_PAGE_TYPE_INVALID', 'A tela informada nao e do tipo special_document.', 422, [
                'screenId' => $screenId,
                'pageType' => $definition['pageType'] ?? null,
            ]);
        }
        if (!is_array($definition['specialDocument'] ?? null)) {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_DEFINITION_MISSING', 'Definicao de documento especial nao configurada.', 422, [
                'screenId' => $screenId,
            ]);
        }

        $this->assertNoUnsafeMetadata($definition['specialDocument']);

        return $definition;
    }

    private function assertNoUnsafeMetadata(mixed $value, array $path = []): void
    {
        if (!is_array($value)) {
            if (is_string($value) && preg_match('/<\s*script|javascript\s*:/i', $value)) {
                throw new RuntimeHttpException('SPECIAL_DOCUMENT_UNSAFE_METADATA', 'Documentos especiais nao aceitam HTML, JS ou template livre nos metadados.', 422, [
                    'path' => implode('.', $path),
                ]);
            }
            return;
        }

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['sql', 'template', 'javascript', 'script', 'handler', 'function'], true)) {
                throw new RuntimeHttpException('SPECIAL_DOCUMENT_UNSAFE_METADATA', 'Documentos especiais nao aceitam HTML, JS ou template livre nos metadados.', 422, [
                    'path' => implode('.', [...$path, (string) $key]),
                ]);
            }
            $this->assertNoUnsafeMetadata($item, [...$path, (string) $key]);
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $result
     * @param array<string, mixed> $payload
     */
    private function recordAudit(array $definition, array $result, array $payload): void
    {
        if (!$this->auditStore instanceof RuntimeAnalyticsAuditStore || !$this->auditStore->isEnabled()) {
            return;
        }

        $generatedAt = $result['generatedAt'] ?? (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->auditStore->record([
            'tenantId' => $this->permissions->getTenantId(),
            'userId' => $this->permissions->getUserId(),
            'sessionId' => $this->permissions->getSessionId(),
            'screenId' => (string) ($definition['screenId'] ?? 'special_document'),
            'datasetId' => (string) ($definition['program']['id'] ?? $definition['screenId'] ?? 'special_document'),
            'viewId' => trim((string) ($payload['format'] ?? '')),
            'executionMode' => 'special_document',
            'resultSource' => 'special_document_render',
            'filterFingerprint' => hash('sha256', (string) json_encode([
                'parameters' => $payload['parameters'] ?? [],
                'format' => $payload['format'] ?? 'pdf',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'rowCount' => 0,
            'totalCount' => 0,
            'filters' => [],
            'parameters' => $payload['parameters'] ?? [],
            'sort' => [],
            'requestPayload' => $payload,
            'resultColumns' => [],
            'resultRows' => [],
            'metadata' => [
                'auditContext' => 'special_document',
                'documentId' => $definition['program']['id'] ?? null,
                'documentKind' => $definition['specialDocument']['classification']['documentKind'] ?? null,
                'renderEngine' => $definition['specialDocument']['renderEngine'] ?? null,
            ],
            'consultedAt' => $generatedAt,
        ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function buildPlaceholderPdf(array $result): string
    {
        $lines = [
            (string) ($result['title'] ?? 'Documento especial'),
            (string) ($result['subtitle'] ?? ''),
            'Documento especial em trilha separada.',
            'Renderer atual: native_stub',
            'Status: placeholder controlado para futura implementacao especifica.',
        ];
        $content = "BT /F1 12 Tf 40 780 Td ";
        $first = true;
        foreach ($lines as $line) {
            if (!$first) {
                $content .= "T* ";
            }
            $content .= '(' . $this->escapePdfText($line) . ') Tj ';
            $first = false;
        }
        $content .= 'ET';

        $objects = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n",
            "4 0 obj << /Length " . strlen($content) . " >> stream\n" . $content . "\nendstream endobj\n",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($index = 1; $index <= count($objects); ++$index) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $value): string
    {
        $normalized = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $normalized);
    }

    private function safeFileName(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?: 'documento-especial';
        return trim($clean, '-');
    }
}
