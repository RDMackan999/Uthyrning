<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\Organization;
use PDO;

/**
 * Repository for organization identity records.
 */
final class OrganizationRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Organization::class);
    }

    /**
     * Find an active organization by primary key.
     */
    public function findById(int|string $id): Organization
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM organizations WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Organization not found.');
        }

        return new Organization($row);
    }
}
