<?php

namespace App\Runtime;

class RuntimeMetadataSanitizer
{
    private const BLOCKED_KEYS = [
        'template',
        'eval',
        'function',
        'handler',
        'script',
        'javascript',
        'onclick',
        'onchange',
        'onload',
        'onerror',
    ];

    private const ALLOWED_TEMPLATE_PATHS = [
        'crud.grid.mobile.template',
        'grid.mobile.template',
    ];

    public function sanitize(array $definition): array
    {
        $definition = $this->removeBlockedKeys($definition);
        $pageType = (string) ($definition['pageType'] ?? '');
        $screenId = $this->resolveScreenId($definition);

        if ($pageType === 'crud') {
            $definition = $this->sanitizeCrudDefinition($definition, $screenId);
        }
        if ($pageType === 'process') {
            $definition = $this->sanitizeProcessDefinition($definition, $screenId);
        }
        if ($pageType === 'analytics') {
            $definition = $this->sanitizeAnalyticsDefinition($definition, $screenId);
        }
        if ($pageType === 'report') {
            $definition = $this->sanitizeReportDefinition($definition, $screenId);
        }
        if ($pageType === 'special_document') {
            $definition = $this->sanitizeSpecialDocumentDefinition($definition, $screenId);
        }
        if ($pageType === 'regulated_document') {
            $definition = $this->sanitizeRegulatedDocumentDefinition($definition, $screenId);
        }

        if ($pageType === 'home') {
            $definition = $this->sanitizeHomeDefinition($definition, $screenId);
        }

        return $definition;
    }

    private function sanitizeAnalyticsDefinition(array $definition, string $screenId): array
    {
        $definition['screenId'] = $screenId;
        $definition['program']['screenId'] = $screenId;
        $api = $definition['dataSource']['api'] ?? $definition['api'] ?? [];
        $api = $this->sanitizeEndpointMap(is_array($api) ? $api : []);
        $definition['api'] = $api;
        $definition['dataSource']['api'] = $api;

        if (!empty($definition['analytics']['endpoints']) && is_array($definition['analytics']['endpoints'])) {
            $definition['analytics']['endpoints'] = $this->sanitizeEndpointMap($definition['analytics']['endpoints']);
        }

        unset($definition['definition'], $definition['definitionUrl'], $definition['openUrl'], $definition['url'], $definition['html'], $definition['htmlUrl']);

        return $definition;
    }

    private function sanitizeReportDefinition(array $definition, string $screenId): array
    {
        $definition['screenId'] = $screenId;
        $definition['program']['screenId'] = $screenId;
        $api = $definition['dataSource']['api'] ?? $definition['api'] ?? [];
        $api = $this->sanitizeEndpointMap(is_array($api) ? $api : []);
        $definition['api'] = $api;
        $definition['dataSource']['api'] = $api;

        if (!empty($definition['report']['endpoints']) && is_array($definition['report']['endpoints'])) {
            $definition['report']['endpoints'] = $this->sanitizeEndpointMap($definition['report']['endpoints']);
        }

        unset($definition['definition'], $definition['definitionUrl'], $definition['openUrl'], $definition['url'], $definition['html'], $definition['htmlUrl']);

        return $definition;
    }

    private function sanitizeSpecialDocumentDefinition(array $definition, string $screenId): array
    {
        $definition['screenId'] = $screenId;
        $definition['program']['screenId'] = $screenId;
        $api = $definition['dataSource']['api'] ?? $definition['api'] ?? [];
        $api = $this->sanitizeEndpointMap(is_array($api) ? $api : []);
        $definition['api'] = $api;
        $definition['dataSource']['api'] = $api;

        if (!empty($definition['specialDocument']['endpoints']) && is_array($definition['specialDocument']['endpoints'])) {
            $definition['specialDocument']['endpoints'] = $this->sanitizeEndpointMap($definition['specialDocument']['endpoints']);
        }

        unset($definition['definition'], $definition['definitionUrl'], $definition['openUrl'], $definition['url'], $definition['html'], $definition['htmlUrl']);

        return $definition;
    }

    private function sanitizeRegulatedDocumentDefinition(array $definition, string $screenId): array
    {
        $definition['screenId'] = $screenId;
        $definition['program']['screenId'] = $screenId;
        $api = $definition['dataSource']['api'] ?? $definition['api'] ?? [];
        $api = $this->sanitizeEndpointMap(is_array($api) ? $api : []);
        $definition['api'] = $api;
        $definition['dataSource']['api'] = $api;

        if (!empty($definition['regulatedDocument']['endpoints']) && is_array($definition['regulatedDocument']['endpoints'])) {
            $definition['regulatedDocument']['endpoints'] = $this->sanitizeEndpointMap($definition['regulatedDocument']['endpoints']);
        }

        unset($definition['definition'], $definition['definitionUrl'], $definition['openUrl'], $definition['url'], $definition['html'], $definition['htmlUrl']);

        return $definition;
    }

