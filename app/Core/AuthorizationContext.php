<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Server-built authorization context for the authenticated user.
 */
final class AuthorizationContext
{
    /**
     * @param list<string> $systemRoleKeys
     * @param array<string, list<int>> $organizationRolesByKey
     */
    public function __construct(
        private readonly int $authenticatedUserId,
        private readonly array $systemRoleKeys = [],
        private readonly array $organizationRolesByKey = [],
        private readonly ?int $resourceOrganizationId = null,
    ) {
    }

    public function authenticatedUserId(): int
    {
        return $this->authenticatedUserId;
    }

    /**
     * @return list<string>
     */
    public function systemRoleKeys(): array
    {
        return $this->systemRoleKeys;
    }

    /**
     * @return array<string, list<int>>
     */
    public function organizationRolesByKey(): array
    {
        return $this->organizationRolesByKey;
    }

    public function resourceOrganizationId(): ?int
    {
        return $this->resourceOrganizationId;
    }

    public function withResourceOrganizationId(?int $organizationId): self
    {
        return new self(
            $this->authenticatedUserId,
            $this->systemRoleKeys,
            $this->organizationRolesByKey,
            $organizationId
        );
    }

    public function hasSystemRole(string $roleKey): bool
    {
        return in_array($roleKey, $this->systemRoleKeys, true);
    }

    public function hasOrganizationRole(string $roleKey, int $organizationId): bool
    {
        return in_array($organizationId, $this->organizationRolesByKey[$roleKey] ?? [], true);
    }

    public function hasAnyOrganizationRole(string $roleKey): bool
    {
        return ($this->organizationRolesByKey[$roleKey] ?? []) !== [];
    }

    /**
     * @param list<string> $roleKeys
     */
    public function hasAnyRole(array $roleKeys): bool
    {
        foreach ($roleKeys as $roleKey) {
            if ($this->hasSystemRole($roleKey) || $this->hasAnyOrganizationRole($roleKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function organizationIdsForRole(string $roleKey): array
    {
        return $this->organizationRolesByKey[$roleKey] ?? [];
    }

    /**
     * @return list<int>
     */
    public function allowedOrganizationIds(): array
    {
        $ids = [];

        foreach ($this->organizationRolesByKey as $organizationIds) {
            foreach ($organizationIds as $organizationId) {
                $ids[] = $organizationId;
            }
        }

        sort($ids);

        return array_values(array_unique($ids));
    }
}
