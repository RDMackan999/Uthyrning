<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
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

    /**
     * Find active organizations for admin selection.
     *
     * @return Collection<Organization>
     */
    public function findAllActive(): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM organizations
             WHERE status_key = :status_key
                AND deleted_at IS NULL
             ORDER BY name ASC, id ASC'
        );
        $statement->execute(['status_key' => 'active']);

        return new Collection(array_map(
            static fn (array $row): Organization => new Organization($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        ));
    }

    /**
     * Create an active organization for first-admin provisioning.
     */
    public function createOrganization(string $name): Organization
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO organizations (
                name,
                status_key,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :status_key,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'name' => $name,
            'status_key' => 'active',
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }
}