    private function sanitizeCrudDefinition(array $definition, string $screenId): array
    {
        $api = $definition['dataSource']['api'] ?? $definition['api'] ?? [];
        $api = $this->sanitizeEndpointMap(is_array($api) ? $api : []);
        $definition['api'] = $api;
        $definition['dataSource']['api'] = $api;
        $definition['screenId'] = $screenId;
        $definition['program']['screenId'] = $screenId;

        $definition = $this->normalizeDocumentReferences($definition);

        if (($definition['crud']['form']['logs']['url'] ?? null) && empty($definition['crud']['form']['logs']['documentId'])) {
            unset($definition['crud']['form']['logs']['url']);
            $definition['crud']['form']['logs']['documentId'] = 'form.logs';
        }

        if (($definition['program']['logs']['url'] ?? null) && empty($definition['program']['logs']['documentId'])) {
            unset($definition['program']['logs']['url']);
            $definition['program']['logs']['documentId'] = 'program.logs';
        }

        return $definition;
    }

    private function sanitizeProcessDefinition(array $definition, string $screenId): array
    {
        $definition['screenId'] = $screenId;
        $definition['program']['screenId'] = $screenId;
        $api = $definition['dataSource']['api'] ?? $definition['api'] ?? [];
        $api = $this->sanitizeEndpointMap(is_array($api) ? $api : []);
        $definition['api'] = $api;
        $definition['dataSource']['api'] = $api;

        if (!empty($definition['process']['endpoint'])) {
            $endpoint = is_array($definition['process']['endpoint']) ? $definition['process']['endpoint'] : ['endpointId' => (string) $definition['process']['endpoint']];
            $definition['process']['endpoint'] = [
                'endpointId' => (string) ($endpoint['endpointId'] ?? $endpoint['actionId'] ?? $endpoint['id'] ?? 'process'),
            ];
            if (!empty($endpoint['method'])) {
                $definition['process']['endpoint']['method'] = (string) $endpoint['method'];
            }
        }
        if (!empty($definition['process']['statusEndpoint'])) {
            $endpoint = is_array($definition['process']['statusEndpoint']) ? $definition['process']['statusEndpoint'] : ['endpointId' => (string) $definition['process']['statusEndpoint']];
            $definition['process']['statusEndpoint'] = [
                'endpointId' => (string) ($endpoint['endpointId'] ?? $endpoint['actionId'] ?? $endpoint['id'] ?? 'status'),
            ];
            if (!empty($endpoint['method'])) {
                $definition['process']['statusEndpoint']['method'] = (string) $endpoint['method'];
            }
        }

        if (!empty($definition['process']['endpoints']) && is_array($definition['process']['endpoints'])) {
            $definition['process']['endpoints'] = $this->sanitizeEndpointMap($definition['process']['endpoints']);
        }

        unset($definition['definition'], $definition['definitionUrl'], $definition['openUrl'], $definition['url'], $definition['html'], $definition['htmlUrl']);

        return $definition;
    }

    private function sanitizeHomeDefinition(array $definition, string $screenId): array
    {
        $definition['screenId'] = $screenId;
        $definition['app']['id'] = $definition['app']['id'] ?? $screenId;
        $definition = $this->normalizeHomeLogoUrl($definition);

        $definition = $this->sanitizeHomeEndpointGroup($definition, ['layout', 'appbar', 'chat'], [
            'contacts' => 'home.chat.contacts',
            'history' => 'home.chat.history',
            'send' => 'home.chat.send',
            'events' => 'home.chat.events',
        ]);
        $definition = $this->sanitizeHomeEndpointGroup($definition, ['layout', 'appbar', 'support'], [
            'onlineUsers' => 'home.support.onlineUsers',
            'history' => 'home.support.history',
            'send' => 'home.support.send',
            'createRequest' => 'home.support.createRequest',
            'requestStatus' => 'home.support.requestStatus',
            'events' => 'home.support.events',
        ]);
        $definition = $this->sanitizeHomeEndpointGroup($definition, ['layout', 'appbar', 'aiChat'], [
            'history' => 'home.aiChat.history',
            'send' => 'home.aiChat.send',
        ]);
        $definition = $this->sanitizeHomeEndpointGroup($definition, ['layout', 'appbar', 'notifications'], [
            'list' => 'home.notifications.list',
            'ack' => 'home.notifications.ack',
        ]);
        $definition = $this->sanitizeHomeEndpointGroup($definition, ['layout', 'appbar', 'alerts'], [
            'list' => 'home.alerts.list',
        ]);
        $definition = $this->sanitizeHomeEndpointGroup($definition, ['layout', 'appbar', 'requests'], [
            'list' => 'home.requests.list',
        ]);
        $definition = $this->sanitizeHomeEndpointGroup($definition, ['layout', 'appbar', 'jobs'], [
            'list' => 'home.jobs.list',
        ]);
        $definition = $this->sanitizeHomeEndpointGroup($definition, ['layout', 'appbar', 'subscriberSwitch'], [
            'change' => 'home.subscriber.change',
        ]);
        $definition = $this->sanitizeHomeEndpointGroup($definition, ['layout', 'appbar', 'runtimeMessages'], [
            'poll' => 'runtime.messages.poll',
            'ack' => 'runtime.messages.ack',
            'forceLogout' => 'runtime.admin.forceLogout',
        ]);

        $definition = $this->sanitizeHomePrograms($definition);

        return $definition;
    }

