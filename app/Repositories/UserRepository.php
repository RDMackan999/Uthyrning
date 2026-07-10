<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\User;
use PDO;

/**
 * Repository for user identity records.
 */
final class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(User::class);
    }

    /**
     * Find an active user by primary key.
     */
    public function findById(int|string $id): User
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('User not found.');
        }

        return new User($row);
    }

    /**
     * Find an active user by normalized email.
     */
    public function findByEmail(string $email): ?User
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM users WHERE email_normalized = :email AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new User($row);
    }
}
