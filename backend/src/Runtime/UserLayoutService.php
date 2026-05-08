<?php

namespace App\Runtime;

use App\Entity\UserFilterPreference;
use App\Entity\UserGridLayoutPreference;
use App\Entity\UserGroupPreference;
use App\Entity\UserMobileGridTemplatePreference;
use App\Entity\UserSortPreference;
use App\Repository\UserFilterPreferenceRepository;
use App\Repository\UserGridLayoutPreferenceRepository;
use App\Repository\UserGroupPreferenceRepository;
use App\Repository\UserMobileGridTemplatePreferenceRepository;
use App\Repository\UserSortPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserLayoutService
{
    private const GLOBAL_TENANT_ID = '__global__';

    public function __construct(
        private readonly UserGridLayoutPreferenceRepository $layouts,
        private readonly UserSortPreferenceRepository $sorts,
        private readonly UserGroupPreferenceRepository $groups,
        private readonly UserFilterPreferenceRepository $filters,
        private readonly UserMobileGridTemplatePreferenceRepository $mobileTemplates,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionResolver $permissions,
    ) {
    }

    public function saveLayout(string $screenId, array $payload): array
    {
        $preference = $this->upsertGridLayout($screenId, $payload);

        return [
            'ok' => true,
            'layout' => $this->formatGridLayout($preference),
            'userLayout' => $this->buildUserLayout($screenId),
        ];
    }

    public function restoreLayout(string $screenId): array
    {
        return [
            'ok' => true,
            'userLayout' => $this->buildUserLayout($screenId),
        ];
    }

    public function saveSort(string $screenId, array $payload): array
    {
        $preference = $this->upsertSort($screenId, $payload);

        return [
            'ok' => true,
            'sortPreset' => $this->formatSort($preference),
            'userLayout' => $this->buildUserLayout($screenId),
        ];
    }

    public function saveGroup(string $screenId, array $payload): array
    {
        $preference = $this->upsertGroup($screenId, $payload);

        return [
            'ok' => true,
            'groupPreset' => $this->formatGroup($preference),
            'userLayout' => $this->buildUserLayout($screenId),
        ];
    }

    public function saveFilter(string $screenId, array $payload): array
    {
        $preference = $this->upsertFilter($screenId, $payload);

        return [
            'ok' => true,
            'filterPreset' => $this->formatFilter($preference),
            'userLayout' => $this->buildUserLayout($screenId),
        ];
    }

    public function saveMobileTemplate(string $screenId, array $payload): array
    {
        $preference = $this->upsertMobileTemplate($screenId, $payload);

        return [
            'ok' => true,
            'mobileTemplatePreset' => $this->formatMobileTemplate($preference),
            'userLayout' => $this->buildUserLayout($screenId),
        ];
    }

    public function deleteMobileTemplate(string $screenId, ?string $id, array $payload = []): array
    {
        return $this->deletePreference($screenId, 'mobileTemplate', $id, $payload);
    }

    public function deletePreference(string $screenId, string $type, ?string $id, array $payload = []): array
    {
        if (!$id) {
            throw new RuntimeHttpException('PREFERENCE_ID_REQUIRED', 'Preferencia nao informada.');
        }

        $tenantIds = $this->deleteTenantCandidates($payload);
        $preference = match ($type) {
            'layout' => $this->findGridLayoutForDelete($screenId, $id, $tenantIds),
            'sort' => $this->findSortForDelete($screenId, $id, $tenantIds),
            'group' => $this->findGroupForDelete($screenId, $id, $tenantIds),
            'filter' => $this->findFilterForDelete($screenId, $id, $tenantIds),
            'mobileTemplate' => $this->findMobileTemplateForDelete($screenId, $id, $tenantIds),
            default => throw new RuntimeHttpException('PREFERENCE_TYPE_INVALID', 'Tipo de preferencia invalido.'),
        };

        if ($preference) {
            $this->entityManager->remove($preference);
            $this->entityManager->flush();
        }

        return [
            'ok' => true,
            'userLayout' => $this->buildUserLayout($screenId),
        ];
    }

    public function buildUserLayout(string $screenId): array
    {
        $tenantLayouts = array_map(fn (UserGridLayoutPreference $item) => $this->formatGridLayout($item), $this->layoutsForTenant($screenId, $this->tenantId()));
        $globalLayouts = array_map(fn (UserGridLayoutPreference $item) => $this->formatGridLayout($item), $this->layoutsForTenant($screenId, self::GLOBAL_TENANT_ID));
        $tenantSorts = array_map(fn (UserSortPreference $item) => $this->formatSort($item), $this->sortsForTenant($screenId, $this->tenantId()));
        $globalSorts = array_map(fn (UserSortPreference $item) => $this->formatSort($item), $this->sortsForTenant($screenId, self::GLOBAL_TENANT_ID));
        $tenantGroups = array_map(fn (UserGroupPreference $item) => $this->formatGroup($item), $this->groupsForTenant($screenId, $this->tenantId()));
        $globalGroups = array_map(fn (UserGroupPreference $item) => $this->formatGroup($item), $this->groupsForTenant($screenId, self::GLOBAL_TENANT_ID));
        $tenantFilters = array_map(fn (UserFilterPreference $item) => $this->formatFilter($item), $this->filtersForTenant($screenId, $this->tenantId()));
        $globalFilters = array_map(fn (UserFilterPreference $item) => $this->formatFilter($item), $this->filtersForTenant($screenId, self::GLOBAL_TENANT_ID));
        $tenantMobileTemplates = array_map(fn (UserMobileGridTemplatePreference $item) => $this->formatMobileTemplate($item), $this->mobileTemplatesForTenant($screenId, $this->tenantId()));
        $globalMobileTemplates = array_map(fn (UserMobileGridTemplatePreference $item) => $this->formatMobileTemplate($item), $this->mobileTemplatesForTenant($screenId, self::GLOBAL_TENANT_ID));

        $layouts = $this->mergePreferences($globalLayouts, $tenantLayouts);
        $sorts = $this->mergePreferences($globalSorts, $tenantSorts);
        $groups = $this->mergePreferences($globalGroups, $tenantGroups);
        $filters = $this->mergePreferences($globalFilters, $tenantFilters);
        $mobileTemplates = $this->mergePreferences($globalMobileTemplates, $tenantMobileTemplates);

        $grid = $this->emptyGridLayout();
        $activeLayout = $this->findDefault($tenantLayouts) ?? $this->findDefault($globalLayouts);
        if ($activeLayout) {
            $grid = array_replace_recursive($grid, $activeLayout['grid'] ?? []);
        }

        $activeSort = $this->findDefault($tenantSorts) ?? $this->findDefault($globalSorts);
        if ($activeSort) {
            $grid['sort'] = $activeSort['sort'] ?? [];
        }

        $activeGroup = $this->findDefault($tenantGroups) ?? $this->findDefault($globalGroups);
        if ($activeGroup) {
            $grid['group'] = $activeGroup['group'] ?? [];
            $grid['groupAggregates'] = $activeGroup['aggregates'] ?? [];
        }

        $activeFilter = $this->findDefault($tenantFilters) ?? $this->findDefault($globalFilters);
        if ($activeFilter && empty($grid['filter'])) {
            $grid['filterPreset'] = $activeFilter['filters'] ?? [];
        }

        $activeMobileTemplate = $this->findDefault($tenantMobileTemplates) ?? $this->findDefault($globalMobileTemplates);
        if ($activeMobileTemplate) {
            $grid['mobileTemplate'] = $activeMobileTemplate['template'] ?? null;
        }

        return [
            'enabled' => true,
            'version' => 2,
            'source' => $activeLayout ? ($activeLayout['scope'] === 'global' ? 'user_global' : 'user') : 'default',
            'definitionHash' => $activeLayout['definitionHash'] ?? $screenId . '-v2',
            'preferenceScopes' => [
                'currentTenantId' => $this->tenantId(),
                'globalScope' => 'global',
                'fallbackOrder' => ['tenant', 'global', 'program', 'system'],
            ],
            'grid' => $grid,
            'savedLayouts' => $layouts,
            'savedSorts' => $sorts,
            'savedGroups' => $groups,
            'savedFilters' => $filters,
            'savedMobileTemplates' => $mobileTemplates,
            'activeLayoutId' => $activeLayout['id'] ?? null,
            'activeSortId' => $activeSort['id'] ?? null,
            'activeGroupId' => $activeGroup['id'] ?? null,
            'activeFilterId' => $activeFilter['id'] ?? null,
            'activeMobileTemplateId' => $activeMobileTemplate['id'] ?? null,
        ];
    }

    private function upsertGridLayout(string $screenId, array $payload): UserGridLayoutPreference
    {
        [$id, $name, $isDefault] = $this->metadata($payload, 'layout');
        $tenantId = $this->preferenceTenantId($payload);
        $grid = $this->arrayValue($payload['grid'] ?? []);
        $columns = $this->arrayValue($grid['columns'] ?? []);

        $preference = $this->layouts->findOneForUser($tenantId, $this->userId(), $screenId, $id) ?? new UserGridLayoutPreference();
        $preference
            ->setTenantId($tenantId)
            ->setUserId($this->userId())
            ->setScreenId($screenId)
            ->setLayoutId($id)
            ->setName($name)
            ->setDefaultPreference($isDefault)
            ->setDefinitionHash(is_scalar($payload['definitionHash'] ?? null) ? (string) $payload['definitionHash'] : null)
            ->setColumnsOrder($this->normalizeFieldList($columns['order'] ?? []))
            ->setHiddenColumns($this->normalizeFieldList($columns['hidden'] ?? []))
            ->setColumnWidths($this->normalizeColumnWidths($columns['widths'] ?? []))
            ->setFrozenColumns($this->normalizeFieldList($columns['frozen'] ?? []))
            ->setAddedColumns($this->normalizeFieldList($columns['added'] ?? []))
            ->setSortConfig($this->normalizeSort($grid['sort'] ?? []))
            ->setFilterConfig($this->normalizeFilter($grid['filter'] ?? null))
            ->setGroupConfig($this->normalizeGroup($grid['group'] ?? []))
            ->setGroupAggregates($this->normalizeAggregates($grid['groupAggregates'] ?? []))
            ->setMobileTemplate($this->normalizeMobileTemplate($grid['mobileTemplate'] ?? $payload['mobileTemplate'] ?? null));

        $this->clearDefaultLayouts($screenId, $tenantId, $id, $isDefault);
        $this->entityManager->persist($preference);
        $this->entityManager->flush();

        return $preference;
    }

    private function upsertSort(string $screenId, array $payload): UserSortPreference
    {
        [$id, $name, $isDefault] = $this->metadata($payload, 'sort');
        $tenantId = $this->preferenceTenantId($payload);
        $preference = $this->sorts->findOneForUser($tenantId, $this->userId(), $screenId, $id) ?? new UserSortPreference();
        $preference
            ->setTenantId($tenantId)
            ->setUserId($this->userId())
            ->setScreenId($screenId)
            ->setSortId($id)
            ->setName($name)
            ->setDefaultPreference($isDefault)
            ->setSortConfig($this->normalizeSort($payload['sort'] ?? []));

        $this->clearDefaultSorts($screenId, $tenantId, $id, $isDefault);
        $this->entityManager->persist($preference);
        $this->entityManager->flush();

        return $preference;
    }

    private function upsertGroup(string $screenId, array $payload): UserGroupPreference
    {
        [$id, $name, $isDefault] = $this->metadata($payload, 'group');
        $tenantId = $this->preferenceTenantId($payload);
        $preference = $this->groups->findOneForUser($tenantId, $this->userId(), $screenId, $id) ?? new UserGroupPreference();
        $preference
            ->setTenantId($tenantId)
            ->setUserId($this->userId())
            ->setScreenId($screenId)
            ->setGroupId($id)
            ->setName($name)
            ->setDefaultPreference($isDefault)
            ->setGroupConfig($this->normalizeGroup($payload['group'] ?? []))
            ->setAggregates($this->normalizeAggregates($payload['aggregates'] ?? []));

        $this->clearDefaultGroups($screenId, $tenantId, $id, $isDefault);
        $this->entityManager->persist($preference);
        $this->entityManager->flush();

        return $preference;
    }

    private function upsertFilter(string $screenId, array $payload): UserFilterPreference
    {
        [$id, $name, $isDefault] = $this->metadata($payload, 'filter');
        $tenantId = $this->preferenceTenantId($payload);
        $preference = $this->filters->findOneForUser($tenantId, $this->userId(), $screenId, $id) ?? new UserFilterPreference();
        $preference
            ->setTenantId($tenantId)
            ->setUserId($this->userId())
            ->setScreenId($screenId)
            ->setFilterId($id)
            ->setName($name)
            ->setDefaultPreference($isDefault)
            ->setFilters($this->normalizeJsonList($payload['filters'] ?? []));

        $this->clearDefaultFilters($screenId, $tenantId, $id, $isDefault);
        $this->entityManager->persist($preference);
        $this->entityManager->flush();

        return $preference;
    }

    private function upsertMobileTemplate(string $screenId, array $payload): UserMobileGridTemplatePreference
    {
        [$id, $name, $isDefault] = $this->metadata($payload, 'mobile-template');
        $tenantId = $this->preferenceTenantId($payload);
        $template = $this->normalizeMobileTemplate($payload['template'] ?? $payload['mobileTemplate'] ?? $payload);
        if ($template === null) {
            throw new RuntimeHttpException('MOBILE_TEMPLATE_REQUIRED', 'Template mobile nao informado.');
        }

        $preference = $this->mobileTemplates->findOneForUser($tenantId, $this->userId(), $screenId, $id) ?? new UserMobileGridTemplatePreference();
        $preference
            ->setTenantId($tenantId)
            ->setUserId($this->userId())
            ->setScreenId($screenId)
            ->setTemplateId($id)
            ->setName($name)
            ->setDefaultPreference($isDefault)
            ->setTitleField($template['titleField'] ?? null)
            ->setSubtitleField($template['subtitleField'] ?? null)
            ->setBadgeFields($template['badges'] ?? [])
            ->setFieldPositions($template['fields'] ?? [])
            ->setTabs($template['tabs'] ?? [])
            ->setPayload($template);

        $this->clearDefaultMobileTemplates($screenId, $tenantId, $id, $isDefault);
        $this->entityManager->persist($preference);
        $this->entityManager->flush();

        return $preference;
    }

    private function formatGridLayout(UserGridLayoutPreference $preference): array
    {
        return [
            'id' => $preference->getLayoutId(),
            'name' => $preference->getName(),
            'isDefault' => $preference->isDefaultPreference(),
            'scope' => $this->scopeForTenantId($preference->getTenantId()),
            'tenantId' => $this->publicTenantId($preference->getTenantId()),
            'inherited' => $this->isInherited($preference->getTenantId()),
            'definitionHash' => $preference->getDefinitionHash(),
            'grid' => [
                'columns' => [
                    'order' => $preference->getColumnsOrder(),
                    'hidden' => $preference->getHiddenColumns(),
                    'widths' => $preference->getColumnWidths(),
                    'frozen' => $preference->getFrozenColumns(),
                    'added' => $preference->getAddedColumns(),
                ],
                'sort' => $preference->getSortConfig(),
                'filter' => $preference->getFilterConfig(),
                'group' => $preference->getGroupConfig(),
                'groupAggregates' => $preference->getGroupAggregates(),
                'mobileTemplate' => $preference->getMobileTemplate(),
            ],
        ];
    }

    private function formatSort(UserSortPreference $preference): array
    {
        return [
            'id' => $preference->getSortId(),
            'name' => $preference->getName(),
            'isDefault' => $preference->isDefaultPreference(),
            'scope' => $this->scopeForTenantId($preference->getTenantId()),
            'tenantId' => $this->publicTenantId($preference->getTenantId()),
            'inherited' => $this->isInherited($preference->getTenantId()),
            'sort' => $preference->getSortConfig(),
        ];
    }

    private function formatGroup(UserGroupPreference $preference): array
    {
        return [
            'id' => $preference->getGroupId(),
            'name' => $preference->getName(),
            'isDefault' => $preference->isDefaultPreference(),
            'scope' => $this->scopeForTenantId($preference->getTenantId()),
            'tenantId' => $this->publicTenantId($preference->getTenantId()),
            'inherited' => $this->isInherited($preference->getTenantId()),
            'group' => $preference->getGroupConfig(),
            'aggregates' => $preference->getAggregates(),
        ];
    }

    private function formatFilter(UserFilterPreference $preference): array
    {
        return [
            'id' => $preference->getFilterId(),
            'name' => $preference->getName(),
            'isDefault' => $preference->isDefaultPreference(),
            'scope' => $this->scopeForTenantId($preference->getTenantId()),
            'tenantId' => $this->publicTenantId($preference->getTenantId()),
            'inherited' => $this->isInherited($preference->getTenantId()),
            'filters' => $preference->getFilters(),
        ];
    }

    private function formatMobileTemplate(UserMobileGridTemplatePreference $preference): array
    {
        $template = $preference->getPayload();

        return [
            'id' => $preference->getTemplateId(),
            'name' => $preference->getName(),
            'isDefault' => $preference->isDefaultPreference(),
            'scope' => $this->scopeForTenantId($preference->getTenantId()),
            'tenantId' => $this->publicTenantId($preference->getTenantId()),
            'inherited' => $this->isInherited($preference->getTenantId()),
            'titleField' => $preference->getTitleField(),
            'subtitleField' => $preference->getSubtitleField(),
            'badges' => $preference->getBadgeFields(),
            'fields' => $preference->getFieldPositions(),
            'tabs' => $preference->getTabs(),
            'template' => $template,
        ];
    }

    /**
     * @return UserGridLayoutPreference[]
     */
    private function layoutsForTenant(string $screenId, string $tenantId): array
    {
        return $this->layouts->findForUser($tenantId, $this->userId(), $screenId);
    }

    /**
     * @return UserSortPreference[]
     */
    private function sortsForTenant(string $screenId, string $tenantId): array
    {
        return $this->sorts->findForUser($tenantId, $this->userId(), $screenId);
    }

    /**
     * @return UserGroupPreference[]
     */
    private function groupsForTenant(string $screenId, string $tenantId): array
    {
        return $this->groups->findForUser($tenantId, $this->userId(), $screenId);
    }

    /**
     * @return UserFilterPreference[]
     */
    private function filtersForTenant(string $screenId, string $tenantId): array
    {
        return $this->filters->findForUser($tenantId, $this->userId(), $screenId);
    }

    /**
     * @return UserMobileGridTemplatePreference[]
     */
    private function mobileTemplatesForTenant(string $screenId, string $tenantId): array
    {
        return $this->mobileTemplates->findForUser($tenantId, $this->userId(), $screenId);
    }

    private function clearDefaultLayouts(string $screenId, string $tenantId, string $currentId, bool $isDefault): void
    {
        if (!$isDefault) {
            return;
        }
        foreach ($this->layoutsForTenant($screenId, $tenantId) as $item) {
            if ($item->getLayoutId() !== $currentId) {
                $item->setDefaultPreference(false);
            }
        }
    }

    private function clearDefaultSorts(string $screenId, string $tenantId, string $currentId, bool $isDefault): void
    {
        if (!$isDefault) {
            return;
        }
        foreach ($this->sortsForTenant($screenId, $tenantId) as $item) {
            if ($item->getSortId() !== $currentId) {
                $item->setDefaultPreference(false);
            }
        }
    }

    private function clearDefaultGroups(string $screenId, string $tenantId, string $currentId, bool $isDefault): void
    {
        if (!$isDefault) {
            return;
        }
        foreach ($this->groupsForTenant($screenId, $tenantId) as $item) {
            if ($item->getGroupId() !== $currentId) {
                $item->setDefaultPreference(false);
            }
        }
    }

    private function clearDefaultFilters(string $screenId, string $tenantId, string $currentId, bool $isDefault): void
    {
        if (!$isDefault) {
            return;
        }
        foreach ($this->filtersForTenant($screenId, $tenantId) as $item) {
            if ($item->getFilterId() !== $currentId) {
                $item->setDefaultPreference(false);
            }
        }
    }

    private function clearDefaultMobileTemplates(string $screenId, string $tenantId, string $currentId, bool $isDefault): void
    {
        if (!$isDefault) {
            return;
        }
        foreach ($this->mobileTemplatesForTenant($screenId, $tenantId) as $item) {
            if ($item->getTemplateId() !== $currentId) {
                $item->setDefaultPreference(false);
            }
        }
    }

    /**
     * @param array<int, array{id: string}> $globalItems
     * @param array<int, array{id: string}> $tenantItems
     * @return array<int, array<string, mixed>>
     */
    private function mergePreferences(array $globalItems, array $tenantItems): array
    {
        $merged = [];
        foreach ($globalItems as $item) {
            $merged[$item['id']] = $item;
        }
        foreach ($tenantItems as $item) {
            $merged[$item['id']] = $item;
        }

        return array_values($merged);
    }

    /**
     * @return string[]
     */
    private function deleteTenantCandidates(array $payload): array
    {
        $tenantId = $this->preferenceTenantId($payload);
        if ($tenantId === self::GLOBAL_TENANT_ID) {
            return [self::GLOBAL_TENANT_ID];
        }

        return [$this->tenantId(), self::GLOBAL_TENANT_ID];
    }

    /**
     * @param string[] $tenantIds
     */
    private function findGridLayoutForDelete(string $screenId, string $id, array $tenantIds): ?UserGridLayoutPreference
    {
        foreach ($tenantIds as $tenantId) {
            $preference = $this->layouts->findOneForUser($tenantId, $this->userId(), $screenId, $id);
            if ($preference) {
                return $preference;
            }
        }

        return null;
    }

    /**
     * @param string[] $tenantIds
     */
    private function findSortForDelete(string $screenId, string $id, array $tenantIds): ?UserSortPreference
    {
        foreach ($tenantIds as $tenantId) {
            $preference = $this->sorts->findOneForUser($tenantId, $this->userId(), $screenId, $id);
            if ($preference) {
                return $preference;
            }
        }

        return null;
    }

    /**
     * @param string[] $tenantIds
     */
    private function findGroupForDelete(string $screenId, string $id, array $tenantIds): ?UserGroupPreference
    {
        foreach ($tenantIds as $tenantId) {
            $preference = $this->groups->findOneForUser($tenantId, $this->userId(), $screenId, $id);
            if ($preference) {
                return $preference;
            }
        }

        return null;
    }

    /**
     * @param string[] $tenantIds
     */
    private function findFilterForDelete(string $screenId, string $id, array $tenantIds): ?UserFilterPreference
    {
        foreach ($tenantIds as $tenantId) {
            $preference = $this->filters->findOneForUser($tenantId, $this->userId(), $screenId, $id);
            if ($preference) {
                return $preference;
            }
        }

        return null;
    }

    /**
     * @param string[] $tenantIds
     */
    private function findMobileTemplateForDelete(string $screenId, string $id, array $tenantIds): ?UserMobileGridTemplatePreference
    {
        foreach ($tenantIds as $tenantId) {
            $preference = $this->mobileTemplates->findOneForUser($tenantId, $this->userId(), $screenId, $id);
            if ($preference) {
                return $preference;
            }
        }

        return null;
    }

    private function findDefault(array $items): ?array
    {
        foreach ($items as $item) {
            if (!empty($item['isDefault'])) {
                return $item;
            }
        }
        return null;
    }

    private function preferenceTenantId(array $payload): string
    {
        $scope = strtolower(trim((string) ($payload['scope'] ?? $payload['tenantScope'] ?? '')));
        $allTenants = (bool) ($payload['applyToAllTenants'] ?? $payload['allSubscribers'] ?? false);
        if ($scope === 'global' || $scope === 'all' || $scope === 'todos' || $allTenants) {
            return self::GLOBAL_TENANT_ID;
        }

        return $this->tenantId();
    }

    private function scopeForTenantId(string $tenantId): string
    {
        return $tenantId === self::GLOBAL_TENANT_ID ? 'global' : 'tenant';
    }

    private function publicTenantId(string $tenantId): ?string
    {
        return $tenantId === self::GLOBAL_TENANT_ID ? null : $tenantId;
    }

    private function isInherited(string $tenantId): bool
    {
        return $tenantId === self::GLOBAL_TENANT_ID && $this->tenantId() !== self::GLOBAL_TENANT_ID;
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private function metadata(array $payload, string $prefix): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeHttpException('PREFERENCE_NAME_REQUIRED', 'Informe o nome da preferencia.');
        }

        $id = trim((string) ($payload['id'] ?? ''));
        if ($id === '') {
            $id = $prefix . '-' . bin2hex(random_bytes(4));
        }

        return [
            mb_substr(preg_replace('/[^A-Za-z0-9_.:-]+/', '-', $id) ?: $prefix, 0, 80),
            mb_substr($name, 0, 160),
            (bool) ($payload['isDefault'] ?? false),
        ];
    }

    private function emptyGridLayout(): array
    {
        return [
            'columns' => [
                'order' => [],
                'hidden' => [],
                'widths' => [],
                'frozen' => [],
                'added' => [],
            ],
            'sort' => [],
            'filter' => null,
            'filterPreset' => [],
            'group' => [],
            'groupAggregates' => [],
            'mobileTemplate' => null,
        ];
    }

    private function normalizeFieldList(mixed $items): array
    {
        $fields = [];
        foreach ($this->arrayValue($items) as $item) {
            if (!is_string($item) && !is_numeric($item)) {
                continue;
            }
            $field = trim((string) $item);
            if (!$this->isFieldName($field) || isset($fields[$field])) {
                continue;
            }
            $fields[$field] = $field;
        }

        return array_values($fields);
    }

    private function normalizeColumnWidths(mixed $items): array
    {
        $widths = [];
        foreach ($this->arrayValue($items) as $field => $value) {
            if (!$this->isFieldName((string) $field)) {
                continue;
            }
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }
            $width = mb_substr(trim((string) $value), 0, 40);
            if ($width !== '') {
                $widths[(string) $field] = $width;
            }
        }

        return $widths;
    }

    private function normalizeSort(mixed $items): array
    {
        $sort = [];
        foreach ($this->arrayValue($items) as $item) {
            if (!is_array($item) || !$this->isFieldName((string) ($item['field'] ?? ''))) {
                continue;
            }
            $sort[] = [
                'field' => (string) $item['field'],
                'dir' => ($item['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            ];
        }

        return $sort;
    }

    private function normalizeGroup(mixed $items): array
    {
        $group = [];
        foreach ($this->arrayValue($items) as $item) {
            if (!is_array($item) || !$this->isFieldName((string) ($item['field'] ?? ''))) {
                continue;
            }
            $group[] = [
                'field' => (string) $item['field'],
                'dir' => ($item['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            ];
        }

        return $group;
    }

    private function normalizeAggregates(mixed $items): array
    {
        $aggregates = [];
        foreach ($this->arrayValue($items) as $item) {
            if (!is_array($item) || !$this->isFieldName((string) ($item['field'] ?? ''))) {
                continue;
            }
            $aggregate = (string) ($item['aggregate'] ?? '');
            if (!in_array($aggregate, ['count', 'sum'], true)) {
                continue;
            }
            $aggregates[] = [
                'field' => (string) $item['field'],
                'aggregate' => $aggregate,
            ];
        }

        return $aggregates;
    }

    private function normalizeFilter(mixed $filter): ?array
    {
        if (!is_array($filter)) {
            return null;
        }

        return $this->normalizeJsonObject($filter);
    }

    private function normalizeJsonList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = $this->normalizeJsonValue($item);
        }

        return $result;
    }

    private function normalizeMobileTemplate(mixed $template): ?array
    {
        if (!is_array($template)) {
            return null;
        }

        $titleField = $this->fieldOrNull($template['titleField'] ?? null);
        $subtitleField = $this->fieldOrNull($template['subtitleField'] ?? null);
        $fields = $this->normalizeFieldList($template['fields'] ?? $template['fieldPositions'] ?? []);
        $badges = $this->normalizeFieldList($template['badges'] ?? $template['badgeFields'] ?? []);
        $tabs = $this->normalizeMobileTabs($template['tabs'] ?? []);

        $normalized = [
            'titleField' => $titleField,
            'subtitleField' => $subtitleField,
            'badges' => $badges,
            'fields' => $fields,
            'tabs' => $tabs,
        ];

        if ($titleField === null && $subtitleField === null && !$fields && !$badges && empty($tabs['items'])) {
            return null;
        }

        return $normalized;
    }

    private function normalizeMobileTabs(mixed $tabs): array
    {
        $tabs = $this->arrayValue($tabs);
        $items = [];
        foreach ($this->arrayValue($tabs['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $fields = $this->normalizeFieldList($item['fields'] ?? []);
            if (!$fields) {
                continue;
            }
            $items[] = [
                'id' => mb_substr(preg_replace('/[^A-Za-z0-9_.:-]+/', '-', (string) ($item['id'] ?? 'tab')) ?: 'tab', 0, 80),
                'title' => mb_substr(trim((string) ($item['title'] ?? $item['id'] ?? 'Aba')), 0, 120),
                'fields' => $fields,
            ];
        }

        return [
            'enabled' => (bool) ($tabs['enabled'] ?? false) && (bool) $items,
            'items' => $items,
        ];
    }

    private function normalizeJsonObject(array $value, int $depth = 0): array
    {
        if ($depth > 6) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) && !is_numeric($key)) {
                continue;
            }
            $normalized[mb_substr((string) $key, 0, 120)] = $this->normalizeJsonValue($item, $depth + 1);
        }

        return $normalized;
    }

    private function normalizeJsonValue(mixed $value, int $depth = 0): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            return mb_substr($value, 0, 2000);
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn (mixed $item) => $this->normalizeJsonValue($item, $depth + 1), $value);
            }
            return $this->normalizeJsonObject($value, $depth);
        }

        return null;
    }

    private function fieldOrNull(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $field = trim((string) $value);

        return $this->isFieldName($field) ? $field : null;
    }

    private function isFieldName(string $field): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) === 1;
    }

    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function tenantId(): string
    {
        return $this->permissions->getTenantId();
    }

    private function userId(): string
    {
        return $this->permissions->getUserId();
    }
}
