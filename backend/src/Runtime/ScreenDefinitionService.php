<?php

namespace App\Runtime;

use App\Repository\ScreenDefinitionRepository;

class ScreenDefinitionService
{
    public function __construct(
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeMetadataSanitizer $sanitizer,
        private readonly PermissionResolver $permissions,
        private readonly UserLayoutService $layouts,
    ) {
    }

    public function getDefinition(string $screenId): array
    {
        $screen = $this->screens->findPublishedByScreenId($screenId);
        if (!$screen) {
            throw new RuntimeHttpException('SCREEN_NOT_FOUND', 'Tela nao encontrada.', 404, [
                'screenId' => $screenId,
                'minimumRequired' => [
                    'screen_definition' => [
                        'screenId' => $screenId,
                        'status' => 'draft ou published',
                        'pageType' => 'crud ou home',
                        'definition' => 'JSON declarativo da tela autorizado pelo backend.',
                    ],
                ],
            ]);
        }
        if (!$this->permissions->canReadScreen($screen)) {
            throw new RuntimeHttpException('SCREEN_FORBIDDEN', 'Voce nao possui permissao para acessar esta tela.', 403, [
                'screenId' => $screenId,
            ]);
        }

        $definition = $screen->getDefinition();
        $definition['schemaVersion'] = $definition['schemaVersion'] ?? $screen->getSchemaVersion();
        $definition['pageType'] = $definition['pageType'] ?? $screen->getPageType();
        $definition['screenId'] = $screen->getScreenId();

        if (!isset($definition['currentUser'])) {
            $definition['currentUser'] = $this->permissions->getCurrentUserPayload();
        }
        if (($definition['pageType'] ?? '') === 'home') {
            $currentUser = $this->permissions->getCurrentUserPayload();
            $definition['currentUser'] = array_replace(
                isset($currentUser['source']) ? ($definition['currentUser'] ?? []) : $currentUser,
                isset($currentUser['source']) ? $currentUser : ($definition['currentUser'] ?? []),
            );
            if (is_array($currentUser['currentSubscriber'] ?? null)) {
                $definition['currentSubscriber'] = $currentUser['currentSubscriber'];
                $definition['currentTenant'] = $currentUser['currentSubscriber'];
                $definition['tenant'] = $currentUser['currentSubscriber'];
            }
            if (is_array($currentUser['availableSubscribers'] ?? null)) {
                $definition['availableSubscribers'] = $currentUser['availableSubscribers'];
            }
        }
        if (($definition['pageType'] ?? '') === 'crud') {
            $definition['crud']['userLayout'] = $this->layouts->buildUserLayout($screen->getScreenId());
        }
        $definition = $this->permissions->applyDefinitionPermissions($definition);

        return $this->sanitizer->sanitize($definition);
    }
}
