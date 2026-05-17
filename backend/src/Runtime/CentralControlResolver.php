<?php

namespace App\Runtime;

class CentralControlResolver
{
    public function __construct(
        private readonly DeploymentModeResolver $deploymentMode,
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
    ) {
    }

    public function resolve(): array
    {
        $explicitRole = strtolower(trim((string) ($_SERVER['APP_SYSTEM_ROLE'] ?? $_ENV['APP_SYSTEM_ROLE'] ?? getenv('APP_SYSTEM_ROLE') ?: '')));
        $explicitCentral = $this->readBool($_SERVER['APP_CENTRAL_CONTROL_ENABLED'] ?? $_ENV['APP_CENTRAL_CONTROL_ENABLED'] ?? getenv('APP_CENTRAL_CONTROL_ENABLED'));
        $environment = $this->environmentIdentity->resolve();
        $deploymentMode = $this->deploymentMode->resolve();

        $role = $explicitRole;
        if ($role === '') {
            $role = match (true) {
                $explicitCentral => 'saas_central',
                $deploymentMode === 'onprem' => 'onprem',
                default => 'subscriber',
            };
        }

        $isCentral = in_array($role, ['saas_central', 'central'], true) || $explicitCentral;

        return [
            'systemRole' => $role,
            'centralControl' => $isCentral,
            'deploymentMode' => $deploymentMode,
            'databaseIdentity' => (string) ($environment['databaseIdentity'] ?? 'db:dev'),
            'databaseEnvironment' => (string) ($environment['databaseEnvironment'] ?? 'dev'),
        ];
    }

    public function isCentralControl(): bool
    {
        return ($this->resolve()['centralControl'] ?? false) === true;
    }

    public function readOnlyReason(): string
    {
        $context = $this->resolve();
        if (($context['centralControl'] ?? false) === true) {
            return '';
        }

        return 'Esta operacao existe apenas no sistema central SaaS.';
    }

    private function readBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'sim', 'on'], true);
    }
}
