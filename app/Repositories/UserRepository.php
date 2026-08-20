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
     * Find active users for system-admin assignment selection.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllActiveForAdmin(?string $query = null): array
    {
        $params = ['status_key' => 'active'];
        $sql = 'SELECT id,
                email,
                email_normalized,
                first_name,
                last_name,
                status_key,
                created_at
             FROM users
             WHERE status_key = :status_key
                AND deleted_at IS NULL';

        $search = $this->nullableSearch($query);
        if ($search !== null) {
            $sql .= ' AND (
                email_normalized LIKE :query_email
                OR first_name LIKE :query_first_name
                OR last_name LIKE :query_last_name
             )';
            $wildcard = '%' . $search . '%';
            $params['query_email'] = $wildcard;
            $params['query_first_name'] = $wildcard;
            $params['query_last_name'] = $wildcard;
        }

        $sql .= ' ORDER BY email_normalized ASC, id ASC LIMIT 250';

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
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

    private function nullableSearch(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }

        $normalized = trim(strtolower($query));

        return $normalized === '' ? null : substr($normalized, 0, 120);
    }
}