    private function normalizeHomeLogoUrl(array $definition): array
    {
        $logo = $definition['app']['logo'] ?? null;
        if (is_string($logo)) {
            $definition['app']['logo'] = $this->normalizeRootPublicUrl($logo);
            return $definition;
        }

        if (is_array($logo) && is_string($logo['url'] ?? null)) {
            $definition['app']['logo']['url'] = $this->normalizeRootPublicUrl($logo['url']);
        }

        return $definition;
    }

    private function normalizeRootPublicUrl(string $url): string
    {
        $url = trim($url);
        if (str_starts_with($url, 'public/')) {
            return '/' . $url;
        }

        return $url;
    }

    private function sanitizeHomePrograms(array $definition): array
    {
        $programs = [];
        foreach (($definition['programs'] ?? []) as $program) {
            if (!is_array($program)) {
                continue;
            }

            $program = $this->sanitizeHomeRuntimeProgram($program);
            if ($program !== null) {
                $programs[] = $program;
            }
        }

        $definition['programs'] = $programs;
        $allowedProgramIds = array_fill_keys(array_column($programs, 'id'), true);
        $definition = $this->sanitizeHomeNavigation($definition, $allowedProgramIds);

        $initialProgramId = (string) ($definition['layout']['initialProgramId'] ?? '');
        if ($programs && ($initialProgramId === '' || !isset($allowedProgramIds[$initialProgramId]))) {
            $definition['layout']['initialProgramId'] = (string) $programs[0]['id'];
        }

        $subscriberSwitch = $definition['layout']['appbar']['subscriberSwitch'] ?? null;
        if (is_array($subscriberSwitch)) {
            foreach (['programId', 'changeProgramId'] as $key) {
                if (!empty($subscriberSwitch[$key]) && !isset($allowedProgramIds[(string) $subscriberSwitch[$key]])) {
                    unset($subscriberSwitch[$key]);
                }
            }
            unset($subscriberSwitch['url'], $subscriberSwitch['changeUrl']);
            $definition['layout']['appbar']['subscriberSwitch'] = $subscriberSwitch;
        }

        return $definition;
    }

    private function sanitizeHomeRuntimeProgram(array $program): ?array
    {
        if (!in_array((string) ($program['type'] ?? 'iframe'), ['crud', 'process', 'analytics', 'report'], true)) {
            return null;
        }

        if (($program['id'] ?? '') === 'clientes-crud') {
            $program['screenId'] = 'cadastros.clientes';
        }
        if (($program['id'] ?? '') === 'processamento-clientes') {
            $program['screenId'] = 'processamento.relatorio-clientes';
        }
        if (($program['id'] ?? '') === 'relatorio-clientes-operacional') {
            $program['screenId'] = 'relatorios.clientes-operacional';
        }
        if (($program['id'] ?? '') === 'relatorio-clientes-analitico') {
            $program['screenId'] = 'relatorios.clientes-analitico';
        }

        if (empty($program['id']) || empty($program['screenId'])) {
            return null;
        }

        $program['type'] = (string) ($program['type'] ?? 'crud');
        unset(
            $program['definition'],
            $program['definitionUrl'],
            $program['openUrl'],
            $program['url'],
            $program['html'],
            $program['htmlUrl'],
        );

        if (($program['logs']['url'] ?? null) && empty($program['logs']['documentId'])) {
            unset($program['logs']['url']);
            $program['logs']['documentId'] = ($program['id'] ?? 'program') . '.logs';
        }

        return $program;
    }

