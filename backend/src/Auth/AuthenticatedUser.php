<?php

namespace App\Auth;

class AuthenticatedUser
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $userId,
        private readonly string $username,
        private readonly ?string $displayName,
        private readonly ?string $email,
        private readonly array $groups,
        private readonly array $permissions,
        private readonly string $source,
        private readonly bool $forcePasswordChange = false,
    ) {
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getDisplayName(): string
    {
        return $this->displayName ?: $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function mustChangePassword(): bool
    {
        return $this->forcePasswordChange;
    }

    public function toPayload(): array
    {
        $name = $this->getDisplayName();

        return [
            'id' => $this->userId,
            'username' => $this->username,
            'name' => $name,
            'email' => $this->email,
            'initials' => $this->initials($name),
            'groups' => $this->groups,
            'permissions' => $this->permissions,
            'source' => $this->source,
            'forcePasswordChange' => $this->forcePasswordChange,
        ];
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
