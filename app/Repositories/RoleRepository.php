<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\Role;
use PDO;

/**
 * Repository for role identity records.
 */
final class RoleRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Role::class);
    }

    /**
     * Find an active role by primary key.
     */
    public function findById(int|string $id): Role
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM roles WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Role not found.');
        }

        return new Role($row);
    }

    /**
     * Find the existing seeded system administrator role.
     */
    public function findSystemAdminRole(): ?Role
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM roles
             WHERE organization_id IS NULL
                AND role_key = :role_key
                AND status_key = :status_key
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'role_key' => 'system_admin',
            'status_key' => 'active',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Role($row);
    }

    /**
     * Find the existing seeded organization administrator role.
     */
    public function findOrganizationAdminRole(): ?Role
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM roles
             WHERE organization_id IS NULL
                AND role_key = :role_key
                AND status_key = :status_key
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'role_key' => 'organization_admin',
            'status_key' => 'active',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Role($row);
    }

    /**
     * Assign an existing role to a user.
     */
    public function assignToUser(int $userId, int $roleId, ?int $organizationId = null): void
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO user_roles (
                user_id,
                role_id,
                organization_id,
                created_at,
                updated_at
            ) VALUES (
                :user_id,
                :role_id,
                :organization_id,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * Determine whether a user has at least one active system-level role.
     *
     * @param list<string> $roleKeys
     */
    public function userHasAnySystemRole(int $userId, array $roleKeys): bool
    {
        $roleKeys = array_values(array_filter(
            array_unique(array_map(static fn (string $roleKey): string => trim($roleKey), $roleKeys)),
            static fn (string $roleKey): bool => $roleKey !== ''
        ));

        if ($roleKeys === []) {
            return false;
        }

        $placeholders = [];
        $params = [
            'user_id' => $userId,
            'status_key' => 'active',
        ];

        foreach ($roleKeys as $index => $roleKey) {
            $name = 'role_key_' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $roleKey;
        }

        $statement = Database::pdo()->prepare(
            'SELECT 1
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             WHERE user_roles.user_id = :user_id
                AND user_roles.organization_id IS NULL
                AND roles.organization_id IS NULL
                AND roles.status_key = :status_key
                AND roles.deleted_at IS NULL
                AND roles.role_key IN (' . implode(', ', $placeholders) . ')
             LIMIT 1'
        );
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Return active global system role keys for one user.
     *
     * @return list<string>
     */
    public function getSystemRolesForUser(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT DISTINCT roles.role_key
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             WHERE user_roles.user_id = :user_id
                AND user_roles.organization_id IS NULL
                AND roles.organization_id IS NULL
                AND roles.status_key = :status_key
                AND roles.deleted_at IS NULL
             ORDER BY roles.role_key ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'status_key' => 'active',
        ]);

        /** @var list<string> $roles */
        $roles = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_map('strval', $roles));
    }

    /**
     * Return active organization-scoped roles for one user.
     *
     * @return list<array{role_key: string, organization_id: int}>
     */
    public function getOrganizationRolesForUser(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT DISTINCT roles.role_key, user_roles.organization_id
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             INNER JOIN organizations ON organizations.id = user_roles.organization_id
             WHERE user_roles.user_id = :user_id
                AND user_roles.organization_id IS NOT NULL
                AND (roles.organization_id IS NULL OR roles.organization_id = user_roles.organization_id)
                AND roles.status_key = :role_status_key
                AND roles.deleted_at IS NULL
                AND organizations.status_key = :organization_status_key
                AND organizations.deleted_at IS NULL
             ORDER BY roles.role_key ASC, user_roles.organization_id ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'role_status_key' => 'active',
            'organization_status_key' => 'active',
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $roles = [];

        foreach ($rows as $row) {
            $roleKey = trim((string) ($row['role_key'] ?? ''));
            $organizationId = (int) ($row['organization_id'] ?? 0);

            if ($roleKey === '' || $organizationId <= 0) {
                continue;
            }

            $roles[] = [
                'role_key' => $roleKey,
                'organization_id' => $organizationId,
            ];
        }

        return $roles;
    }

    /**
     * Return organization ids where the user has one active scoped role.
     *
     * @return list<int>
     */
    public function getOrganizationIdsForRole(int $userId, string $roleKey): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT DISTINCT user_roles.organization_id
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             INNER JOIN organizations ON organizations.id = user_roles.organization_id
             WHERE user_roles.user_id = :user_id
                AND user_roles.organization_id IS NOT NULL
                AND roles.role_key = :role_key
                AND (roles.organization_id IS NULL OR roles.organization_id = user_roles.organization_id)
                AND roles.status_key = :role_status_key
                AND roles.deleted_at IS NULL
                AND organizations.status_key = :organization_status_key
                AND organizations.deleted_at IS NULL
             ORDER BY user_roles.organization_id ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'role_key' => trim($roleKey),
            'role_status_key' => 'active',
            'organization_status_key' => 'active',
        ]);

        /** @var list<int|string> $ids */
        $ids = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_map(static fn (int|string $id): int => (int) $id, $ids));
    }

    /**
     * Determine whether a user has one active role in one organization.
     */
    public function userHasOrganizationRole(int $userId, string $roleKey, int $organizationId): bool
    {
        if ($organizationId <= 0 || trim($roleKey) === '') {
            return false;
        }

        $statement = Database::pdo()->prepare(
            'SELECT 1
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             INNER JOIN organizations ON organizations.id = user_roles.organization_id
             WHERE user_roles.user_id = :user_id
                AND user_roles.organization_id = :organization_id
                AND roles.role_key = :role_key
                AND (roles.organization_id IS NULL OR roles.organization_id = user_roles.organization_id)
                AND roles.status_key = :role_status_key
                AND roles.deleted_at IS NULL
                AND organizations.status_key = :organization_status_key
                AND organizations.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'role_key' => trim($roleKey),
            'role_status_key' => 'active',
            'organization_status_key' => 'active',
        ]);

        return $statement->fetchColumn() !== false;
    }
}
