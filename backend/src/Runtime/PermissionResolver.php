<?php

namespace App\Runtime;

use App\Auth\AuthenticatedSessionResolver;
use App\Entity\RuntimeEndpoint;
use App\Entity\RuntimeUserSession;
use App\Entity\ScreenDefinition;
use Symfony\Component\HttpFoundation\RequestStack;

class PermissionResolver
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ?AuthenticatedSessionResolver $authenticatedSessions = null,
    ) {
    }

    public function getTenantId(): string
    {
        if ($session = $this->getAuthenticatedSession()) {
            return $session->getTenantId();
        }

        return $this->cleanRequestValue('X-Runtime-Tenant-Id', ['runtimeTenantId', 'tenantId'], 'default');
    }

    public function getUserId(): string
    {
        if ($session = $this->getAuthenticatedSession()) {
            return $session->getUserId();
        }

        return $this->cleanRequestValue('X-Runtime-User-Id', ['runtimeUserId', 'demoUserId', 'userId'], $this->cleanRequestValue('X-Demo-User', ['demoUserId'], 'demo'));
    }

    public function getSessionId(): string
    {
        if ($session = $this->getAuthenticatedSession()) {
            return $session->getSessionId();
        }

        return $this->cleanRequestValue('X-Runtime-Session-Id', ['runtimeSessionId', 'sessionId'], 'demo-session');
    }

    public function getCurrentUserPayload(): array
    {
        if ($session = $this->getAuthenticatedSession()) {
            $snapshot = $session->getPermissionSnapshot();
            $groups = is_array($snapshot['groups'] ?? null) ? $snapshot['groups'] : [];
            $permissions = is_array($snapshot['permissions'] ?? null) ? $snapshot['permissions'] : [];
            $impersonation = is_array($snapshot['impersonation'] ?? null) ? $snapshot['impersonation'] : null;
            if (is_array($snapshot['user'] ?? null)) {
                $payload = array_replace([
                    'id' => $session->getUserId(),
                    'name' => $session->getUserName() ?: $session->getUserId(),
                    'email' => null,
                    'initials' => $this->initials($session->getUserName() ?: $session->getUserId()),
                    'groups' => $groups,
                    'permissions' => $permissions,
                    'favoritePrograms' => [],
                ], $snapshot['user']);
                if (($impersonation['enabled'] ?? false) === true) {
                    $payload['impersonation'] = $impersonation;
                }
                return $payload;
            }

            $payload = [
                'id' => $session->getUserId(),
                'name' => $session->getUserName() ?: $session->getUserId(),
                'email' => null,
                'initials' => $this->initials($session->getUserName() ?: $session->getUserId()),
                'groups' => $groups,
                'permissions' => $permissions,
                'favoritePrograms' => [],
            ];
            if (($impersonation['enabled'] ?? false) === true) {
                $payload['impersonation'] = $impersonation;
            }
            return $payload;
        }

        $userId = $this->getUserId();
        $name = $this->cleanRequestValue('X-Runtime-User-Name', ['runtimeUserName', 'userName'], $userId === 'demo' ? 'Usuario Demo' : $userId);
        $groups = $this->readRequestList('X-Runtime-Groups', ['runtimeGroups'], ['admin', 'vendas']);
        $permissions = $this->readRequestList('X-Runtime-Permissions', ['runtimePermissions'], []);

        return [
            'id' => $userId,
            'name' => $name,
            'email' => $userId . '@example.com',
            'initials' => $this->initials($name),
            'groups' => $groups,
            'permissions' => $permissions,
            'favoritePrograms' => ['clientes-crud'],
        ];
    }

    public function canReadScreen(ScreenDefinition $screen): bool
    {
        if ($screen->getStatus() === 'disabled') {
            return false;
        }

        $definition = $screen->getDefinition();
        if (!$this->passesSecurity($definition['security'] ?? [])) {
            return false;
        }

        $programPermission = $definition['program']['permission'] ?? null;
        if (is_string($programPermission) && trim($programPermission) !== '' && !$this->hasPermission($programPermission)) {
            return false;
        }

        return true;
    }

    public function canExecuteEndpoint(RuntimeEndpoint $endpoint): bool
    {
        if (!$endpoint->isEnabled()) {
            return false;
        }

        $permission = $this->endpointPermissions($endpoint);
        if (!$permission) {
            return true;
        }

        return $this->hasAnyPermission($permission);
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    public function applyDefinitionPermissions(array $definition): array
    {
        if (is_array($definition['permissions'] ?? null)) {
            $filtered = [];
            foreach ($definition['permissions'] as $key => $value) {
                $filtered[$key] = $this->isDefinitionPermissionAllowed((string) $key, $value, $definition);
            }
            $definition['permissions'] = $filtered;
        }

        if (($definition['pageType'] ?? '') === 'home') {
            return $this->filterHomeDefinition($definition);
        }

        return $definition;
    }

    /**
     * @param string|string[] $permissions
     */
    public function hasAnyPermission(string|array $permissions): bool
    {
        $items = array_values(array_filter(array_map('strval', is_array($permissions) ? $permissions : [$permissions])));
        if (!$items) {
            return true;
        }

        foreach ($items as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        $permission = $this->normalizePermission($permission);
        if ($permission === '') {
            return true;
        }

        $parsed = $this->currentPermissionSets();
        foreach ($parsed['deny'] as $denied) {
            if ($this->permissionMatches($denied, $permission)) {
                return false;
            }
        }

        foreach ($parsed['allow'] as $allowed) {
            if ($this->permissionMatches($allowed, $permission)) {
                return true;
            }
        }

        return $this->isAdministrator();
    }

    private function cleanHeader(string $name, string $fallback): string
    {
        return $this->cleanRequestValue($name, [], $fallback);
    }

    private function getAuthenticatedSession(): ?RuntimeUserSession
    {
        return $this->authenticatedSessions?->resolve();
    }

    /**
     * @param array<string, mixed>|mixed $security
     */
    private function passesSecurity(mixed $security): bool
    {
        if (!is_array($security) || $security === []) {
            return true;
        }
        if ($this->isAdministrator()) {
            return true;
        }

        $groups = $this->normalizeList($security['userGroups'] ?? $security['groups'] ?? $security['requiredGroups'] ?? []);
        if ($groups) {
            $mode = strtolower((string) ($security['groupMode'] ?? ($security['requireAllGroups'] ?? false ? 'all' : 'any')));
            $userGroups = $this->currentGroups();
            $matched = array_intersect($groups, $userGroups);
            if ($mode === 'all') {
                if (count($matched) !== count($groups)) {
                    return false;
                }
            } elseif (!$matched) {
                return false;
            }
        }

        $anyPermissions = $this->normalizeList($security['anyPermissions'] ?? $security['permissions'] ?? $security['permission'] ?? []);
        if ($anyPermissions && !$this->hasAnyPermission($anyPermissions)) {
            return false;
        }

        $allPermissions = $this->normalizeList($security['allPermissions'] ?? []);
        foreach ($allPermissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function endpointPermissions(RuntimeEndpoint $endpoint): array
    {
        $direct = trim((string) ($endpoint->getPermission() ?? ''));
        if ($direct !== '') {
            return [$direct];
        }

        $config = $endpoint->getConfig();
        if (is_string($config['permission'] ?? null) && trim($config['permission']) !== '') {
            return [(string) $config['permission']];
        }
        if (is_array($config['permissions'] ?? null)) {
            return $this->normalizeList($config['permissions']);
        }

        $endpointId = $endpoint->getEndpointId();
        $handler = $endpoint->getHandler();
        if ($endpointId === 'runtime.admin.forceLogout') {
            return ['admin.sessions.revoke', 'admin.write'];
        }
        if ($endpointId === 'runtime.admin.impersonateStart') {
            return ['admin.impersonate'];
        }
        if ($endpointId === 'runtime.admin.impersonateStop') {
            return [];
        }
        if (str_starts_with($endpointId, 'runtime.messages.') || str_starts_with($endpointId, 'runtime.lock.')) {
            return [];
        }
        if (str_starts_with($handler, 'layout.')) {
            return ['user.preferences'];
        }
        if ($handler === 'help.markAsRead') {
            return ['user.preferences'];
        }
        if (str_starts_with($handler, 'home.')) {
            return ['home.read'];
        }

        $suffix = $this->permissionSuffix((string) ($config['actionId'] ?? $config['operation'] ?? $endpointId));
        $aliases = $this->permissionAliases($endpoint, $config);
        if (!$suffix || !$aliases) {
            return [];
        }

        return array_map(static fn (string $alias): string => $alias . '.' . $suffix, $aliases);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return string[]
     */
    private function permissionAliases(RuntimeEndpoint $endpoint, array $config): array
    {
        $aliases = [];
        foreach (['permissionPrefix', 'entityCode', 'programId'] as $key) {
            if (is_string($config[$key] ?? null) && trim((string) $config[$key]) !== '') {
                $aliases[] = (string) $config[$key];
            }
        }
        $aliases[] = $endpoint->getScreenId();

        $entityCode = (string) ($config['entityCode'] ?? '');
        if ($entityCode === 'cliente') {
            $aliases[] = 'clientes';
        }

        return array_values(array_unique(array_filter(array_map([$this, 'normalizePermission'], $aliases))));
    }

    private function permissionSuffix(string $action): string
    {
        return match ($action) {
            'read', 'get', 'list', 'statusHistory', 'stepHistory',
            'printClienteExcel', 'printClientePdf', 'printClienteCsv', 'loadCidadesByUf' => 'read',
            'create', 'insert' => 'create',
            'update', 'edit', 'validateStatusCliente', 'checkCredit', 'sendWelcome',
            'sendWhatsapp', 'bulkActivate', 'bulkInactivate' => 'edit',
            'delete', 'remove', 'bulkDelete' => 'delete',
            default => $this->normalizePermission($action),
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isDefinitionPermissionAllowed(string $key, mixed $value, array $definition): bool
    {
        if ($value === false || $value === null) {
            return false;
        }
        if (is_string($value) && trim($value) !== '') {
            return $this->hasPermission($value);
        }
        if (is_array($value)) {
            $any = $this->normalizeList($value['any'] ?? $value['permissions'] ?? []);
            $all = $this->normalizeList($value['all'] ?? []);
            if ($any && !$this->hasAnyPermission($any)) {
                return false;
            }
            foreach ($all as $permission) {
                if (!$this->hasPermission($permission)) {
                    return false;
                }
            }
            return (bool) ($any || $all);
        }

        return $this->hasAnyPermission($this->definitionPermissionCandidates($key, $definition));
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return string[]
     */
    private function definitionPermissionCandidates(string $key, array $definition): array
    {
        $key = $this->normalizePermission($key);
        $suffix = $this->permissionSuffix($key);
        $candidates = [$key];

        if ($key === 'savelayout' || $key === 'save_layout') {
            $candidates[] = 'user.preferences';
            $suffix = 'saveLayout';
        }

        $aliases = array_filter([
            $definition['runtime']['entityCode'] ?? null,
            $definition['program']['entity'] ?? null,
            $definition['runtime']['programId'] ?? null,
            $definition['program']['id'] ?? null,
            $definition['screenId'] ?? null,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        foreach ($aliases as $alias) {
            $alias = $this->normalizePermission((string) $alias);
            if ($alias !== '') {
                $candidates[] = $alias . '.' . $suffix;
            }
        }
        if (($definition['runtime']['entityCode'] ?? null) === 'cliente') {
            $candidates[] = 'clientes.' . $suffix;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    private function filterHomeDefinition(array $definition): array
    {
        $programs = [];
        $allowedProgramIds = [];
        foreach (($definition['programs'] ?? []) as $program) {
            if (!is_array($program) || !$this->isProgramAllowed($program)) {
                continue;
            }
            $programs[] = $program;
            if (isset($program['id'])) {
                $allowedProgramIds[(string) $program['id']] = true;
            }
        }
        $definition['programs'] = $programs;

        if (is_array($definition['navigation']['groups'] ?? null)) {
            $groups = [];
            foreach ($definition['navigation']['groups'] as $group) {
                if (!is_array($group)) {
                    continue;
                }
                $items = [];
                foreach (($group['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $programId = (string) ($item['programId'] ?? '');
                    if ($programId === '' || isset($allowedProgramIds[$programId])) {
                        $items[] = $item;
                    }
                }
                if ($items) {
                    $group['items'] = $items;
                    $groups[] = $group;
                }
            }
            $definition['navigation']['groups'] = $groups;
        }

        if (is_array($definition['layout']['appbar']['userMenu']['items'] ?? null)) {
            $definition['layout']['appbar']['userMenu']['items'] = array_values(array_filter(
                $definition['layout']['appbar']['userMenu']['items'],
                fn (mixed $item): bool => !is_array($item) || $this->isPermissionReferenceAllowed($item['permission'] ?? null),
            ));
        }

        return $definition;
    }

    /**
     * @param array<string, mixed> $program
     */
    private function isProgramAllowed(array $program): bool
    {
        return $this->isPermissionReferenceAllowed($program['permission'] ?? null);
    }

    private function isPermissionReferenceAllowed(mixed $permission): bool
    {
        if (!is_string($permission) || trim($permission) === '') {
            return true;
        }

        return $this->hasPermission($permission);
    }

    private function isAdministrator(): bool
    {
        return in_array('admin', $this->currentGroups(), true)
            || in_array('*', $this->currentPermissionSets()['allow'], true);
    }

    /**
     * @return string[]
     */
    private function currentGroups(): array
    {
        return $this->normalizeList($this->getCurrentUserPayload()['groups'] ?? []);
    }

    /**
     * @return array{allow: string[], deny: string[]}
     */
    private function currentPermissionSets(): array
    {
        $map = [];
        $raw = $this->getCurrentUserPayload()['permissions'] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $this->collectPermissionEntries('', $raw, $map);

        $allow = [];
        $deny = [];
        foreach ($map as $permission => $allowed) {
            $permission = $this->normalizePermission((string) $permission);
            if ($permission === '') {
                continue;
            }
            if ($allowed) {
                $allow[] = $permission;
            } else {
                $deny[] = $permission;
            }
        }

        return [
            'allow' => array_values(array_unique($allow)),
            'deny' => array_values(array_unique($deny)),
        ];
    }

    /**
     * @param array<int|string, mixed> $permissions
     * @param array<string, bool> $map
     */
    private function collectPermissionEntries(string $prefix, array $permissions, array &$map): void
    {
        foreach ($permissions as $key => $value) {
            if (is_int($key)) {
                $this->addPermissionEntry($this->normalizePermission((string) $value), true, $map);
                continue;
            }

            $permission = $this->normalizePermission((string) $key);
            if ($permission === '') {
                continue;
            }
            $fullPermission = $prefix !== '' ? $prefix . '.' . $permission : $permission;

            if (is_array($value)) {
                if ($this->isAssociativeArray($value)) {
                    $this->collectPermissionEntries($fullPermission, $value, $map);
                    continue;
                }

                foreach ($value as $item) {
                    $nested = $this->normalizePermission((string) $item);
                    if ($nested === '') {
                        continue;
                    }
                    $this->addPermissionEntry($fullPermission !== '' ? $fullPermission . '.' . $nested : $nested, true, $map);
                }
                continue;
            }

            $this->addPermissionEntry($fullPermission, !$this->isPermissionValueDenied($value), $map);
        }
    }

    private function addPermissionEntry(string $permission, bool $allowed, array &$map): void
    {
        if ($permission === '') {
            return;
        }
        $map[$permission] = $allowed;
    }

    private function isPermissionValueDenied(mixed $value): bool
    {
        if ($value === false || $value === 0 || $value === '0') {
            return true;
        }
        if (is_string($value)) {
            return strtolower($value) === 'false' || strtolower($value) === 'nao' || strtolower($value) === 'no';
        }

        return false;
    }

    private function isAssociativeArray(array $value): bool
    {
        return array_values($value) !== $value;
    }

    private function permissionMatches(string $pattern, string $permission): bool
    {
        $pattern = $this->normalizePermission($pattern);
        $permission = $this->normalizePermission($permission);
        if ($pattern === '*' || $pattern === $permission) {
            return true;
        }
        if (str_ends_with($pattern, '.*')) {
            return str_starts_with($permission, substr($pattern, 0, -1));
        }
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/';
            return (bool) preg_match($regex, $permission);
        }

        return false;
    }

    /**
     * @param mixed $value
     *
     * @return string[]
     */
    private function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;|]+/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function (mixed $item): string {
            return $this->normalizePermission((string) $item);
        }, $value))));
    }

    private function normalizePermission(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * @param string[] $queryNames
     * @param string[] $fallback
     *
     * @return string[]
     */
    private function readRequestList(string $headerName, array $queryNames, array $fallback): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $value = $request ? trim((string) $request->headers->get($headerName, '')) : '';
        if ($value === '' && $request) {
            foreach ($queryNames as $queryName) {
                $value = trim((string) $request->query->get($queryName, ''));
                if ($value !== '') {
                    break;
                }
            }
        }
        if ($value === '') {
            return $fallback;
        }

        return $this->normalizeList($value);
    }

    /**
     * EventSource nao permite headers customizados. Enquanto nao houver login real
     * por cookie/token, o runtime aceita os mesmos identificadores tambem na query.
     *
     * @param string[] $queryNames
     */
    private function cleanRequestValue(string $headerName, array $queryNames, string $fallback): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $value = $request ? trim((string) $request->headers->get($headerName, '')) : '';
        if ($value === '' && $request) {
            foreach ($queryNames as $queryName) {
                $value = trim((string) $request->query->get($queryName, ''));
                if ($value !== '') {
                    break;
                }
            }
        }
        if ($value === '') {
            return $fallback;
        }

        return mb_substr(preg_replace('/[^A-Za-z0-9_.:@ -]+/', '', $value) ?: $fallback, 0, 160);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= mb_substr($part, 0, 1);
            }
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return mb_strtoupper($letters ?: 'U');
    }
}
