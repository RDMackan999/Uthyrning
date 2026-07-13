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

    /**
     * Determine whether an email already exists, including soft-deleted users.
     */
    public function emailExists(string $email): bool
    {
        $statement = Database::pdo()->prepare(
            'SELECT 1 FROM users WHERE email_normalized = :email LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Create a local password user for first-admin provisioning.
     */
    public function createLocalUser(
        string $email,
        string $passwordHash,
        ?string $firstName = null,
        ?string $lastName = null
    ): User {
        $statement = Database::pdo()->prepare(
            'INSERT INTO users (
                email,
                email_normalized,
                password_hash,
                first_name,
                last_name,
                status_key,
                created_at,
                updated_at
            ) VALUES (
                :email,
                :email_normalized,
                :password_hash,
                :first_name,
                :last_name,
                :status_key,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'email' => $email,
            'email_normalized' => strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status_key' => 'active',
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }
}