    /**
     * @param array<string, bool> $allowedProgramIds
     */
    private function sanitizeHomeNavigation(array $definition, array $allowedProgramIds): array
    {
        $groups = [];
        foreach (($definition['navigation']['groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }

            $items = [];
            foreach (($group['items'] ?? []) as $item) {
                if (is_array($item) && isset($allowedProgramIds[(string) ($item['programId'] ?? '')])) {
                    $items[] = $item;
                }
            }

            if ($items) {
                $group['items'] = array_values($items);
                $groups[] = $group;
            }
        }

        if (!$groups && !empty($definition['programs'])) {
            $groups[] = [
                'id' => 'principal',
                'title' => 'Principal',
                'items' => array_map(
                    static fn (array $program): array => [
                        'programId' => (string) $program['id'],
                        'title' => (string) ($program['title'] ?? $program['id']),
                    ],
                    $definition['programs'],
                ),
            ];
        }

        $definition['navigation']['groups'] = $groups;
        return $definition;
    }

    private function sanitizeEndpointMap(array $api): array
    {
        $sanitized = [];
        foreach ($api as $key => $endpoint) {
            $source = is_array($endpoint) ? $endpoint : ['endpointId' => (string) $endpoint];
            $endpointId = (string) ($source['endpointId'] ?? $source['actionId'] ?? $source['id'] ?? $key);
            $sanitized[$key] = [
                'endpointId' => $endpointId,
                'method' => 'POST',
            ];
            if (!empty($source['originalMethod'])) {
                $sanitized[$key]['originalMethod'] = (string) $source['originalMethod'];
            } elseif (!empty($source['method'])) {
                $sanitized[$key]['originalMethod'] = (string) $source['method'];
            }
        }
        return $sanitized;
    }

    private function sanitizeHomeEndpointGroup(array $definition, array $path, array $defaults): array
    {
        $group = $this->getByPath($definition, $path);
        if (!is_array($group)) {
            return $definition;
        }

        $endpoints = is_array($group['endpoints'] ?? null) ? $group['endpoints'] : [];
        foreach ($defaults as $key => $endpointId) {
            $value = $endpoints[$key] ?? $group[$key . 'Url'] ?? $group[$key] ?? null;
            if ($value === null) {
                continue;
            }
            $source = is_array($value) ? $value : ['endpointId' => $endpointId];
            $defaultMethod = in_array($key, ['contacts', 'onlineUsers', 'requestStatus', 'events'], true) ? 'GET' : 'POST';
            $endpoints[$key] = [
                'endpointId' => (string) ($source['endpointId'] ?? $source['actionId'] ?? $source['id'] ?? $endpointId),
                'method' => strtoupper((string) ($source['method'] ?? $defaultMethod)),
            ];
            unset($group[$key . 'Url'], $group[$key]);
        }
        $group['endpoints'] = $endpoints;
        return $this->setByPath($definition, $path, $group);
    }

    private function normalizeDocumentReferences(array $definition): array
    {
        foreach (($definition['crud']['form']['steps'] ?? []) as $stepIndex => $step) {
            if (($step['logs']['url'] ?? null) && empty($step['logs']['endpointId'])) {
                unset($definition['crud']['form']['steps'][$stepIndex]['logs']['url']);
                $definition['crud']['form']['steps'][$stepIndex]['logs']['endpointId'] = 'stepHistory';
            }
        }

        return $definition;
    }

    private function removeBlockedKeys(mixed $value, array $path = []): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            $currentPath = [...$path, (string) $key];
            if (is_string($key) && in_array(strtolower($key), self::BLOCKED_KEYS, true) && !$this->isAllowedTemplatePath($key, $currentPath)) {
                continue;
            }
            $clean[$key] = $this->removeBlockedKeys($item, $currentPath);
        }

        return $clean;
    }

    private function isAllowedTemplatePath(string $key, array $path): bool
    {
        return strtolower($key) === 'template' && in_array(implode('.', $path), self::ALLOWED_TEMPLATE_PATHS, true);
    }

    private function resolveScreenId(array $definition): string
    {
        return (string) (
            $definition['screenId']
            ?? $definition['program']['screenId']
            ?? $definition['program']['id']
            ?? $definition['app']['id']
            ?? 'screen'
        );
    }

    private function getByPath(array $source, array $path): mixed
    {
        $cursor = $source;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }

    private function setByPath(array $source, array $path, mixed $value): array
    {
        $cursor = &$source;
        foreach ($path as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        $cursor = $value;
        return $source;
    }
}
