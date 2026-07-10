<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\Customer;
use PDO;

/**
 * Repository for customer identity records.
 */
final class CustomerRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Customer::class);
    }

    /**
     * Find an active customer by primary key.
     */
    public function findById(int|string $id): Customer
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM customers WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Customer not found.');
        }

        return new Customer($row);
    }
}
