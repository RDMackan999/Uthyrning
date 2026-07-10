<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\Permission;
use PDO;

/**
 * Repository for permission identity records.
 */
final class PermissionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Permission::class);
    }

    /**
     * Find an active permission by primary key.
     */
    public function findById(int|string $id): Permission
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM permissions WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Permission not found.');
        }

        return new Permission($row);
    }
}
