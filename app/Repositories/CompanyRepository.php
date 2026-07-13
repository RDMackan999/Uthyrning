<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\Company;
use PDO;

/**
 * Repository for company identity records.
 */
final class CompanyRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Company::class);
    }

    /**
     * Find an active company by primary key.
     */
    public function findById(int|string $id): Company
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM companies WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Company not found.');
        }

        return new Company($row);
    }

    /**
     * Create an active company connected to an organization.
     */
    public function createForOrganization(int $organizationId, string $name): Company
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO companies (
                organization_id,
                name,
                status_key,
                created_at,
                updated_at
            ) VALUES (
                :organization_id,
                :name,
                :status_key,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'name' => $name,
            'status_key' => 'active',
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Connect a user to a company.
     */
    public function attachUser(int $companyId, int $userId): void
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO company_users (
                company_id,
                user_id,
                status_key,
                created_at,
                updated_at
            ) VALUES (
                :company_id,
                :user_id,
                :status_key,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
            'status_key' => 'active',
        ]);
    }
}
