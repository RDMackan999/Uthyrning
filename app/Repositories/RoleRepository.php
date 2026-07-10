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
}
