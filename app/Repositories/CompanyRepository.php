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
}
