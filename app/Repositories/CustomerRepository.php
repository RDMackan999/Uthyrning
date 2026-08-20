<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\Customer;
use PDO;

/**
 * Repository for customer identity records.
 */
final class CustomerRepository extends BaseRepository
{
    /**
     * @var list<string>
     */
    private const STATUS_KEYS = ['active', 'inactive', 'blocked'];

    /**
     * @var list<string>
     */
    private const CUSTOMER_TYPE_KEYS = ['private', 'company'];

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
            $this->adminSelectSql() . '
             WHERE customers.id = :id
                AND customers.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Customer not found.');
        }

        return new Customer($row);
    }

    /**
     * Find one non-deleted customer inside an organization.
     */
    public function findByIdForOrganization(int|string $id, int $organizationId): ?Customer
    {
        $statement = Database::pdo()->prepare(
            $this->adminSelectSql() . '
             WHERE customers.id = :id
                AND customers.organization_id = :organization_id
                AND customers.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'organization_id' => $organizationId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Customer($row);
    }

    /**
     * Find non-deleted customers for system administration.
     *
     * @return Collection<Customer>
     */
    public function findAllForAdmin(?string $statusKey = null, ?string $query = null): Collection
    {
        $params = [];
        $where = 'WHERE customers.deleted_at IS NULL';
        $status = $this->nullableStatus($statusKey);
        $search = $this->nullableSearch($query);

        if ($status !== null) {
            $where .= ' AND customers.status_key = :status_key';
            $params['status_key'] = $status;
        }

        if ($search !== null) {
            $where .= ' AND (
                customers.name LIKE :query_name
                OR customers.email_normalized LIKE :query_email
                OR customers.phone LIKE :query_phone
                OR companies.name LIKE :query_company
                OR organizations.name LIKE :query_organization
            )';
            $query = '%' . $search . '%';
            $params['query_name'] = $query;
            $params['query_email'] = $query;
            $params['query_phone'] = $query;
            $params['query_company'] = $query;
            $params['query_organization'] = $query;
        }

        $statement = Database::pdo()->prepare(
            $this->adminSelectSql() . '
             ' . $where . '
             ORDER BY customers.created_at DESC, customers.id DESC'
        );
        $statement->execute($params);

        return $this->collectionFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find one unique non-deleted customer by normalized email in one organization.
     */
    public function findUniqueByNormalizedEmailForOrganization(int $organizationId, string $emailNormalized): ?Customer
    {
        $statement = Database::pdo()->prepare(
            'SELECT *
             FROM customers
             WHERE organization_id = :organization_id
                AND email_normalized = :email_normalized
                AND deleted_at IS NULL
             ORDER BY id ASC
             LIMIT 2'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'email_normalized' => $this->normalizeEmail($emailNormalized),
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return count($rows) === 1 ? new Customer($rows[0]) : null;
    }

    /**
     * Create a customer record without creating a login identity.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Customer
    {
        $organizationId = $this->requiredInt($data['organization_id'] ?? null, 'organization_id');
        $companyId = $this->nullableInt($data['company_id'] ?? null);
        $email = $this->nullableString($data['email'] ?? null);
        $customerTypeKey = $this->normalizeType((string) ($data['customer_type_key'] ?? 'private'));

        $statement = Database::pdo()->prepare(
            'INSERT INTO customers (
                organization_id,
                company_id,
                customer_type_key,
                name,
                email,
                email_normalized,
                phone,
                status_key,
                created_at,
                updated_at
            ) VALUES (
                :organization_id,
                :company_id,
                :customer_type_key,
                :name,
                :email,
                :email_normalized,
                :phone,
                :status_key,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'company_id' => $companyId,
            'customer_type_key' => $customerTypeKey,
            'name' => $this->requiredString($data['name'] ?? null, 'name'),
            'email' => $email,
            'email_normalized' => $email === null ? null : $this->normalizeEmail($email),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'status_key' => $this->normalizeStatus((string) ($data['status_key'] ?? 'active')),
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Update editable customer administration fields inside organization scope.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): Customer
    {
        $current = $this->findById($id);
        $currentData = $current->toArray();
        $organizationId = (int) ($currentData['organization_id'] ?? 0);
        $email = $this->nullableString($data['email'] ?? null);

        $statement = Database::pdo()->prepare(
            'UPDATE customers
             SET company_id = :company_id,
                customer_type_key = :customer_type_key,
                name = :name,
                email = :email,
                email_normalized = :email_normalized,
                phone = :phone,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND organization_id = :organization_id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'organization_id' => $organizationId,
            'company_id' => $this->nullableInt($data['company_id'] ?? null),
            'customer_type_key' => $this->normalizeType((string) ($data['customer_type_key'] ?? 'private')),
            'name' => $this->requiredString($data['name'] ?? null, 'name'),
            'email' => $email,
            'email_normalized' => $email === null ? null : $this->normalizeEmail($email),
            'phone' => $this->nullableString($data['phone'] ?? null),
        ]);

        return $this->findByIdForOrganization($id, $organizationId) ?? throw new ModelException('Customer not found.');
    }

    /**
     * Update only the customer lifecycle status.
     */
    public function updateStatus(int|string $id, string $statusKey): Customer
    {
        $current = $this->findById($id);
        $currentData = $current->toArray();
        $organizationId = (int) ($currentData['organization_id'] ?? 0);

        $statement = Database::pdo()->prepare(
            'UPDATE customers
             SET status_key = :status_key,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND organization_id = :organization_id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'organization_id' => $organizationId,
            'status_key' => $this->normalizeStatus($statusKey),
        ]);

        return $this->findByIdForOrganization($id, $organizationId) ?? throw new ModelException('Customer not found.');
    }

    /**
     * Soft delete a customer record without removing historical bookings.
     */
    public function delete(int|string $id): bool
    {
        $current = $this->findById($id);
        $currentData = $current->toArray();

        $statement = Database::pdo()->prepare(
            'UPDATE customers
             SET deleted_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND organization_id = :organization_id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'organization_id' => (int) ($currentData['organization_id'] ?? 0),
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Return booking history linked to a customer without mutating snapshots.
     *
     * @return list<array<string, mixed>>
     */
    public function findBookingHistory(int $customerId, int $organizationId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT bookings.public_id,
                bookings.status_key,
                bookings.start_date,
                bookings.end_date,
                bookings.currency,
                bookings.subtotal_amount,
                bookings.created_at,
                booking_customer_snapshots.customer_name,
                booking_customer_snapshots.customer_email,
                booking_customer_snapshots.customer_phone,
                booking_customer_snapshots.company_name,
                booking_history_items.rental_item_names
             FROM bookings
             LEFT JOIN booking_customer_snapshots
                ON booking_customer_snapshots.booking_id = bookings.id
             LEFT JOIN (
                SELECT booking_items.booking_id,
                    GROUP_CONCAT(rental_items.name ORDER BY booking_items.id ASC SEPARATOR \', \') AS rental_item_names
                FROM booking_items
                INNER JOIN rental_items
                    ON rental_items.id = booking_items.rental_item_id
                GROUP BY booking_items.booking_id
             ) AS booking_history_items
                ON booking_history_items.booking_id = bookings.id
             WHERE bookings.customer_id = :customer_id
                AND bookings.organization_id = :organization_id
                AND bookings.deleted_at IS NULL
             ORDER BY bookings.created_at DESC, bookings.id DESC'
        );
        $statement->execute([
            'customer_id' => $customerId,
            'organization_id' => $organizationId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check company scope before linking it to a customer.
     */
    public function companyBelongsToOrganization(int $companyId, int $organizationId): bool
    {
        $statement = Database::pdo()->prepare(
            'SELECT COUNT(*)
             FROM companies
             WHERE id = :company_id
                AND organization_id = :organization_id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'company_id' => $companyId,
            'organization_id' => $organizationId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<Customer>
     */
    private function collectionFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): Customer => new Customer($row),
            $rows
        ));
    }

    private function adminSelectSql(): string
    {
        return 'SELECT customers.*,
                organizations.name AS organization_name,
                companies.name AS company_name,
                customer_booking_counts.booking_count
             FROM customers
             INNER JOIN organizations
                ON organizations.id = customers.organization_id
             LEFT JOIN companies
                ON companies.id = customers.company_id
             LEFT JOIN (
                SELECT customer_id,
                    COUNT(*) AS booking_count
                FROM bookings
                WHERE deleted_at IS NULL
                    AND customer_id IS NOT NULL
                GROUP BY customer_id
             ) AS customer_booking_counts
                ON customer_booking_counts.customer_id = customers.id';
    }

    private function nullableStatus(?string $statusKey): ?string
    {
        if ($statusKey === null || trim($statusKey) === '') {
            return null;
        }

        return $this->normalizeStatus($statusKey);
    }

    private function normalizeStatus(string $statusKey): string
    {
        $normalized = strtolower(trim($statusKey));

        if (!in_array($normalized, self::STATUS_KEYS, true)) {
            throw new ModelException('Customer status is not supported.');
        }

        return $normalized;
    }

    private function normalizeType(string $customerTypeKey): string
    {
        $normalized = strtolower(trim($customerTypeKey));

        if (!in_array($normalized, self::CUSTOMER_TYPE_KEYS, true)) {
            throw new ModelException('Customer type is not supported.');
        }

        return $normalized;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function nullableSearch(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }

        $normalized = trim($query);

        return $normalized === '' ? null : substr($normalized, 0, 120);
    }

    private function requiredString(mixed $value, string $field): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            throw new ModelException($field . ' is required.');
        }

        return $text;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new ModelException($field . ' is required.');
        }

        return (int) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
