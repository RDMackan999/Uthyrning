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
    public function findAllActive(?array $organizationIds = null): Collection
    {
        $params = ['status_key' => 'active'];
        $scopeSql = $this->organizationScopeSql($organizationIds, $params);

        $statement = Database::pdo()->prepare(
            'SELECT * FROM organizations
             WHERE status_key = :status_key
                AND deleted_at IS NULL
                ' . $scopeSql . '
             ORDER BY name ASC, id ASC'
        );
        $statement->execute($params);

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

    /**
     * @param list<int>|null $organizationIds
     * @param array<string, mixed> $params
     */
    private function organizationScopeSql(?array $organizationIds, array &$params): string
    {
        if ($organizationIds === null) {
            return '';
        }

        $ids = array_values(array_filter(
            array_unique(array_map(static fn (mixed $id): int => (int) $id, $organizationIds)),
            static fn (int $id): bool => $id > 0
        ));

        if ($ids === []) {
            return 'AND 1 = 0';
        }

        $placeholders = [];

        foreach ($ids as $index => $organizationId) {
            $name = 'organization_id_' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $organizationId;
        }

        return 'AND id IN (' . implode(', ', $placeholders) . ')';
    }
}
