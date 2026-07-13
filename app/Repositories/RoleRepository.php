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
}
