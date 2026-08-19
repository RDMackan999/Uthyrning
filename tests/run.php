<?php

declare(strict_types=1);

use App\Core\Collection;
use App\Core\BookingException;
use App\Core\Config;
use App\Core\Database;
use App\Core\MigrationRunner;
use App\Core\ModelException;
use App\Core\NotificationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\SeederRunner;
use App\Core\View;
use App\Controllers\AdminNotificationController;
use App\Http\BookingRequestFormRequest;
use App\Http\ItemRateFormRequest;
use App\Http\RentalItemFormRequest;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Category;
use App\Models\ItemAvailabilityBlock;
use App\Models\ItemRate;
use App\Models\Notification;
use App\Models\RentalItem;
use App\Repositories\BookingItemRepository;
use App\Repositories\BookingRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ItemAvailabilityBlockRepository;
use App\Repositories\ItemRateRepository;
use App\Repositories\NotificationAttemptRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RentalItemRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\BookingAvailabilityService;
use App\Services\AvailabilityCalendarService;
use App\Services\AuditService;
use App\Services\BookingPricingService;
use App\Services\BookingService;
use App\Services\BookingStatusService;
use App\Services\Email\DevelopmentEmailTransport;
use App\Services\Email\EmailMessage;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use App\Services\NotificationTemplateService;
use App\Services\RentalItemPublicationService;
use App\Services\SessionService;

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (is_file($autoloadPath)) {
    require $autoloadPath;
} else {
    spl_autoload_register(static function (string $class) use ($basePath): void {
        $prefix = 'App\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (is_file($path)) {
            require $path;
        }
    });
}

Config::load($basePath);
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Stockholm'));

if (ob_get_level() === 0) {
    ob_start();
}

final class TestRunner
{
    private int $passed = 0;

    private int $failed = 0;

    /**
     * @param callable(): void $test
     */
    public function test(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo '[PASS] ' . $name . PHP_EOL;
        } catch (Throwable $exception) {
            $this->failed++;
            echo '[FAIL] ' . $name . ': ' . $exception->getMessage() . PHP_EOL;
        }
    }

    public function finish(): int
    {
        echo PHP_EOL . 'Passed: ' . $this->passed . PHP_EOL;
        echo 'Failed: ' . $this->failed . PHP_EOL;

        return $this->failed === 0 ? 0 : 1;
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected: ' . var_export($expected, true)
            . ' Actual: ' . var_export($actual, true));
    }
}

function assertNotNull(mixed $value, string $message): void
{
    assertTrue($value !== null, $message);
}

function assertThrows(callable $callback, string $exceptionClass, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        assertTrue($exception instanceof $exceptionClass, $message);

        return;
    }

    throw new RuntimeException($message);
}

function pdo(): PDO
{
    return Database::pdo();
}

function tableExists(string $table): bool
{
    $statement = pdo()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table'
    );
    $statement->execute(['table' => $table]);

    return (int) $statement->fetchColumn() === 1;
}

/**
 * @return list<string>
 */
function columnsFor(string $table): array
{
    $statement = pdo()->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
         ORDER BY ORDINAL_POSITION ASC'
    );
    $statement->execute(['table' => $table]);

    /** @var list<string> $columns */
    $columns = $statement->fetchAll(PDO::FETCH_COLUMN);

    return $columns;
}

function indexExists(string $table, string $index): bool
{
    $statement = pdo()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
            AND INDEX_NAME = :index_name'
    );
    $statement->execute([
        'table' => $table,
        'index_name' => $index,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function foreignKeyExists(string $table, string $referencedTable): bool
{
    $statement = pdo()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
            AND REFERENCED_TABLE_NAME = :referenced_table'
    );
    $statement->execute([
        'table' => $table,
        'referenced_table' => $referencedTable,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function columnDataType(string $table, string $column): ?string
{
    $statement = pdo()->prepare(
        'SELECT DATA_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
            AND COLUMN_NAME = :column
         LIMIT 1'
    );
    $statement->execute([
        'table' => $table,
        'column' => $column,
    ]);

    $type = $statement->fetchColumn();

    return $type === false ? null : (string) $type;
}

function countRows(string $table): int
{
    return (int) pdo()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

function createOrganization(string $name, string $slug): int
{
    $statement = pdo()->prepare(
        'INSERT INTO organizations (name, slug, status_key, created_at, updated_at)
         VALUES (:name, :slug, :status_key, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $statement->execute([
        'name' => $name,
        'slug' => $slug,
        'status_key' => 'active',
    ]);

    return (int) pdo()->lastInsertId();
}

function createCustomer(int $organizationId, string $name, string $email): int
{
    $statement = pdo()->prepare(
        'INSERT INTO customers (
            organization_id,
            customer_type_key,
            name,
            email,
            email_normalized,
            status_key,
            created_at,
            updated_at
        ) VALUES (
            :organization_id,
            :customer_type_key,
            :name,
            :email,
            :email_normalized,
            :status_key,
            UTC_TIMESTAMP(),
            UTC_TIMESTAMP()
        )'
    );
    $statement->execute([
        'organization_id' => $organizationId,
        'customer_type_key' => 'private',
        'name' => $name,
        'email' => $email,
        'email_normalized' => strtolower(trim($email)),
        'status_key' => 'active',
    ]);

    return (int) pdo()->lastInsertId();
}

function requestWithValidCsrf(string $method, string $uri, array $post = []): Request
{
    $csrfId = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(32));
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR . 'csrf';

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    file_put_contents($directory . DIRECTORY_SEPARATOR . $csrfId . '.json', json_encode([
        'token_hash' => hash('sha256', $token),
        'expires_at' => time() + 1800,
    ], JSON_THROW_ON_ERROR), LOCK_EX);

    return new Request($method, $uri, [], array_merge($post, [
        'csrf_token' => $token,
    ]), [
        'uthyrning_csrf' => $csrfId,
    ]);
}

function requestWithValidCsrfAndSession(string $method, string $uri, string $sessionToken, array $post = []): Request
{
    $csrfId = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(32));
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR . 'csrf';

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    file_put_contents($directory . DIRECTORY_SEPARATOR . $csrfId . '.json', json_encode([
        'token_hash' => hash('sha256', $token),
        'expires_at' => time() + 1800,
    ], JSON_THROW_ON_ERROR), LOCK_EX);

    return new Request($method, $uri, [], array_merge($post, [
        'csrf_token' => $token,
    ]), [
        'uthyrning_csrf' => $csrfId,
        'uthyrning_session' => $sessionToken,
    ], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'Uthyrning test runner',
    ]);
}

/**
 * @return array{user_id: int, token: string}
 */
function createAuthenticatedTestUser(bool $isSystemAdmin): array
{
    $suffix = bin2hex(random_bytes(4));
    $user = (new UserRepository())->createLocalUser(
        'test-' . $suffix . '@example.com',
        password_hash('temporary-test-password', PASSWORD_DEFAULT),
        'Test',
        $isSystemAdmin ? 'Admin' : 'User'
    );
    $userId = (int) ($user->toArray()['id'] ?? 0);

    if ($isSystemAdmin) {
        $role = (new RoleRepository())->findSystemAdminRole();
        assertNotNull($role, 'System admin role should exist.');
        (new RoleRepository())->assignToUser($userId, (int) ($role->toArray()['id'] ?? 0));
    }

    $session = (new SessionService())->createSession($userId, '127.0.0.1', 'Uthyrning test runner');

    return [
        'user_id' => $userId,
        'token' => $session['token'],
    ];
}

function collectionContainsSlug(Collection $collection, string $slug): bool
{
    foreach ($collection as $category) {
        if (!$category instanceof Category) {
            continue;
        }

        if (($category->toArray()['slug'] ?? null) === $slug) {
            return true;
        }
    }

    return false;
}

function collectionOrganizationIds(Collection $collection): array
{
    $ids = [];

    foreach ($collection as $category) {
        if ($category instanceof Category) {
            $ids[] = $category->toArray()['organization_id'] ?? null;
        }
    }

    return $ids;
}

function orderedSlugs(Collection $collection): array
{
    $slugs = [];

    foreach ($collection as $category) {
        if ($category instanceof Category) {
            $slugs[] = $category->toArray()['slug'] ?? '';
        }
    }

    return $slugs;
}

function collectionContainsRentalItemSlug(Collection $collection, string $slug): bool
{
    foreach ($collection as $item) {
        if (!$item instanceof RentalItem) {
            continue;
        }

        if (($item->toArray()['slug'] ?? null) === $slug) {
            return true;
        }
    }

    return false;
}

function collectionContainsRateType(Collection $collection, string $rateType): bool
{
    foreach ($collection as $rate) {
        if (!$rate instanceof ItemRate) {
            continue;
        }

        if (($rate->toArray()['rate_type'] ?? null) === $rateType) {
            return true;
        }
    }

    return false;
}

function createBookableRentalItem(
    int $organizationId,
    int $categoryId,
    string $slug,
    string $name,
    RentalItemRepository $rentalItemRepository,
    ItemRateRepository $itemRateRepository
): RentalItem {
    $item = $rentalItemRepository->create([
        'organization_id' => $organizationId,
        'primary_category_id' => $categoryId,
        'slug' => $slug,
        'name' => $name,
        'publication_status_key' => 'published',
        'is_active' => true,
        'is_rentable' => true,
        'deposit_amount' => '250.00',
    ]);

    $itemRateRepository->create([
        'organization_id' => $organizationId,
        'rental_item_id' => (int) $item->toArray()['id'],
        'rate_type' => 'daily',
        'amount' => '250.00',
        'currency' => 'SEK',
        'is_active' => true,
    ]);

    return $item;
}

function setOrganizationEmail(int $organizationId, string $email): void
{
    $statement = pdo()->prepare(
        'UPDATE organizations
         SET email = :email,
            updated_at = UTC_TIMESTAMP()
         WHERE id = :organization_id'
    );
    $statement->execute([
        'organization_id' => $organizationId,
        'email' => $email,
    ]);
}

function notificationForBooking(int $bookingId, string $eventKey, string $recipientType): Notification
{
    $statement = pdo()->prepare(
        'SELECT * FROM notifications
         WHERE booking_id = :booking_id
            AND event_key = :event_key
            AND recipient_type = :recipient_type
         ORDER BY id ASC
         LIMIT 1'
    );
    $statement->execute([
        'booking_id' => $bookingId,
        'event_key' => $eventKey,
        'recipient_type' => $recipientType,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        throw new RuntimeException('Notification not found.');
    }

    return new Notification($row);
}

function notificationCountForBooking(int $bookingId, string $eventKey, string $recipientType): int
{
    $statement = pdo()->prepare(
        'SELECT COUNT(*)
         FROM notifications
         WHERE booking_id = :booking_id
            AND event_key = :event_key
            AND recipient_type = :recipient_type'
    );
    $statement->execute([
        'booking_id' => $bookingId,
        'event_key' => $eventKey,
        'recipient_type' => $recipientType,
    ]);

    return (int) $statement->fetchColumn();
}

function auditCount(string $eventName, string $subjectType): int
{
    $statement = pdo()->prepare(
        'SELECT COUNT(*)
         FROM audit_logs
         WHERE event_name = :event_name
            AND subject_type = :subject_type'
    );
    $statement->execute([
        'event_name' => $eventName,
        'subject_type' => $subjectType,
    ]);

    return (int) $statement->fetchColumn();
}

$runner = new TestRunner();
$migrationRunner = new MigrationRunner($basePath);
$seederRunner = new SeederRunner($basePath);
$repository = new CategoryRepository();
$rentalItemRepository = new RentalItemRepository();
$itemRateRepository = new ItemRateRepository();
$availabilityBlockRepository = new ItemAvailabilityBlockRepository();
$bookingRepository = new BookingRepository();
$bookingItemRepository = new BookingItemRepository();
$bookingAvailabilityService = new BookingAvailabilityService();
$availabilityCalendarService = new AvailabilityCalendarService();
$bookingPricingService = new BookingPricingService();
$bookingService = new BookingService();
$bookingStatusService = new BookingStatusService();

$runner->test('migrations create category tables', static function () use ($migrationRunner): void {
    $migrationRunner->run();

    assertTrue(tableExists('item_categories'), 'item_categories table should exist.');
    assertTrue(tableExists('item_category_relations'), 'item_category_relations table should exist.');
});

$runner->test('migrations create rental item foundation tables', static function () use ($migrationRunner): void {
    $migrationRunner->run();

    assertTrue(tableExists('rental_items'), 'rental_items table should exist.');
    assertTrue(tableExists('item_rates'), 'item_rates table should exist.');
});

$runner->test('migrations create booking foundation tables', static function () use ($migrationRunner): void {
    $migrationRunner->run();

    foreach ([
        'booking_statuses',
        'bookings',
        'booking_items',
        'booking_customer_snapshots',
        'booking_price_snapshots',
        'booking_status_history',
        'booking_notes',
    ] as $table) {
        assertTrue(tableExists($table), $table . ' table should exist.');
    }
});

$runner->test('migrations create manual availability block foundation table', static function () use ($migrationRunner): void {
    $migrationRunner->run();

    assertTrue(tableExists('blocked_periods'), 'blocked_periods table should exist.');

    $columns = columnsFor('blocked_periods');
    foreach ([
        'id',
        'organization_id',
        'rental_item_id',
        'start_date',
        'end_date',
        'reason_code',
        'internal_note',
        'created_by_user_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ] as $column) {
        assertTrue(in_array($column, $columns, true), $column . ' should exist on blocked_periods.');
    }

    assertSame('date', columnDataType('blocked_periods', 'start_date'), 'blocked_periods.start_date should be DATE.');
    assertSame('date', columnDataType('blocked_periods', 'end_date'), 'blocked_periods.end_date should be DATE.');
    assertTrue(indexExists('blocked_periods', 'idx_blocked_periods_item_dates'), 'blocked periods item date index missing.');
    assertTrue(foreignKeyExists('blocked_periods', 'organizations'), 'blocked_periods should reference organizations.');
    assertTrue(foreignKeyExists('blocked_periods', 'rental_items'), 'blocked_periods should reference rental_items.');
    assertTrue(foreignKeyExists('blocked_periods', 'users'), 'blocked_periods should reference users.');
});

$runner->test('migrations create notification foundation tables', static function () use ($migrationRunner): void {
    $migrationRunner->run();

    assertTrue(tableExists('notifications'), 'notifications table should exist.');
    assertTrue(tableExists('notification_attempts'), 'notification_attempts table should exist.');

    foreach ([
        'id',
        'public_id',
        'organization_id',
        'booking_id',
        'event_key',
        'channel_key',
        'recipient_type',
        'recipient_email',
        'recipient_email_normalized',
        'template_key',
        'subject',
        'status_key',
        'idempotency_key',
        'attempts_count',
        'max_attempts',
        'last_error_code',
        'last_error_summary',
        'scheduled_at',
        'sent_at',
        'failed_at',
        'created_at',
        'updated_at',
    ] as $column) {
        assertTrue(in_array($column, columnsFor('notifications'), true), $column . ' should exist on notifications.');
    }

    foreach ([
        'id',
        'notification_id',
        'attempt_number',
        'transport_key',
        'status_key',
        'error_code',
        'error_summary',
        'attempted_at',
        'created_at',
    ] as $column) {
        assertTrue(in_array($column, columnsFor('notification_attempts'), true), $column . ' should exist on notification_attempts.');
    }

    assertTrue(indexExists('notifications', 'uniq_notifications_idempotency_key'), 'Notification idempotency index missing.');
    assertTrue(indexExists('notifications', 'idx_notifications_booking_event'), 'Notification booking event index missing.');
    assertTrue(indexExists('notifications', 'idx_notifications_status_scheduled'), 'Notification status scheduled index missing.');
    assertTrue(indexExists('notification_attempts', 'uniq_notification_attempts_notification_attempt'), 'Attempt unique index missing.');
    assertTrue(foreignKeyExists('notifications', 'organizations'), 'notifications should reference organizations.');
    assertTrue(foreignKeyExists('notifications', 'bookings'), 'notifications should reference bookings.');
    assertTrue(foreignKeyExists('notification_attempts', 'notifications'), 'notification_attempts should reference notifications.');
    assertFalse(columnDataType('notifications', 'event_key') === 'enum', 'Notification events should not use ENUM.');
    assertFalse(columnDataType('notifications', 'status_key') === 'enum', 'Notification statuses should not use ENUM.');
    assertFalse(columnDataType('notification_attempts', 'status_key') === 'enum', 'Attempt statuses should not use ENUM.');
});

$runner->test('booking_created creates idempotent customer and admin notifications', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingRepository,
    $bookingItemRepository,
    $bookingAvailabilityService,
    $bookingPricingService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Notification Org ' . $suffix, 'notification-org-' . $suffix);
        setOrganizationEmail($organizationId, 'notify-' . $suffix . '@example.com');
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for notification tests.');
        $item = createBookableRentalItem(
            $organizationId,
            (int) $globalCategory->toArray()['id'],
            'notification-item-' . $suffix,
            'Notification Item ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );

        $transport = new DevelopmentEmailTransport();
        $notificationRepository = new NotificationRepository();
        $notificationAttemptRepository = new NotificationAttemptRepository();
        $templateService = new NotificationTemplateService();
        $notificationService = new NotificationService(
            $notificationRepository,
            new NotificationDispatcher(
                $notificationRepository,
                $notificationAttemptRepository,
                $templateService,
                new AuditService(),
                $transport
            ),
            $templateService,
            new AuditService()
        );
        $service = new BookingService(
            $bookingRepository,
            $bookingItemRepository,
            $rentalItemRepository,
            $bookingAvailabilityService,
            $bookingPricingService,
            new AuditService(),
            $notificationService
        );

        $booking = $service->createRequest([
            'rental_item_id' => (int) $item->toArray()['id'],
            'start_date' => '2031-01-10',
            'end_date' => '2031-01-11',
            'customer_name' => '<script>alert(1)</script>',
            'customer_email' => 'Snapshot.Customer+' . $suffix . '@Example.COM',
            'customer_phone' => '070-100 10 10',
            'internal_note' => 'Secret internal note should not be mailed.',
        ]);
        $bookingId = (int) $booking->toArray()['id'];

        assertSame(1, notificationCountForBooking($bookingId, 'booking_created', 'customer'), 'Customer notification should be created once.');
        assertSame(1, notificationCountForBooking($bookingId, 'booking_created', 'admin'), 'Admin notification should be created once.');

        $customerNotification = notificationForBooking($bookingId, 'booking_created', 'customer');
        $customerData = $customerNotification->toArray();
        assertSame('snapshot.customer+' . $suffix . '@example.com', $customerData['recipient_email_normalized'] ?? null, 'Customer recipient should come from booking snapshot.');
        assertSame('sent', $customerData['status_key'] ?? null, 'Customer notification should be sent by development transport.');
        assertSame(1, (int) ($customerData['attempts_count'] ?? 0), 'Customer notification should have one attempt.');

        $adminNotification = notificationForBooking($bookingId, 'booking_created', 'admin');
        assertSame('notify-' . $suffix . '@example.com', $adminNotification->toArray()['recipient_email_normalized'] ?? null, 'Admin recipient should come from organization email.');
        assertSame(2, count($transport->capturedMessages()), 'Development transport should capture two messages.');

        $customerBody = $transport->capturedMessages()[0]->htmlBody;
        assertTrue(str_contains($customerBody, (string) $booking->toArray()['public_id']), 'Customer email should include public booking reference.');
        assertTrue(str_contains($customerBody, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Customer email should escape HTML data.');
        assertFalse(str_contains($customerBody, '<script>alert(1)</script>'), 'Customer email should not render raw HTML data.');
        assertFalse(str_contains($customerBody, 'Secret internal note'), 'Customer email should not include internal notes.');

        $notificationService->notifyBookingCreated($booking);
        assertSame(1, notificationCountForBooking($bookingId, 'booking_created', 'customer'), 'Customer idempotency should prevent duplicates.');
        assertSame(1, notificationCountForBooking($bookingId, 'booking_created', 'admin'), 'Admin idempotency should prevent duplicates.');
        assertSame(2, count($transport->capturedMessages()), 'Sent notifications should not be sent again when idempotency finds sent records.');
        assertTrue(auditCount('notification_created', 'notification') >= 2, 'Notification creation should be audited.');
        assertTrue(auditCount('notification_sent', 'notification') >= 2, 'Notification sent should be audited.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('booking status transitions create customer notifications only for V1 email events', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingRepository,
    $bookingItemRepository,
    $bookingAvailabilityService,
    $bookingPricingService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Status Notification Org ' . $suffix, 'status-notification-org-' . $suffix);
        setOrganizationEmail($organizationId, 'status-notify-' . $suffix . '@example.com');
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for status notification tests.');
        $item = createBookableRentalItem(
            $organizationId,
            (int) $globalCategory->toArray()['id'],
            'status-notification-item-' . $suffix,
            'Status Notification Item ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );
        $itemId = (int) $item->toArray()['id'];

        $transport = new DevelopmentEmailTransport();
        $notificationRepository = new NotificationRepository();
        $notificationAttemptRepository = new NotificationAttemptRepository();
        $templateService = new NotificationTemplateService();
        $notificationService = new NotificationService(
            $notificationRepository,
            new NotificationDispatcher(
                $notificationRepository,
                $notificationAttemptRepository,
                $templateService,
                new AuditService(),
                $transport
            ),
            $templateService,
            new AuditService()
        );
        $service = new BookingService(
            $bookingRepository,
            $bookingItemRepository,
            $rentalItemRepository,
            $bookingAvailabilityService,
            $bookingPricingService,
            new AuditService(),
            $notificationService
        );
        $statusService = new BookingStatusService($bookingRepository, new AuditService(), $notificationService);

        $approvedBooking = $service->createRequest([
            'rental_item_id' => $itemId,
            'start_date' => '2031-02-10',
            'end_date' => '2031-02-10',
            'customer_name' => 'Approved Guest',
            'customer_email' => 'approved-' . $suffix . '@example.com',
            'customer_phone' => '070-200 20 10',
        ]);
        $approved = $statusService->transition($organizationId, (int) $approvedBooking->toArray()['id'], 'approved');
        assertSame('approved', $approved->toArray()['status_key'] ?? null, 'Booking should be approved.');
        assertSame(1, notificationCountForBooking((int) $approvedBooking->toArray()['id'], 'booking_approved', 'customer'), 'Approved notification should be created.');

        $rejectedBooking = $service->createRequest([
            'rental_item_id' => $itemId,
            'start_date' => '2031-02-11',
            'end_date' => '2031-02-11',
            'customer_name' => 'Rejected Guest',
            'customer_email' => 'rejected-' . $suffix . '@example.com',
            'customer_phone' => '070-200 20 11',
        ]);
        $statusService->transition($organizationId, (int) $rejectedBooking->toArray()['id'], 'rejected');
        assertSame(1, notificationCountForBooking((int) $rejectedBooking->toArray()['id'], 'booking_rejected', 'customer'), 'Rejected notification should be created.');

        $cancelledBooking = $service->createRequest([
            'rental_item_id' => $itemId,
            'start_date' => '2031-02-12',
            'end_date' => '2031-02-12',
            'customer_name' => 'Cancelled Guest',
            'customer_email' => 'cancelled-' . $suffix . '@example.com',
            'customer_phone' => '070-200 20 12',
        ]);
        $statusService->transition($organizationId, (int) $cancelledBooking->toArray()['id'], 'cancelled');
        assertSame(1, notificationCountForBooking((int) $cancelledBooking->toArray()['id'], 'booking_cancelled', 'customer'), 'Cancelled notification should be created.');

        $completedBooking = $service->createRequest([
            'rental_item_id' => $itemId,
            'start_date' => '2031-02-13',
            'end_date' => '2031-02-13',
            'customer_name' => 'Completed Guest',
            'customer_email' => 'completed-' . $suffix . '@example.com',
            'customer_phone' => '070-200 20 13',
        ]);
        $statusService->transition($organizationId, (int) $completedBooking->toArray()['id'], 'approved');
        $statusService->transition($organizationId, (int) $completedBooking->toArray()['id'], 'active');
        $statusService->transition($organizationId, (int) $completedBooking->toArray()['id'], 'completed');
        assertSame(0, notificationCountForBooking((int) $completedBooking->toArray()['id'], 'booking_started', 'customer'), 'Started event should not create V1 email notification.');
        assertSame(0, notificationCountForBooking((int) $completedBooking->toArray()['id'], 'booking_completed', 'customer'), 'Completed event should not create V1 email notification.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('notification failure retries do not roll back booking or status changes', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingRepository,
    $bookingItemRepository,
    $bookingAvailabilityService,
    $bookingPricingService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Failure Notification Org ' . $suffix, 'failure-notification-org-' . $suffix);
        setOrganizationEmail($organizationId, 'failure-notify-' . $suffix . '@example.com');
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for failure notification tests.');
        $item = createBookableRentalItem(
            $organizationId,
            (int) $globalCategory->toArray()['id'],
            'failure-notification-item-' . $suffix,
            'Failure Notification Item ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );

        $transport = new DevelopmentEmailTransport(true);
        $notificationRepository = new NotificationRepository();
        $notificationAttemptRepository = new NotificationAttemptRepository();
        $templateService = new NotificationTemplateService();
        $dispatcher = new NotificationDispatcher(
            $notificationRepository,
            $notificationAttemptRepository,
            $templateService,
            new AuditService(),
            $transport
        );
        $notificationService = new NotificationService(
            $notificationRepository,
            $dispatcher,
            $templateService,
            new AuditService()
        );
        $service = new BookingService(
            $bookingRepository,
            $bookingItemRepository,
            $rentalItemRepository,
            $bookingAvailabilityService,
            $bookingPricingService,
            new AuditService(),
            $notificationService
        );
        $statusService = new BookingStatusService($bookingRepository, new AuditService(), $notificationService);

        $booking = $service->createRequest([
            'rental_item_id' => (int) $item->toArray()['id'],
            'start_date' => '2031-03-10',
            'end_date' => '2031-03-10',
            'customer_name' => 'Failure Guest',
            'customer_email' => 'failure-' . $suffix . '@example.com',
            'customer_phone' => '070-300 30 10',
        ]);
        $bookingData = $booking->toArray();
        $bookingId = (int) $bookingData['id'];
        assertSame('request', $bookingData['status_key'] ?? null, 'Booking should still be created when email fails.');

        $customerNotification = notificationForBooking($bookingId, 'booking_created', 'customer');
        assertSame('pending', $customerNotification->toArray()['status_key'] ?? null, 'Failed notification should stay pending while retries remain.');
        assertSame(1, (int) ($customerNotification->toArray()['attempts_count'] ?? 0), 'First failure should record one attempt.');
        assertSame(1, $notificationAttemptRepository->countForNotification((int) $customerNotification->toArray()['id']), 'First failure should append one attempt.');

        $notificationService->notifyBookingCreated($booking);
        $notificationService->notifyBookingCreated($booking);
        $notificationService->notifyBookingCreated($booking);
        $customerNotification = notificationForBooking($bookingId, 'booking_created', 'customer');
        assertSame('failed', $customerNotification->toArray()['status_key'] ?? null, 'Notification should be failed after max attempts.');
        assertSame(3, (int) ($customerNotification->toArray()['attempts_count'] ?? 0), 'Max three attempts should be respected.');
        assertSame(3, $notificationAttemptRepository->countForNotification((int) $customerNotification->toArray()['id']), 'Retries should append attempts to the same notification.');
        assertSame(1, notificationCountForBooking($bookingId, 'booking_created', 'customer'), 'Retries should not create duplicate customer notifications.');

        $updated = $statusService->transition($organizationId, $bookingId, 'approved');
        assertSame('approved', $updated->toArray()['status_key'] ?? null, 'Status change should remain valid when email fails.');
        assertSame(1, notificationCountForBooking($bookingId, 'booking_approved', 'customer'), 'Approved failure should still create one logical notification.');
        assertTrue(auditCount('notification_failed', 'notification') >= 1, 'Notification failure should be audited.');
        assertSame(0, count($transport->capturedMessages()), 'Development failure transport should not send real or captured success email.');

        assertThrows(
            static fn (): mixed => (new DevelopmentEmailTransport())->send(new EmailMessage(
                "bad@example.com\r\nbcc:evil@example.com",
                'Unsafe recipient',
                '<p>Body</p>',
                'Body'
            )),
            NotificationException::class,
            'Invalid recipients should be rejected safely.'
        );

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('item_categories has expected columns only for Sprint 3B', static function (): void {
    $columns = columnsFor('item_categories');

    foreach ([
        'id',
        'organization_id',
        'organization_scope_key',
        'slug',
        'name',
        'description',
        'sort_order',
        'is_active',
        'created_at',
        'updated_at',
        'deleted_at',
    ] as $column) {
        assertTrue(in_array($column, $columns, true), $column . ' should exist.');
    }

    foreach (['parent_id', 'seo_title', 'seo_description', 'media_asset_id', 'icon_key'] as $futureColumn) {
        assertFalse(in_array($futureColumn, $columns, true), $futureColumn . ' should not exist yet.');
    }
});

$runner->test('item_category_relations has expected columns', static function (): void {
    $columns = columnsFor('item_category_relations');

    foreach ([
        'id',
        'rental_item_id',
        'item_category_id',
        'is_primary',
        'sort_order',
        'created_at',
        'updated_at',
    ] as $column) {
        assertTrue(in_array($column, $columns, true), $column . ' should exist.');
    }
});

$runner->test('category indexes and foreign keys match current foundation', static function (): void {
    assertTrue(indexExists('item_categories', 'uniq_item_categories_scope_slug'), 'Scope slug unique index missing.');
    assertTrue(indexExists('item_categories', 'idx_item_categories_organization_id'), 'Organization index missing.');
    assertTrue(indexExists('item_categories', 'idx_item_categories_slug'), 'Slug index missing.');
    assertTrue(indexExists('item_categories', 'idx_item_categories_scope_active_sort'), 'Scope active sort index missing.');
    assertTrue(indexExists('item_category_relations', 'uniq_item_category_relations_item_category'), 'Relation unique index missing.');
    assertTrue(indexExists('item_category_relations', 'idx_item_category_relations_rental_item_id'), 'Rental item index missing.');
    assertTrue(indexExists('item_category_relations', 'idx_item_category_relations_item_category_id'), 'Category relation index missing.');
    assertTrue(foreignKeyExists('item_categories', 'organizations'), 'item_categories should reference organizations.');
    assertTrue(foreignKeyExists('item_category_relations', 'item_categories'), 'item_category_relations should reference item_categories.');
    assertTrue(foreignKeyExists('item_category_relations', 'rental_items'), 'item_category_relations should reference rental_items after Sprint 4B.');
});

$runner->test('rental item schema supports Sprint 4B foundation', static function (): void {
    $rentalItemColumns = columnsFor('rental_items');
    $itemRateColumns = columnsFor('item_rates');

    foreach ([
        'id',
        'public_id',
        'organization_id',
        'owning_company_id',
        'primary_category_id',
        'slug',
        'name',
        'short_name',
        'description',
        'internal_note',
        'manufacturer',
        'model',
        'serial_number',
        'inventory_number',
        'status_key',
        'publication_status_key',
        'condition_grade_id',
        'is_active',
        'is_rentable',
        'vat_rate',
        'deposit_amount',
        'created_at',
        'updated_at',
        'deleted_at',
    ] as $column) {
        assertTrue(in_array($column, $rentalItemColumns, true), $column . ' should exist on rental_items.');
    }

    foreach (['qr_code_value', 'barcode_value', 'rfid_tag', 'gps_latitude', 'gps_longitude', 'seo_title'] as $futureColumn) {
        assertFalse(in_array($futureColumn, $rentalItemColumns, true), $futureColumn . ' should not exist yet.');
    }

    foreach ([
        'id',
        'rental_item_id',
        'rate_type',
        'amount',
        'currency',
        'is_active',
        'created_at',
        'updated_at',
        'deleted_at',
    ] as $column) {
        assertTrue(in_array($column, $itemRateColumns, true), $column . ' should exist on item_rates.');
    }

    assertTrue(indexExists('rental_items', 'uniq_rental_items_public_id'), 'public_id unique index missing.');
    assertTrue(indexExists('rental_items', 'uniq_rental_items_organization_slug'), 'organization slug unique index missing.');
    assertTrue(indexExists('item_rates', 'idx_item_rates_rental_item_id'), 'item rate item index missing.');
    assertTrue(foreignKeyExists('rental_items', 'organizations'), 'rental_items should reference organizations.');
    assertTrue(foreignKeyExists('rental_items', 'companies'), 'rental_items should reference companies.');
    assertTrue(foreignKeyExists('rental_items', 'item_categories'), 'rental_items should reference item_categories.');
    assertTrue(foreignKeyExists('item_rates', 'rental_items'), 'item_rates should reference rental_items.');
});

$runner->test('booking schema supports Sprint 5B foundation', static function (): void {
    $bookingColumns = columnsFor('bookings');
    $bookingItemColumns = columnsFor('booking_items');

    foreach ([
        'id',
        'public_id',
        'organization_id',
        'customer_id',
        'company_id',
        'status_key',
        'start_date',
        'end_date',
        'customer_comment',
        'internal_note',
        'currency',
        'total_units',
        'subtotal_amount',
        'deposit_amount',
        'created_at',
        'updated_at',
        'deleted_at',
    ] as $column) {
        assertTrue(in_array($column, $bookingColumns, true), $column . ' should exist on bookings.');
    }

    foreach ([
        'id',
        'booking_id',
        'rental_item_id',
        'start_date',
        'end_date',
        'rate_type',
        'unit_price',
        'currency',
        'quantity',
        'number_of_units',
        'subtotal_amount',
        'deposit_amount',
        'created_at',
        'updated_at',
    ] as $column) {
        assertTrue(in_array($column, $bookingItemColumns, true), $column . ' should exist on booking_items.');
    }

    assertSame('varchar', columnDataType('bookings', 'status_key'), 'bookings.status_key should not use ENUM.');
    assertSame('varchar', columnDataType('booking_statuses', 'status_key'), 'booking_statuses.status_key should not use ENUM.');

    assertTrue(indexExists('bookings', 'uniq_bookings_public_id'), 'Booking public id unique index missing.');
    assertTrue(indexExists('bookings', 'idx_bookings_organization_status_dates'), 'Booking organization/status/date index missing.');
    assertTrue(indexExists('booking_items', 'idx_booking_items_item_dates'), 'Booking item date index missing.');
    assertTrue(indexExists('booking_customer_snapshots', 'idx_booking_customer_snapshots_email_normalized'), 'Customer snapshot email index missing.');
    assertTrue(foreignKeyExists('bookings', 'organizations'), 'bookings should reference organizations.');
    assertTrue(foreignKeyExists('bookings', 'customers'), 'bookings should reference customers.');
    assertTrue(foreignKeyExists('bookings', 'companies'), 'bookings should reference companies.');
    assertTrue(foreignKeyExists('bookings', 'booking_statuses'), 'bookings should reference booking_statuses.');
    assertTrue(foreignKeyExists('booking_items', 'bookings'), 'booking_items should reference bookings.');
    assertTrue(foreignKeyExists('booking_items', 'rental_items'), 'booking_items should reference rental_items.');
    assertTrue(foreignKeyExists('booking_status_history', 'bookings'), 'booking_status_history should reference bookings.');
    assertTrue(foreignKeyExists('booking_customer_snapshots', 'bookings'), 'booking_customer_snapshots should reference bookings.');
    assertTrue(foreignKeyExists('booking_price_snapshots', 'bookings'), 'booking_price_snapshots should reference bookings.');
});

$runner->test('booking status seed is idempotent and does not create bookings or customers', static function () use ($seederRunner): void {
    $bookingsBefore = countRows('bookings');
    $customersBefore = countRows('customers');

    $seederRunner->run();
    $seederRunner->run();

    $statement = pdo()->query(
        'SELECT status_key, is_blocking
         FROM booking_statuses
         WHERE deleted_at IS NULL
         ORDER BY sort_order ASC'
    );
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statusKeys = array_column($rows, 'status_key');

    assertSame(
        ['request', 'approved', 'rejected', 'cancelled', 'active', 'completed'],
        $statusKeys,
        'Booking status seed should match Sprint 5A keys.'
    );

    $blocking = [];
    foreach ($rows as $row) {
        if ((int) $row['is_blocking'] === 1) {
            $blocking[] = $row['status_key'];
        }
    }
    assertSame(['request', 'approved', 'active'], $blocking, 'Blocking status keys should match Sprint 5A.');

    $duplicates = (int) pdo()->query(
        'SELECT COUNT(*)
         FROM (
            SELECT status_key, COUNT(*) AS row_count
            FROM booking_statuses
            GROUP BY status_key
            HAVING row_count > 1
         ) duplicates'
    )->fetchColumn();

    assertSame(0, $duplicates, 'Booking status seed should not create duplicate status keys.');
    assertSame($bookingsBefore, countRows('bookings'), 'Booking status seed should not create bookings.');
    assertSame($customersBefore, countRows('customers'), 'Booking status seed should not create customers.');
});

$runner->test('Booking models map to booking foundation tables', static function (): void {
    assertSame('bookings', Booking::tableName(), 'Booking table name should match booking foundation table.');
    assertSame('booking_items', BookingItem::tableName(), 'BookingItem table name should match booking item table.');
});

$runner->test('seed creates six global categories and is idempotent', static function () use ($seederRunner): void {
    $usersBefore = countRows('users');

    $seederRunner->run();
    $seederRunner->run();

    $statement = pdo()->query(
        "SELECT slug, organization_id
         FROM item_categories
         WHERE organization_id IS NULL
            AND deleted_at IS NULL
         ORDER BY sort_order ASC"
    );
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $slugs = array_column($rows, 'slug');

    assertSame(['verktyg', 'maskiner', 'slap', 'tradgard', 'bygg', 'ovrigt'], $slugs, 'Seeded slugs should match.');

    foreach ($rows as $row) {
        assertSame(null, $row['organization_id'], 'Seeded categories should be global.');
    }

    $duplicates = (int) pdo()->query(
        'SELECT COUNT(*)
         FROM (
            SELECT organization_scope_key, slug, COUNT(*) AS row_count
            FROM item_categories
            GROUP BY organization_scope_key, slug
            HAVING row_count > 1
         ) duplicates'
    )->fetchColumn();

    assertSame(0, $duplicates, 'Seed should not create duplicate scope/slug rows.');
    assertSame($usersBefore, countRows('users'), 'Category seed should not create users or admins.');
});

$runner->test('Category model maps to item_categories', static function (): void {
    assertSame('item_categories', Category::tableName(), 'Category table name should match category foundation table.');
});

$runner->test('CategoryRepository uses prepared statements', static function () use ($basePath): void {
    $source = (string) file_get_contents($basePath . DIRECTORY_SEPARATOR . 'app'
        . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR . 'CategoryRepository.php');

    assertTrue(str_contains($source, '->prepare('), 'Repository should prepare SQL statements.');
    assertFalse(str_contains($source, '->query('), 'Repository should not use direct query calls.');
});

$runner->test('repository scope, CRUD, sorting and soft delete behavior', static function () use ($repository): void {
    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));

    $pdo->beginTransaction();

    try {
        $organizationOneId = createOrganization('Category Test One ' . $suffix, 'category-test-one-' . $suffix);
        $organizationTwoId = createOrganization('Category Test Two ' . $suffix, 'category-test-two-' . $suffix);

        $globalShared = $repository->create([
            'slug' => 'shared-' . $suffix,
            'name' => 'Shared Global',
            'sort_order' => 500,
        ]);
        $organizationOneShared = $repository->create([
            'organization_id' => $organizationOneId,
            'slug' => 'shared-' . $suffix,
            'name' => 'Shared One',
            'sort_order' => 510,
        ]);
        $organizationTwoShared = $repository->create([
            'organization_id' => $organizationTwoId,
            'slug' => 'shared-' . $suffix,
            'name' => 'Shared Two',
            'sort_order' => 520,
        ]);

        assertSame($globalShared->toArray()['id'], $repository->findBySlug('shared-' . $suffix)?->toArray()['id'] ?? null, 'Global slug lookup should use global scope only.');
        assertSame($organizationOneShared->toArray()['id'], $repository->findBySlug('shared-' . $suffix, $organizationOneId)?->toArray()['id'] ?? null, 'Organization lookup should use exact organization scope.');
        assertSame($organizationTwoShared->toArray()['id'], $repository->findBySlug('shared-' . $suffix, $organizationTwoId)?->toArray()['id'] ?? null, 'Second organization lookup should use exact organization scope.');

        $organizationThreeId = createOrganization('Category Test Three ' . $suffix, 'category-test-three-' . $suffix);
        assertSame(null, $repository->findBySlug('shared-' . $suffix, $organizationThreeId), 'Organization lookup should not fall back to global scope.');

        assertThrows(
            static fn () => $repository->create([
                'organization_id' => $organizationOneId,
                'slug' => 'shared-' . $suffix,
                'name' => 'Duplicate Shared One',
            ]),
            PDOException::class,
            'Duplicate slug in same organization scope should fail.'
        );

        $first = $repository->create([
            'organization_id' => $organizationOneId,
            'slug' => 'aaa-sort-' . $suffix,
            'name' => 'AAA Sort',
            'sort_order' => 100,
        ]);
        $second = $repository->create([
            'organization_id' => $organizationOneId,
            'slug' => 'bbb-sort-' . $suffix,
            'name' => 'BBB Sort',
            'sort_order' => 110,
        ]);
        $inactive = $repository->create([
            'organization_id' => $organizationOneId,
            'slug' => 'inactive-' . $suffix,
            'name' => 'Inactive',
            'is_active' => false,
        ]);

        $globalCategories = $repository->findGlobal();
        assertTrue(count($globalCategories) >= 6, 'Global categories should be available.');

        $allActive = $repository->findAllActive($organizationOneId);
        assertTrue(collectionContainsSlug($allActive, 'aaa-sort-' . $suffix), 'Active organization category should be visible.');
        assertFalse(collectionContainsSlug($allActive, 'inactive-' . $suffix), 'Inactive category should be hidden.');
        assertFalse(collectionContainsSlug($allActive, 'shared-' . $suffix) && in_array($organizationTwoId, collectionOrganizationIds($allActive), true), 'Other organization categories should not leak.');

        $slugs = orderedSlugs($repository->findForOrganization($organizationOneId));
        assertTrue(array_search('aaa-sort-' . $suffix, $slugs, true) < array_search('bbb-sort-' . $suffix, $slugs, true), 'Sort order should be deterministic.');

        $foundById = $repository->findById((int) $first->toArray()['id']);
        assertSame('aaa-sort-' . $suffix, $foundById->toArray()['slug'], 'findById should return the created category.');

        $updated = $repository->update((int) $second->toArray()['id'], [
            'slug' => 'bbb-updated-' . $suffix,
            'name' => 'BBB Updated',
            'description' => 'Updated description',
            'sort_order' => 120,
            'is_active' => true,
        ]);
        assertSame('bbb-updated-' . $suffix, $updated->toArray()['slug'], 'update should persist slug changes.');
        assertNotNull($repository->findBySlug('bbb-updated-' . $suffix, $organizationOneId), 'Updated slug should be findable.');

        assertSame(null, $repository->findBySlug('inactive-' . $suffix, $organizationOneId), 'findBySlug should exclude inactive categories.');
        assertTrue($repository->delete((int) $first->toArray()['id']), 'delete should soft delete a category.');
        assertThrows(
            static fn () => $repository->findById((int) $first->toArray()['id']),
            ModelException::class,
            'Soft-deleted category should not be found by findById.'
        );
        assertFalse(collectionContainsSlug($repository->findForOrganization($organizationOneId), 'aaa-sort-' . $suffix), 'Soft-deleted category should be hidden from active scoped results.');

        assertTrue($inactive instanceof Category, 'Inactive category object should have been created for filtering test.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('RentalItem model maps to rental_items', static function (): void {
    assertSame('rental_items', RentalItem::tableName(), 'RentalItem table name should match rental item foundation table.');
});

$runner->test('ItemRate model maps to item_rates', static function (): void {
    assertSame('item_rates', ItemRate::tableName(), 'ItemRate table name should match item rate foundation table.');
});

$runner->test('rental item and rate repositories enforce foundation scope rules', static function () use (
    $repository,
    $rentalItemRepository,
    $itemRateRepository
): void {
    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));

    $pdo->beginTransaction();

    try {
        $organizationOneId = createOrganization('Rental Item Test One ' . $suffix, 'rental-item-test-one-' . $suffix);
        $organizationTwoId = createOrganization('Rental Item Test Two ' . $suffix, 'rental-item-test-two-' . $suffix);

        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for rental item test.');
        $globalCategoryId = (int) $globalCategory->toArray()['id'];

        $organizationOneCategory = $repository->create([
            'organization_id' => $organizationOneId,
            'slug' => 'item-org-one-' . $suffix,
            'name' => 'Item Org One',
        ]);
        $organizationTwoCategory = $repository->create([
            'organization_id' => $organizationTwoId,
            'slug' => 'item-org-two-' . $suffix,
            'name' => 'Item Org Two',
        ]);

        $globalItem = $rentalItemRepository->create([
            'organization_id' => $organizationOneId,
            'primary_category_id' => $globalCategoryId,
            'slug' => 'shared-item-' . $suffix,
            'name' => 'Shared Item One',
            'description' => 'Created without daily price.',
            'vat_rate' => '25.00',
            'deposit_amount' => null,
        ]);
        $globalItemData = $globalItem->toArray();

        assertTrue(str_starts_with((string) $globalItemData['public_id'], 'itm_'), 'public_id should use item prefix.');
        assertFalse((string) $globalItemData['public_id'] === (string) $globalItemData['id'], 'public_id should not equal internal id.');
        assertSame(null, $globalItemData['deposit_amount'], 'deposit_amount should be nullable.');
        assertSame('draft', $globalItemData['publication_status_key'], 'New item should start as draft.');
        assertSame('0', (string) $globalItemData['is_rentable'], 'Draft item should not be rentable by default.');

        $organizationScopedItem = $rentalItemRepository->create([
            'organization_id' => $organizationOneId,
            'primary_category_id' => (int) $organizationOneCategory->toArray()['id'],
            'slug' => 'org-category-item-' . $suffix,
            'name' => 'Organization Category Item',
            'is_rentable' => true,
        ]);

        $otherOrganizationSameSlug = $rentalItemRepository->create([
            'organization_id' => $organizationTwoId,
            'primary_category_id' => $globalCategoryId,
            'slug' => 'shared-item-' . $suffix,
            'name' => 'Shared Item Two',
        ]);

        assertFalse(
            $globalItemData['public_id'] === $otherOrganizationSameSlug->toArray()['public_id'],
            'public_id should be unique across rental items.'
        );
        assertSame(
            $globalItemData['id'],
            $rentalItemRepository->findByPublicId((string) $globalItemData['public_id'])?->toArray()['id'] ?? null,
            'findByPublicId should find the created item.'
        );
        assertSame(
            $otherOrganizationSameSlug->toArray()['id'],
            $rentalItemRepository->findBySlug($organizationTwoId, 'shared-item-' . $suffix)?->toArray()['id'] ?? null,
            'Same slug should be available in another organization.'
        );

        assertThrows(
            static fn () => $rentalItemRepository->create([
                'organization_id' => $organizationOneId,
                'primary_category_id' => $globalCategoryId,
                'slug' => 'shared-item-' . $suffix,
                'name' => 'Duplicate Item One',
            ]),
            PDOException::class,
            'Duplicate rental item slug in same organization should fail.'
        );

        assertThrows(
            static fn () => $rentalItemRepository->create([
                'organization_id' => $organizationOneId,
                'primary_category_id' => (int) $organizationTwoCategory->toArray()['id'],
                'slug' => 'wrong-category-' . $suffix,
                'name' => 'Wrong Category Item',
            ]),
            ModelException::class,
            'Category from another organization should be denied.'
        );

        $updated = $rentalItemRepository->update((int) $globalItemData['id'], [
            'public_id' => 'itm_should_not_change',
            'name' => 'Updated Shared Item One',
            'slug' => 'updated-shared-item-' . $suffix,
            'deposit_amount' => '500.00',
            'is_rentable' => true,
        ]);
        $updatedData = $updated->toArray();

        assertSame($globalItemData['public_id'], $updatedData['public_id'], 'public_id should not change on update.');
        assertSame('updated-shared-item-' . $suffix, $updatedData['slug'], 'Slug should update only when explicitly supplied.');
        assertSame('500.00', $updatedData['deposit_amount'], 'Deposit should be updatable when supplied.');

        $relationStatement = $pdo->prepare(
            'INSERT INTO item_category_relations (rental_item_id, item_category_id, is_primary, sort_order, created_at, updated_at)
             VALUES (:rental_item_id, :item_category_id, 1, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $relationStatement->execute([
            'rental_item_id' => (int) $organizationScopedItem->toArray()['id'],
            'item_category_id' => (int) $organizationOneCategory->toArray()['id'],
        ]);

        $dailyRate = $itemRateRepository->create([
            'organization_id' => $organizationOneId,
            'rental_item_id' => (int) $organizationScopedItem->toArray()['id'],
            'rate_type' => 'daily',
            'amount' => '250.00',
            'currency' => 'sek',
        ]);
        $weekendRate = $itemRateRepository->create([
            'organization_id' => $organizationOneId,
            'rental_item_id' => (int) $organizationScopedItem->toArray()['id'],
            'rate_type' => 'weekend',
            'amount' => '600.00',
        ]);

        assertTrue(
            collectionContainsRateType(
                $itemRateRepository->findActiveForItem($organizationOneId, (int) $organizationScopedItem->toArray()['id']),
                'daily'
            ),
            'Active daily rate should be visible in item scope.'
        );
        assertSame(
            0,
            count($itemRateRepository->findForItem($organizationTwoId, (int) $organizationScopedItem->toArray()['id'])),
            'Item rates should not leak to another organization scope.'
        );
        assertThrows(
            static fn () => $itemRateRepository->create([
                'organization_id' => $organizationTwoId,
                'rental_item_id' => (int) $organizationScopedItem->toArray()['id'],
                'rate_type' => 'weekly',
                'amount' => '1000.00',
            ]),
            ModelException::class,
            'Creating a rate with the wrong organization should fail.'
        );

        $updatedRate = $itemRateRepository->update((int) $dailyRate->toArray()['id'], [
            'amount' => '275.00',
            'currency' => 'SEK',
        ], $organizationOneId);
        assertSame('275.00', $updatedRate->toArray()['amount'], 'Item rate amount should update.');
        assertTrue($weekendRate instanceof ItemRate, 'Weekend rate should be created for allowed Version 1 rate type.');

        assertTrue($itemRateRepository->delete((int) $dailyRate->toArray()['id'], $organizationOneId), 'Item rate should soft delete.');
        assertThrows(
            static fn () => $itemRateRepository->findById((int) $dailyRate->toArray()['id'], $organizationOneId),
            ModelException::class,
            'Soft-deleted item rate should not be found.'
        );

        assertTrue($rentalItemRepository->delete((int) $organizationScopedItem->toArray()['id']), 'Rental item should soft delete.');
        assertThrows(
            static fn () => $rentalItemRepository->findById((int) $organizationScopedItem->toArray()['id']),
            ModelException::class,
            'Soft-deleted rental item should not be found.'
        );
        assertFalse(
            collectionContainsRentalItemSlug(
                $rentalItemRepository->findForOrganization($organizationOneId),
                'org-category-item-' . $suffix
            ),
            'Soft-deleted rental item should be excluded from organization lists.'
        );

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('Router supports rental item admin public_id route parameters', static function (): void {
    $router = new Router();
    $router->get(
        '/admin/items/{public_id}/edit',
        static fn (Request $request): Response => Response::text((string) $request->route('public_id'))
    );

    $response = $router->dispatch(new Request('GET', '/admin/items/itm_test_public_id/edit'));

    assertSame(200, $response->statusCode(), 'Parameterized route should return OK.');
    assertSame('itm_test_public_id', $response->content(), 'Router should expose public_id route parameter.');
});

$runner->test('rental item admin list view renders item display fields', static function (): void {
    $html = (new View())->render('admin/items/index', [
        'items' => [[
            'name' => 'Admin List Item',
            'public_id' => 'itm_admin_list',
            'primary_category_name' => 'Verktyg',
            'organization_name' => 'Uthyrning Test',
            'is_active' => 1,
            'is_rentable' => 1,
        ]],
    ]);

    assertTrue(str_contains($html, 'Admin List Item'), 'Admin list should render item name.');
    assertTrue(str_contains($html, 'itm_admin_list'), 'Admin list should render public id.');
    assertTrue(str_contains($html, 'Verktyg'), 'Admin list should render category name.');
    assertTrue(str_contains($html, 'Uthyrning Test'), 'Admin list should render organization name.');
});

$runner->test('rental item admin form validation and repository listing work', static function () use (
    $repository,
    $rentalItemRepository
): void {
    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));

    $pdo->beginTransaction();

    try {
        $organizationOneId = createOrganization('Admin Item One ' . $suffix, 'admin-item-one-' . $suffix);
        $organizationTwoId = createOrganization('Admin Item Two ' . $suffix, 'admin-item-two-' . $suffix);

        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for admin form validation.');
        $globalCategoryId = (int) $globalCategory->toArray()['id'];

        $organizationTwoCategory = $repository->create([
            'organization_id' => $organizationTwoId,
            'slug' => 'admin-org-two-' . $suffix,
            'name' => 'Admin Org Two',
        ]);

        $item = $rentalItemRepository->create([
            'organization_id' => $organizationOneId,
            'primary_category_id' => $globalCategoryId,
            'slug' => 'admin-item-' . $suffix,
            'name' => 'Admin Item',
            'short_name' => 'Admin',
            'description' => 'Visible in admin list.',
            'is_active' => true,
            'is_rentable' => true,
        ]);
        $itemData = $item->toArray();

        $formRequest = new RentalItemFormRequest();
        $duplicate = $formRequest->validate([
            'organization_id' => (string) $organizationOneId,
            'primary_category_id' => (string) $globalCategoryId,
            'slug' => 'admin-item-' . $suffix,
            'name' => 'Duplicate Admin Item',
            'is_active' => '1',
            'is_rentable' => '1',
        ]);
        assertTrue(isset($duplicate['errors']['slug']), 'Duplicate slug in same organization should fail validation.');

        $wrongCategory = $formRequest->validate([
            'organization_id' => (string) $organizationOneId,
            'primary_category_id' => (string) $organizationTwoCategory->toArray()['id'],
            'slug' => 'wrong-category-admin-' . $suffix,
            'name' => 'Wrong Category Admin Item',
            'is_active' => '1',
            'is_rentable' => '1',
        ]);
        assertTrue(isset($wrongCategory['errors']['primary_category_id']), 'Category from another organization should fail validation.');

        $validUpdate = $formRequest->validate([
            'organization_id' => (string) $organizationTwoId,
            'primary_category_id' => (string) $organizationTwoCategory->toArray()['id'],
            'slug' => 'admin-item-moved-' . $suffix,
            'name' => 'Moved Admin Item',
            'short_name' => 'Moved',
            'description' => 'Moved to another organization.',
            'public_id' => 'itm_should_not_change',
            'is_active' => '1',
            'is_rentable' => '1',
        ], $item);
        assertSame([], $validUpdate['errors'], 'Valid admin update should pass validation.');

        $updated = $rentalItemRepository->update((int) $itemData['id'], $validUpdate['data'] + [
            'public_id' => 'itm_should_not_change',
        ]);
        $updatedData = $updated->toArray();

        assertSame($itemData['public_id'], $updatedData['public_id'], 'public_id should remain immutable.');
        assertSame($organizationTwoId, (int) $updatedData['organization_id'], 'Admin update should persist organization selection.');
        assertSame(
            (int) $organizationTwoCategory->toArray()['id'],
            (int) $updatedData['primary_category_id'],
            'Admin update should persist scoped category selection.'
        );

        $adminRows = $rentalItemRepository->findAllForAdmin()->toArray();
        $adminRow = null;

        foreach ($adminRows as $row) {
            if (is_array($row) && (int) ($row['id'] ?? 0) === (int) $itemData['id']) {
                $adminRow = $row;
                break;
            }
        }

        assertNotNull($adminRow, 'Admin list repository should include the updated rental item.');
        assertSame('Moved Admin Item', $adminRow['name'] ?? null, 'Admin list should expose rental item name.');
        assertSame('Admin Item Two ' . $suffix, $adminRow['organization_name'] ?? null, 'Admin list should expose organization name.');
        assertSame('Admin Org Two', $adminRow['primary_category_name'] ?? null, 'Admin list should expose category name.');

        assertTrue($rentalItemRepository->delete((int) $itemData['id']), 'Admin archive should use repository soft delete.');
        assertThrows(
            static fn () => $rentalItemRepository->findById((int) $itemData['id']),
            ModelException::class,
            'Archived rental item should not be found by findById.'
        );

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('item rate admin foundation validates CRUD and active rate uniqueness', static function () use (
    $repository,
    $rentalItemRepository,
    $itemRateRepository
): void {
    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));

    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Rate Admin Test ' . $suffix, 'rate-admin-test-' . $suffix);

        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for item rate admin tests.');

        $item = $rentalItemRepository->create([
            'organization_id' => $organizationId,
            'primary_category_id' => (int) $globalCategory->toArray()['id'],
            'slug' => 'rate-admin-item-' . $suffix,
            'name' => 'Rate Admin Item',
            'is_active' => true,
            'is_rentable' => true,
        ]);
        $itemId = (int) $item->toArray()['id'];

        $formRequest = new ItemRateFormRequest($itemRateRepository);
        $dailyValidation = $formRequest->validate([
            'rate_type' => 'daily',
            'amount' => '300',
            'currency' => 'SEK',
            'is_active' => '1',
        ], $organizationId, $itemId);
        assertSame([], $dailyValidation['errors'], 'Valid daily rate should pass validation.');

        $dailyRate = $itemRateRepository->create($dailyValidation['data'] + [
            'organization_id' => $organizationId,
            'rental_item_id' => $itemId,
        ]);
        assertSame('300.00', $dailyRate->toArray()['amount'] ?? null, 'Daily rate amount should normalize.');

        $duplicateDaily = $formRequest->validate([
            'rate_type' => 'daily',
            'amount' => '325',
            'currency' => 'SEK',
            'is_active' => '1',
        ], $organizationId, $itemId);
        assertTrue(isset($duplicateDaily['errors']['rate_type']), 'Duplicate active daily rate should fail validation.');
        assertThrows(
            static fn () => $itemRateRepository->create($duplicateDaily['data'] + [
                'organization_id' => $organizationId,
                'rental_item_id' => $itemId,
            ]),
            ModelException::class,
            'Repository should guard against duplicate active daily rates.'
        );

        $weeklyRate = $itemRateRepository->create([
            'organization_id' => $organizationId,
            'rental_item_id' => $itemId,
            'rate_type' => 'weekly',
            'amount' => '1500.00',
            'currency' => 'SEK',
            'is_active' => true,
        ]);
        assertThrows(
            static fn () => $itemRateRepository->create([
                'organization_id' => $organizationId,
                'rental_item_id' => $itemId,
                'rate_type' => 'weekly',
                'amount' => '1600.00',
                'currency' => 'SEK',
                'is_active' => true,
            ]),
            ModelException::class,
            'Only one active weekly rate should be allowed.'
        );

        $updatedDaily = $itemRateRepository->update((int) $dailyRate->toArray()['id'], [
            'amount' => '350.00',
            'currency' => 'SEK',
            'is_active' => true,
        ], $organizationId);
        assertSame('350.00', $updatedDaily->toArray()['amount'] ?? null, 'Daily rate should update.');

        $activeRates = $itemRateRepository->findActiveForItem($organizationId, $itemId);
        assertTrue(collectionContainsRateType($activeRates, 'daily'), 'Active rates should include daily rate.');
        assertTrue(collectionContainsRateType($activeRates, 'weekly'), 'Active rates should include weekly rate.');

        $service = new RentalItemPublicationService($rentalItemRepository, $itemRateRepository);
        assertTrue($service->canPublish($item), 'Publication service should find administrated active daily price.');

        assertTrue($itemRateRepository->delete((int) $dailyRate->toArray()['id'], $organizationId), 'Admin archive should soft delete rate.');
        assertThrows(
            static fn () => $itemRateRepository->findByIdForItem($organizationId, $itemId, (int) $dailyRate->toArray()['id']),
            ModelException::class,
            'Soft-deleted item rate should not be found for item.'
        );
        assertFalse(
            collectionContainsRateType($itemRateRepository->findActiveForItem($organizationId, $itemId), 'daily'),
            'Soft-deleted daily rate should be excluded from active rate lookup.'
        );

        assertTrue($weeklyRate instanceof ItemRate, 'Weekly rate should remain available as a Version 1 rate type.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('item rate admin list view renders rate fields and actions', static function (): void {
    $html = (new View())->render('admin/item-rates/index', [
        'item' => [
            'name' => 'Rate View Item',
            'public_id' => 'itm_rate_view',
        ],
        'rates' => [[
            'id' => 12,
            'rate_type' => 'daily',
            'amount' => '450.00',
            'currency' => 'SEK',
            'is_active' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-02 00:00:00',
        ]],
        'csrfToken' => 'test-token',
    ]);

    assertTrue(str_contains($html, 'Rate View Item'), 'Rate list should render item name.');
    assertTrue(str_contains($html, 'Dagspris'), 'Rate list should render type label.');
    assertTrue(str_contains($html, '450.00'), 'Rate list should render amount.');
    assertTrue(str_contains($html, 'SEK'), 'Rate list should render currency.');
    assertTrue(str_contains($html, '/admin/items/itm_rate_view/rates/12/edit'), 'Rate list should render edit link.');
    assertTrue(str_contains($html, '/admin/items/itm_rate_view/rates/12/archive'), 'Rate list should render archive action.');
});

$runner->test('public rental item listing exposes only publishable items', static function () use (
    $repository,
    $rentalItemRepository,
    $itemRateRepository
): void {
    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));

    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Public Listing Test ' . $suffix, 'public-listing-test-' . $suffix);

        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for public listing tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $createItem = static function (
            string $slug,
            string $name,
            array $overrides = []
        ) use ($rentalItemRepository, $organizationId, $categoryId): RentalItem {
            return $rentalItemRepository->create(array_merge([
                'organization_id' => $organizationId,
                'primary_category_id' => $categoryId,
                'slug' => $slug,
                'name' => $name,
                'description' => 'Publik beskrivning.',
                'internal_note' => 'Intern anteckning ska aldrig synas.',
                'publication_status_key' => 'published',
                'is_active' => true,
                'is_rentable' => true,
            ], $overrides));
        };

        $addDailyRate = static function (RentalItem $item, bool $isActive = true) use (
            $itemRateRepository,
            $organizationId
        ): ItemRate {
            return $itemRateRepository->create([
                'organization_id' => $organizationId,
                'rental_item_id' => (int) $item->toArray()['id'],
                'rate_type' => 'daily',
                'amount' => '450.00',
                'currency' => 'SEK',
                'is_active' => $isActive,
            ]);
        };

        $visibleItem = $createItem('public-visible-' . $suffix, 'Public Visible ' . $suffix);
        $addDailyRate($visibleItem);

        $draftItem = $createItem('public-draft-' . $suffix, 'Public Draft ' . $suffix, [
            'publication_status_key' => 'draft',
        ]);
        $addDailyRate($draftItem);

        $archivedItem = $createItem('public-archived-' . $suffix, 'Public Archived ' . $suffix, [
            'publication_status_key' => 'archived',
        ]);
        $addDailyRate($archivedItem);

        $softDeletedItem = $createItem('public-soft-deleted-' . $suffix, 'Public Soft Deleted ' . $suffix);
        $addDailyRate($softDeletedItem);
        $rentalItemRepository->delete((int) $softDeletedItem->toArray()['id']);

        $inactiveItem = $createItem('public-inactive-' . $suffix, 'Public Inactive ' . $suffix, [
            'is_active' => false,
        ]);
        $addDailyRate($inactiveItem);

        $notRentableItem = $createItem('public-not-rentable-' . $suffix, 'Public Not Rentable ' . $suffix, [
            'is_rentable' => false,
        ]);
        $addDailyRate($notRentableItem);

        $createItem('public-without-rate-' . $suffix, 'Public Without Rate ' . $suffix);

        $inactiveRateItem = $createItem('public-inactive-rate-' . $suffix, 'Public Inactive Rate ' . $suffix);
        $addDailyRate($inactiveRateItem, false);

        $softDeletedRateItem = $createItem('public-soft-deleted-rate-' . $suffix, 'Public Soft Deleted Rate ' . $suffix);
        $softDeletedRate = $addDailyRate($softDeletedRateItem);
        $itemRateRepository->delete((int) $softDeletedRate->toArray()['id'], $organizationId);

        $listing = $rentalItemRepository->findPublicListing()->toArray();
        $names = array_map(
            static fn (array $item): string => (string) ($item['name'] ?? ''),
            array_filter($listing, 'is_array')
        );

        assertTrue(in_array('Public Visible ' . $suffix, $names, true), 'Published active rentable item with daily rate should show.');
        assertFalse(in_array('Public Draft ' . $suffix, $names, true), 'Draft item should not show.');
        assertFalse(in_array('Public Archived ' . $suffix, $names, true), 'Archived item should not show.');
        assertFalse(in_array('Public Soft Deleted ' . $suffix, $names, true), 'Soft-deleted item should not show.');
        assertFalse(in_array('Public Inactive ' . $suffix, $names, true), 'Inactive item should not show.');
        assertFalse(in_array('Public Not Rentable ' . $suffix, $names, true), 'Not rentable item should not show.');
        assertFalse(in_array('Public Without Rate ' . $suffix, $names, true), 'Item without daily rate should not show.');
        assertFalse(in_array('Public Inactive Rate ' . $suffix, $names, true), 'Item with inactive daily rate should not show.');
        assertFalse(in_array('Public Soft Deleted Rate ' . $suffix, $names, true), 'Item with soft-deleted daily rate should not show.');

        $visibleRow = null;
        foreach ($listing as $row) {
            if (is_array($row) && ($row['name'] ?? null) === 'Public Visible ' . $suffix) {
                $visibleRow = $row;
                break;
            }
        }

        assertNotNull($visibleRow, 'Visible public row should be available for field checks.');
        assertFalse(array_key_exists('id', $visibleRow), 'Public listing should not expose technical id.');
        assertFalse(array_key_exists('internal_note', $visibleRow), 'Public listing should not expose internal note.');
        assertSame('450.00', $visibleRow['daily_rate_amount'] ?? null, 'Public listing should expose active daily rate.');
        assertSame('SEK', $visibleRow['daily_rate_currency'] ?? null, 'Public listing should expose daily rate currency.');

        $html = (new View())->render('public/items/index', [
            'items' => [$visibleRow + [
                'id' => '999999',
                'internal_note' => 'Hemlig intern notering',
            ]],
        ]);

        assertTrue(str_contains($html, 'Public Visible ' . $suffix), 'Public view should render visible item.');
        assertTrue(str_contains($html, '450 SEK/dag'), 'Public view should render daily rate.');
        assertTrue(
            str_contains($html, '/items/' . rawurlencode((string) $visibleRow['public_id']) . '/' . rawurlencode((string) $visibleRow['slug'])),
            'Public view should link to the detail route.'
        );
        assertTrue(str_contains($html, 'Visa objekt'), 'Public view should render a detail link label.');
        assertFalse(str_contains($html, 'Hemlig intern notering'), 'Public view should not render internal note.');
        assertFalse(str_contains($html, '999999'), 'Public view should not render technical id.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('public rental item listing has empty state and unauthenticated route', static function () use ($basePath): void {
    $html = (new View())->render('public/items/index', [
        'items' => [],
    ]);

    assertTrue(
        str_contains($html, 'Inga objekt finns tillg&auml;ngliga f&ouml;r uthyrning just nu.') ||
        str_contains($html, 'Inga objekt finns tillgÃ¤ngliga fÃ¶r uthyrning just nu.'),
        'Empty public list should show a friendly message.'
    );

    $router = new Router();
    $routes = require $basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
    $routes($router);

    $response = $router->dispatch(new Request('GET', '/items'));

    assertSame(200, $response->statusCode(), 'Public /items route should not require authentication.');
});

$runner->test('public rental item detail renders safe fields and rejects non-public items', static function () use (
    $basePath,
    $repository,
    $rentalItemRepository,
    $itemRateRepository
): void {
    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));

    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Public Detail Test ' . $suffix, 'public-detail-test-' . $suffix);

        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for public detail tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $createItem = static function (
            string $slug,
            string $name,
            array $overrides = []
        ) use ($rentalItemRepository, $organizationId, $categoryId): RentalItem {
            return $rentalItemRepository->create(array_merge([
                'organization_id' => $organizationId,
                'primary_category_id' => $categoryId,
                'slug' => $slug,
                'name' => $name,
                'short_name' => 'Kortnamn ' . $name,
                'description' => 'Publik detaljbeskrivning.',
                'internal_note' => 'Intern detalj ska aldrig synas.',
                'serial_number' => 'SERIE-HEMLIG',
                'inventory_number' => 'INV-HEMLIG',
                'publication_status_key' => 'published',
                'is_active' => true,
                'is_rentable' => true,
                'deposit_amount' => '500.00',
            ], $overrides));
        };

        $addDailyRate = static function (RentalItem $item, bool $isActive = true) use (
            $itemRateRepository,
            $organizationId
        ): ItemRate {
            return $itemRateRepository->create([
                'organization_id' => $organizationId,
                'rental_item_id' => (int) $item->toArray()['id'],
                'rate_type' => 'daily',
                'amount' => '450.00',
                'currency' => 'SEK',
                'is_active' => $isActive,
            ]);
        };

        $router = new Router();
        $routes = require $basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $routes($router);

        $dispatchDetail = static function (RentalItem $item, ?string $publicId = null, ?string $slug = null) use ($router): Response {
            $data = $item->toArray();
            $path = '/items/'
                . rawurlencode($publicId ?? (string) $data['public_id'])
                . '/'
                . rawurlencode($slug ?? (string) $data['slug']);

            return $router->dispatch(new Request('GET', $path));
        };

        $visibleItem = $createItem('public-detail-visible-' . $suffix, 'Public Detail Visible ' . $suffix);
        $addDailyRate($visibleItem);

        $detail = $rentalItemRepository->findPublicDetail(
            (string) $visibleItem->toArray()['public_id'],
            (string) $visibleItem->toArray()['slug']
        );
        assertNotNull($detail, 'Repository should find a valid public detail item.');

        $response = $dispatchDetail($visibleItem);
        $content = $response->content();

        assertSame(200, $response->statusCode(), 'Published active rentable detail route should return 200.');
        assertTrue(str_contains($content, 'Public Detail Visible ' . $suffix), 'Detail should render item name.');
        assertTrue(str_contains($content, 'Kortnamn Public Detail Visible ' . $suffix), 'Detail should render short name.');
        assertTrue(str_contains($content, 'Publik detaljbeskrivning.'), 'Detail should render description.');
        assertTrue(str_contains($content, 'Verktyg'), 'Detail should render primary category.');
        assertTrue(str_contains($content, 'Public Detail Test ' . $suffix), 'Detail should render organization name.');
        assertTrue(str_contains($content, '450 SEK/dag'), 'Detail should render active daily rate.');
        assertTrue(str_contains($content, 'Deposition: 500 SEK'), 'Detail should render deposit when present.');
        assertTrue(str_contains($content, '/items'), 'Detail should include a link back to listing.');
        assertFalse(str_contains($content, 'Intern detalj ska aldrig synas.'), 'Detail should not expose internal note.');
        assertFalse(str_contains($content, 'SERIE-HEMLIG'), 'Detail should not expose serial number.');
        assertFalse(str_contains($content, 'INV-HEMLIG'), 'Detail should not expose inventory number.');

        $safeViewHtml = (new View())->render('public/items/show', [
            'item' => $detail->toArray() + [
                'id' => '999999',
                'internal_note' => 'Hemlig vyanteckning',
            ],
        ]);
        assertFalse(str_contains($safeViewHtml, '999999'), 'Detail view should not render technical id.');
        assertFalse(str_contains($safeViewHtml, 'Hemlig vyanteckning'), 'Detail view should not render internal note.');

        $optionalItem = $createItem('public-detail-optional-' . $suffix, 'Public Detail Optional ' . $suffix, [
            'short_name' => null,
            'description' => null,
            'deposit_amount' => null,
        ]);
        $addDailyRate($optionalItem);
        $optionalResponse = $dispatchDetail($optionalItem);
        assertSame(200, $optionalResponse->statusCode(), 'Empty optional fields should not break detail route.');
        assertFalse(str_contains($optionalResponse->content(), 'Deposition:'), 'Missing deposit should not render deposit row.');

        $rejectedItems = [];

        $draftItem = $createItem('public-detail-draft-' . $suffix, 'Public Detail Draft ' . $suffix, [
            'publication_status_key' => 'draft',
        ]);
        $addDailyRate($draftItem);
        $rejectedItems['draft item'] = $draftItem;

        $archivedItem = $createItem('public-detail-archived-' . $suffix, 'Public Detail Archived ' . $suffix, [
            'publication_status_key' => 'archived',
        ]);
        $addDailyRate($archivedItem);
        $rejectedItems['archived item'] = $archivedItem;

        $softDeletedItem = $createItem('public-detail-soft-deleted-' . $suffix, 'Public Detail Soft Deleted ' . $suffix);
        $addDailyRate($softDeletedItem);
        $rentalItemRepository->delete((int) $softDeletedItem->toArray()['id']);
        $rejectedItems['soft-deleted item'] = $softDeletedItem;

        $inactiveItem = $createItem('public-detail-inactive-' . $suffix, 'Public Detail Inactive ' . $suffix, [
            'is_active' => false,
        ]);
        $addDailyRate($inactiveItem);
        $rejectedItems['inactive item'] = $inactiveItem;

        $notRentableItem = $createItem('public-detail-not-rentable-' . $suffix, 'Public Detail Not Rentable ' . $suffix, [
            'is_rentable' => false,
        ]);
        $addDailyRate($notRentableItem);
        $rejectedItems['not rentable item'] = $notRentableItem;

        $withoutRateItem = $createItem('public-detail-without-rate-' . $suffix, 'Public Detail Without Rate ' . $suffix);
        $rejectedItems['item without active daily rate'] = $withoutRateItem;

        $inactiveRateItem = $createItem('public-detail-inactive-rate-' . $suffix, 'Public Detail Inactive Rate ' . $suffix);
        $addDailyRate($inactiveRateItem, false);
        $rejectedItems['item with inactive daily rate'] = $inactiveRateItem;

        $softDeletedRateItem = $createItem('public-detail-soft-deleted-rate-' . $suffix, 'Public Detail Soft Deleted Rate ' . $suffix);
        $softDeletedRate = $addDailyRate($softDeletedRateItem);
        $itemRateRepository->delete((int) $softDeletedRate->toArray()['id'], $organizationId);
        $rejectedItems['item with soft-deleted daily rate'] = $softDeletedRateItem;

        foreach ($rejectedItems as $case => $item) {
            assertSame(404, $dispatchDetail($item)->statusCode(), $case . ' should return 404.');
            assertSame(
                null,
                $rentalItemRepository->findPublicDetail(
                    (string) $item->toArray()['public_id'],
                    (string) $item->toArray()['slug']
                ),
                $case . ' should not be found by public repository lookup.'
            );
        }

        assertSame(
            404,
            $dispatchDetail($visibleItem, 'itm_missing_' . $suffix)->statusCode(),
            'Wrong public_id should return 404.'
        );
        assertSame(
            404,
            $dispatchDetail($visibleItem, null, 'wrong-slug-' . $suffix)->statusCode(),
            'Wrong slug should return 404 until redirect history exists.'
        );
        assertSame(
            404,
            $router->dispatch(new Request('GET', '/items/' . rawurlencode((string) $visibleItem->toArray()['public_id'])))->statusCode(),
            'Incomplete detail route should return 404.'
        );

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('RentalItemPublicationService enforces Version 1 publication rules', static function () use (
    $repository,
    $rentalItemRepository,
    $itemRateRepository
): void {
    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));

    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Publication Test ' . $suffix, 'publication-test-' . $suffix);

        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for publication tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $service = new RentalItemPublicationService($rentalItemRepository, $itemRateRepository);

        $item = $rentalItemRepository->create([
            'organization_id' => $organizationId,
            'primary_category_id' => $categoryId,
            'slug' => 'publication-item-' . $suffix,
            'name' => 'Publication Item',
            'is_active' => true,
            'is_rentable' => true,
        ]);
        $itemData = $item->toArray();

        assertFalse($service->canPublish($item), 'Rentable draft without active daily rate should not publish.');
        assertThrows(
            static fn () => $service->publish($item),
            ModelException::class,
            'Publishing without daily rate should fail.'
        );

        $inactiveRate = $itemRateRepository->create([
            'organization_id' => $organizationId,
            'rental_item_id' => (int) $itemData['id'],
            'rate_type' => 'daily',
            'amount' => '100.00',
            'is_active' => false,
        ]);
        assertFalse($service->canPublish($item), 'Inactive daily rate should not allow publication.');

        $itemRateRepository->delete((int) $inactiveRate->toArray()['id'], $organizationId);
        assertFalse($service->canPublish($item), 'Soft-deleted daily rate should not allow publication.');

        $itemRateRepository->create([
            'organization_id' => $organizationId,
            'rental_item_id' => (int) $itemData['id'],
            'rate_type' => 'daily',
            'amount' => '125.00',
            'is_active' => true,
        ]);
        assertTrue($service->canPublish($item), 'Complete rentable draft with active daily rate should publish.');

        $published = $service->publish($item);
        assertSame('published', $published->toArray()['publication_status_key'] ?? null, 'publish should set published status.');

        $draft = $service->unpublish($published);
        assertSame('draft', $draft->toArray()['publication_status_key'] ?? null, 'unpublish should move published item to draft.');

        $publishedAgain = $service->publish($draft);
        assertTrue($service->archive($publishedAgain), 'published item should archive.');
        assertThrows(
            static fn () => $rentalItemRepository->findById((int) $itemData['id']),
            ModelException::class,
            'Archived item should be soft-deleted from normal repository lookups.'
        );

        $baseData = $itemData + [
            'publication_status_key' => 'draft',
            'deleted_at' => null,
        ];

        assertFalse($service->canPublish(new RentalItem(array_merge($baseData, ['name' => '']))), 'Draft without name should be denied.');
        assertFalse($service->canPublish(new RentalItem(array_merge($baseData, ['slug' => '']))), 'Draft without slug should be denied.');
        assertFalse($service->canPublish(new RentalItem(array_merge($baseData, ['primary_category_id' => null]))), 'Draft without category should be denied.');
        assertFalse($service->canPublish(new RentalItem(array_merge($baseData, ['is_active' => 0]))), 'Inactive item should be denied.');
        assertFalse($service->canPublish(new RentalItem(array_merge($baseData, ['is_rentable' => 0]))), 'Non-rentable item should be denied.');

        $archivedModel = new RentalItem(array_merge($baseData, ['publication_status_key' => 'archived']));
        assertFalse($service->canPublish($archivedModel), 'Archived item should not publish directly.');
        assertThrows(
            static fn () => $service->publish($archivedModel),
            ModelException::class,
            'Archived item publish should throw.'
        );

        $softDeletedModel = new RentalItem(array_merge($baseData, ['deleted_at' => '2026-01-01 00:00:00']));
        assertFalse($service->canPublish($softDeletedModel), 'Soft-deleted item should never publish.');
        assertThrows(
            static fn () => $service->publish($softDeletedModel),
            ModelException::class,
            'Soft-deleted item publish should throw.'
        );

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('Booking repositories persist scoped guest/customer bookings, snapshots and overlap rules', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingRepository,
    $bookingItemRepository
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));

    $pdo->beginTransaction();

    try {
        $organizationOneId = createOrganization('Booking Test One ' . $suffix, 'booking-test-one-' . $suffix);
        $organizationTwoId = createOrganization('Booking Test Two ' . $suffix, 'booking-test-two-' . $suffix);

        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for booking tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $createItem = static function (
            int $organizationId,
            string $slug,
            string $name
        ) use ($rentalItemRepository, $itemRateRepository, $categoryId): RentalItem {
            $item = $rentalItemRepository->create([
                'organization_id' => $organizationId,
                'primary_category_id' => $categoryId,
                'slug' => $slug,
                'name' => $name,
                'publication_status_key' => 'published',
                'is_active' => true,
                'is_rentable' => true,
                'deposit_amount' => '500.00',
            ]);

            $itemRateRepository->create([
                'organization_id' => $organizationId,
                'rental_item_id' => (int) $item->toArray()['id'],
                'rate_type' => 'daily',
                'amount' => '450.00',
                'currency' => 'SEK',
                'is_active' => true,
            ]);

            return $item;
        };

        $itemOne = $createItem($organizationOneId, 'booking-item-one-' . $suffix, 'Booking Item One ' . $suffix);
        $itemTwo = $createItem($organizationTwoId, 'booking-item-two-' . $suffix, 'Booking Item Two ' . $suffix);
        $customerId = createCustomer($organizationOneId, 'Linked Customer ' . $suffix, 'linked-' . $suffix . '@example.com');

        $guestBooking = $bookingRepository->create([
            'organization_id' => $organizationOneId,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
            'customer_name' => 'Guest Customer',
            'customer_email' => ' Guest@Example.COM ',
            'customer_phone' => '070-123 45 67',
            'company_name' => 'Guest AB',
            'customer_comment' => 'Customer needs pickup instructions.',
            'internal_note' => 'Internal admin note should stay private.',
        ]);
        $guestBookingData = $guestBooking->toArray();

        assertTrue(str_starts_with((string) $guestBookingData['public_id'], 'bkg_'), 'Booking public id should use booking prefix.');
        assertSame('request', $guestBookingData['status_key'] ?? null, 'New booking should default to request.');

        $bookingItem = $bookingItemRepository->create([
            'organization_id' => $organizationOneId,
            'booking_id' => (int) $guestBookingData['id'],
            'rental_item_id' => (int) $itemOne->toArray()['id'],
            'rate_type' => 'daily',
            'unit_price' => '450.00',
            'currency' => 'SEK',
            'quantity' => 1,
            'number_of_units' => 3,
            'subtotal_amount' => '1350.00',
            'deposit_amount' => '500.00',
        ]);

        $refreshedGuestBooking = $bookingRepository->findById((int) $guestBookingData['id'], $organizationOneId);
        $refreshedGuestBookingData = $refreshedGuestBooking->toArray();

        assertSame('3', (string) $refreshedGuestBookingData['total_units'], 'Booking totals should store calendar days.');
        assertSame('1350.00', $refreshedGuestBookingData['subtotal_amount'] ?? null, 'Booking subtotal snapshot should persist.');
        assertSame('500.00', $refreshedGuestBookingData['deposit_amount'] ?? null, 'Booking deposit snapshot should persist.');
        assertSame(1, count($bookingItemRepository->findForBooking($organizationOneId, (int) $guestBookingData['id'])), 'Booking should have one item in V1 flow.');
        assertSame((string) $guestBookingData['public_id'], $bookingRepository->findByPublicId((string) $guestBookingData['public_id'], $organizationOneId)?->toArray()['public_id'] ?? null, 'Booking should be found by public id in organization scope.');
        assertThrows(
            static fn () => $bookingRepository->findById((int) $guestBookingData['id'], $organizationTwoId),
            ModelException::class,
            'Booking lookup should not leak across organizations.'
        );

        $organizationOneBookings = $bookingRepository->findForOrganization($organizationOneId)->toArray();
        $organizationTwoBookings = $bookingRepository->findForOrganization($organizationTwoId)->toArray();
        assertTrue(count($organizationOneBookings) >= 1, 'Organization one should see its booking.');
        assertSame(0, count($organizationTwoBookings), 'Organization two should not see organization one booking.');

        $snapshot = pdo()->prepare(
            'SELECT customer_name, customer_email_normalized, customer_phone, company_name
             FROM booking_customer_snapshots
             WHERE booking_id = :booking_id
             LIMIT 1'
        );
        $snapshot->execute(['booking_id' => (int) $guestBookingData['id']]);
        $snapshotRow = $snapshot->fetch(PDO::FETCH_ASSOC);
        assertSame('Guest Customer', $snapshotRow['customer_name'] ?? null, 'Guest name snapshot should persist.');
        assertSame('guest@example.com', $snapshotRow['customer_email_normalized'] ?? null, 'Guest email should be normalized in snapshot.');
        assertSame('070-123 45 67', $snapshotRow['customer_phone'] ?? null, 'Guest phone snapshot should persist.');
        assertSame('Guest AB', $snapshotRow['company_name'] ?? null, 'Optional company name snapshot should persist.');

        $priceSnapshot = pdo()->prepare(
            'SELECT rate_type, unit_price, currency, number_of_units, subtotal_amount, deposit_amount
             FROM booking_price_snapshots
             WHERE booking_item_id = :booking_item_id
             LIMIT 1'
        );
        $priceSnapshot->execute(['booking_item_id' => (int) $bookingItem->toArray()['id']]);
        $priceSnapshotRow = $priceSnapshot->fetch(PDO::FETCH_ASSOC);
        assertSame('daily', $priceSnapshotRow['rate_type'] ?? null, 'Price snapshot should store rate type.');
        assertSame('450.00', $priceSnapshotRow['unit_price'] ?? null, 'Price snapshot should store unit price.');
        assertSame('SEK', $priceSnapshotRow['currency'] ?? null, 'Price snapshot should store currency.');
        assertSame('3', (string) ($priceSnapshotRow['number_of_units'] ?? ''), 'Price snapshot should store calendar days.');
        assertSame('1350.00', $priceSnapshotRow['subtotal_amount'] ?? null, 'Price snapshot should store subtotal.');
        assertSame('500.00', $priceSnapshotRow['deposit_amount'] ?? null, 'Price snapshot should store deposit separately.');

        $activeRates = $itemRateRepository->findActiveForItem($organizationOneId, (int) $itemOne->toArray()['id'])->toArray();
        $rateId = (int) ($activeRates[0]['id'] ?? 0);
        $itemRateRepository->update($rateId, ['amount' => '999.00'], $organizationOneId);

        $priceSnapshot->execute(['booking_item_id' => (int) $bookingItem->toArray()['id']]);
        $stablePriceSnapshotRow = $priceSnapshot->fetch(PDO::FETCH_ASSOC);
        assertSame('450.00', $stablePriceSnapshotRow['unit_price'] ?? null, 'Item rate changes should not alter booking price snapshot.');
        assertSame('450.00', $bookingItemRepository->findById((int) $bookingItem->toArray()['id'], $organizationOneId)->toArray()['unit_price'] ?? null, 'Item rate changes should not alter booking item snapshot.');

        assertThrows(
            static fn () => $bookingItemRepository->create([
                'organization_id' => $organizationOneId,
                'booking_id' => (int) $guestBookingData['id'],
                'rental_item_id' => (int) $itemTwo->toArray()['id'],
                'rate_type' => 'daily',
                'unit_price' => '450.00',
                'currency' => 'SEK',
                'number_of_units' => 3,
                'subtotal_amount' => '1350.00',
            ]),
            ModelException::class,
            'Cross-tenant rental item booking should be rejected by repository.'
        );

        assertTrue(
            $bookingItemRepository->hasBlockingOverlap(
                $organizationOneId,
                (int) $itemOne->toArray()['id'],
                '2026-10-12',
                '2026-10-13'
            ),
            'Inclusive overlap should block same end/start date.'
        );
        assertFalse(
            $bookingItemRepository->hasBlockingOverlap(
                $organizationOneId,
                (int) $itemOne->toArray()['id'],
                '2026-10-13',
                '2026-10-14'
            ),
            'Non-overlapping dates should be available.'
        );
        assertFalse(
            $bookingItemRepository->hasBlockingOverlap(
                $organizationOneId,
                (int) $itemOne->toArray()['id'],
                '2026-10-10',
                '2026-10-12',
                (int) $guestBookingData['id']
            ),
            'Overlap check should support excluding the current booking.'
        );

        $bookingRepository->updateStatus($organizationOneId, (int) $guestBookingData['id'], 'rejected');
        assertFalse(
            $bookingItemRepository->hasBlockingOverlap(
                $organizationOneId,
                (int) $itemOne->toArray()['id'],
                '2026-10-10',
                '2026-10-12'
            ),
            'Rejected booking should not block the calendar.'
        );

        $historyStatement = pdo()->prepare(
            'SELECT COUNT(*) FROM booking_status_history WHERE booking_id = :booking_id'
        );
        $historyStatement->execute(['booking_id' => (int) $guestBookingData['id']]);
        assertSame(2, (int) $historyStatement->fetchColumn(), 'Initial and updated status should be recorded.');

        $publicBooking = $bookingRepository->findPublicByPublicId((string) $guestBookingData['public_id']);
        assertNotNull($publicBooking, 'Public-safe booking lookup should return booking.');
        $publicBookingData = $publicBooking->toArray();
        assertFalse(array_key_exists('id', $publicBookingData), 'Public booking lookup should not expose technical id.');
        assertFalse(array_key_exists('organization_id', $publicBookingData), 'Public booking lookup should not expose organization id.');
        assertFalse(array_key_exists('customer_id', $publicBookingData), 'Public booking lookup should not expose customer id.');
        assertFalse(array_key_exists('internal_note', $publicBookingData), 'Public booking lookup should not expose internal note.');

        $linkedCustomerBooking = $bookingRepository->create([
            'organization_id' => $organizationOneId,
            'customer_id' => $customerId,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-01',
            'customer_name' => 'Linked Customer ' . $suffix,
            'customer_email' => 'linked-' . $suffix . '@example.com',
            'customer_phone' => '070-000 00 00',
        ]);
        assertSame($customerId, (int) ($linkedCustomerBooking->toArray()['customer_id'] ?? 0), 'Customer FK should persist when customer exists.');

        assertTrue($bookingRepository->delete((int) $guestBookingData['id'], $organizationOneId), 'Booking delete should soft delete.');
        assertThrows(
            static fn () => $bookingRepository->findById((int) $guestBookingData['id'], $organizationOneId),
            ModelException::class,
            'Soft-deleted booking should be hidden from normal repository lookup.'
        );

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('Booking services enforce availability, pricing snapshots, guest creation and audit', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingRepository,
    $bookingAvailabilityService,
    $bookingPricingService,
    $bookingService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationOneId = createOrganization('Booking Service One ' . $suffix, 'booking-service-one-' . $suffix);
        $organizationTwoId = createOrganization('Booking Service Two ' . $suffix, 'booking-service-two-' . $suffix);
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for booking service tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $createItem = static function (
            int $organizationId,
            string $slug,
            string $dailyRate = '450.00',
            string $deposit = '500.00'
        ) use ($rentalItemRepository, $itemRateRepository, $categoryId): RentalItem {
            $item = $rentalItemRepository->create([
                'organization_id' => $organizationId,
                'primary_category_id' => $categoryId,
                'slug' => $slug,
                'name' => 'Bookable ' . $slug,
                'publication_status_key' => 'published',
                'is_active' => true,
                'is_rentable' => true,
                'deposit_amount' => $deposit,
            ]);

            $itemRateRepository->create([
                'organization_id' => $organizationId,
                'rental_item_id' => (int) $item->toArray()['id'],
                'rate_type' => 'daily',
                'amount' => $dailyRate,
                'currency' => 'SEK',
                'is_active' => true,
            ]);

            return $item;
        };

        $item = $createItem($organizationOneId, 'booking-service-main-' . $suffix);
        $itemId = (int) $item->toArray()['id'];
        $pricing = $bookingPricingService->calculateDailySnapshot(
            $organizationOneId,
            $item,
            '2026-12-01',
            '2026-12-01'
        );
        assertSame(1, $pricing['number_of_units'], 'Same-day booking should price as one day.');
        assertSame('450.00', $pricing['subtotal_amount'], 'Same-day subtotal should match one daily rate.');
        assertSame('500.00', $pricing['deposit_amount'], 'Deposit should be snapshotted separately.');

        $booking = $bookingService->createRequest([
            'organization_id' => $organizationOneId,
            'rental_item_id' => $itemId,
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-12',
            'customer_name' => 'Guest Booking',
            'customer_email' => 'Guest.Booking@Example.COM',
            'customer_phone' => '070-111 22 33',
            'company_name' => 'Guest Booking AB',
            'customer_comment' => 'Please confirm pickup time.',
            'internal_note' => 'Internal notes are not audit context.',
        ]);
        $bookingData = $booking->toArray();
        $bookingId = (int) $bookingData['id'];
        assertSame('request', $bookingData['status_key'] ?? null, 'Booking service should create request status.');
        assertSame('3', (string) ($bookingData['total_units'] ?? ''), 'Booking service should persist inclusive day count.');
        assertSame('1350.00', $bookingData['subtotal_amount'] ?? null, 'Booking service should persist calculated subtotal.');
        assertSame('500.00', $bookingData['deposit_amount'] ?? null, 'Booking service should persist deposit snapshot.');

        $snapshotStatement = $pdo->prepare(
            'SELECT customer_name, customer_email_normalized, customer_phone, company_name
             FROM booking_customer_snapshots
             WHERE booking_id = :booking_id
             LIMIT 1'
        );
        $snapshotStatement->execute(['booking_id' => $bookingId]);
        $customerSnapshot = $snapshotStatement->fetch(PDO::FETCH_ASSOC);
        assertSame('Guest Booking', $customerSnapshot['customer_name'] ?? null, 'Guest booking should store customer snapshot.');
        assertSame('guest.booking@example.com', $customerSnapshot['customer_email_normalized'] ?? null, 'Guest email should be normalized.');
        assertSame('070-111 22 33', $customerSnapshot['customer_phone'] ?? null, 'Guest phone should be snapshotted.');
        assertSame('Guest Booking AB', $customerSnapshot['company_name'] ?? null, 'Optional company name should be snapshotted.');

        $priceStatement = $pdo->prepare(
            'SELECT booking_items.id AS booking_item_id,
                booking_items.unit_price AS booking_item_price,
                booking_price_snapshots.unit_price AS snapshot_price,
                booking_price_snapshots.number_of_units,
                booking_price_snapshots.subtotal_amount,
                booking_price_snapshots.deposit_amount
             FROM booking_items
             INNER JOIN booking_price_snapshots
                ON booking_price_snapshots.booking_item_id = booking_items.id
             WHERE booking_items.booking_id = :booking_id
             LIMIT 1'
        );
        $priceStatement->execute(['booking_id' => $bookingId]);
        $priceSnapshot = $priceStatement->fetch(PDO::FETCH_ASSOC);
        assertSame('450.00', $priceSnapshot['booking_item_price'] ?? null, 'Booking item should store unit price snapshot.');
        assertSame('450.00', $priceSnapshot['snapshot_price'] ?? null, 'Price snapshot should store unit price.');
        assertSame('3', (string) ($priceSnapshot['number_of_units'] ?? ''), 'Price snapshot should store charged days.');
        assertSame('1350.00', $priceSnapshot['subtotal_amount'] ?? null, 'Price snapshot should store subtotal.');
        assertSame('500.00', $priceSnapshot['deposit_amount'] ?? null, 'Price snapshot should store deposit.');

        $activeRates = $itemRateRepository->findActiveForItem($organizationOneId, $itemId)->toArray();
        $itemRateRepository->update((int) ($activeRates[0]['id'] ?? 0), ['amount' => '999.00'], $organizationOneId);
        $priceStatement->execute(['booking_id' => $bookingId]);
        $stablePriceSnapshot = $priceStatement->fetch(PDO::FETCH_ASSOC);
        assertSame('450.00', $stablePriceSnapshot['booking_item_price'] ?? null, 'Changed item rate should not alter booking item snapshot.');
        assertSame('450.00', $stablePriceSnapshot['snapshot_price'] ?? null, 'Changed item rate should not alter price snapshot.');

        assertTrue(
            $bookingAvailabilityService->hasBlockingBookings($organizationOneId, $itemId, '2026-12-12', '2026-12-13'),
            'Request booking should block inclusive overlapping dates.'
        );
        assertFalse(
            $bookingAvailabilityService->hasBlockingBookings($organizationOneId, $itemId, '2026-12-13', '2026-12-14'),
            'Non-overlapping dates should remain available.'
        );
        assertThrows(
            static fn () => $bookingAvailabilityService->assertAvailable(
                $organizationOneId,
                $itemId,
                '2026-12-12',
                '2026-12-13'
            ),
            BookingException::class,
            'assertAvailable should reject blocking overlap.'
        );
        assertThrows(
            static fn () => $bookingAvailabilityService->isAvailable(
                $organizationOneId,
                $itemId,
                '2026-12-31',
                '2026-12-30'
            ),
            BookingException::class,
            'Invalid interval should be rejected.'
        );

        assertThrows(
            static fn () => $bookingService->createRequest([
                'organization_id' => $organizationTwoId,
                'rental_item_id' => $itemId,
                'start_date' => '2027-01-10',
                'end_date' => '2027-01-11',
                'customer_name' => 'Wrong Org',
                'customer_email' => 'wrong-org@example.com',
                'customer_phone' => '070-222 33 44',
            ]),
            BookingException::class,
            'Organization scope should not be bypassable.'
        );

        $auditStatement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM audit_logs
             WHERE event_name = :event_name
                AND subject_type = :subject_type
                AND subject_id = :subject_id'
        );
        $auditStatement->execute([
            'event_name' => 'booking_created',
            'subject_type' => 'booking',
            'subject_id' => $bookingId,
        ]);
        assertSame(1, (int) $auditStatement->fetchColumn(), 'booking_created audit event should be recorded.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('Booking availability service blocks only Version 1 blocking statuses', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingRepository,
    $bookingItemRepository,
    $bookingAvailabilityService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Booking Status Availability ' . $suffix, 'booking-status-availability-' . $suffix);
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for status availability tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $createItem = static function (
            string $slug
        ) use ($rentalItemRepository, $itemRateRepository, $organizationId, $categoryId): RentalItem {
            $item = $rentalItemRepository->create([
                'organization_id' => $organizationId,
                'primary_category_id' => $categoryId,
                'slug' => $slug,
                'name' => 'Status Item ' . $slug,
                'publication_status_key' => 'published',
                'is_active' => true,
                'is_rentable' => true,
            ]);
            $itemRateRepository->create([
                'organization_id' => $organizationId,
                'rental_item_id' => (int) $item->toArray()['id'],
                'rate_type' => 'daily',
                'amount' => '100.00',
                'currency' => 'SEK',
                'is_active' => true,
            ]);

            return $item;
        };

        $createBookingForStatus = static function (
            RentalItem $item,
            string $statusKey
        ) use ($organizationId, $bookingRepository, $bookingItemRepository): int {
            $booking = $bookingRepository->create([
                'organization_id' => $organizationId,
                'start_date' => '2027-02-10',
                'end_date' => '2027-02-12',
                'customer_name' => 'Status Customer',
                'customer_email' => 'status-customer@example.com',
                'customer_phone' => '070-333 44 55',
            ]);
            $bookingId = (int) $booking->toArray()['id'];
            $bookingItemRepository->create([
                'organization_id' => $organizationId,
                'booking_id' => $bookingId,
                'rental_item_id' => (int) $item->toArray()['id'],
                'rate_type' => 'daily',
                'unit_price' => '100.00',
                'currency' => 'SEK',
                'quantity' => 1,
                'number_of_units' => 3,
                'subtotal_amount' => '300.00',
            ]);

            if ($statusKey !== 'request') {
                $bookingRepository->updateStatus($organizationId, $bookingId, $statusKey);
            }

            return $bookingId;
        };

        foreach (['request', 'approved', 'active'] as $statusKey) {
            $item = $createItem('blocking-' . $statusKey . '-' . $suffix);
            $createBookingForStatus($item, $statusKey);
            assertFalse(
                $bookingAvailabilityService->isAvailable(
                    $organizationId,
                    (int) $item->toArray()['id'],
                    '2027-02-11',
                    '2027-02-13'
                ),
                $statusKey . ' should block overlapping dates.'
            );
        }

        foreach (['rejected', 'cancelled', 'completed'] as $statusKey) {
            $item = $createItem('nonblocking-' . $statusKey . '-' . $suffix);
            $createBookingForStatus($item, $statusKey);
            assertTrue(
                $bookingAvailabilityService->isAvailable(
                    $organizationId,
                    (int) $item->toArray()['id'],
                    '2027-02-11',
                    '2027-02-13'
                ),
                $statusKey . ' should not block overlapping dates.'
            );
        }

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('Booking status service enforces transitions, history and audit events', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingService,
    $bookingStatusService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Booking Transition ' . $suffix, 'booking-transition-' . $suffix);
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for transition tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];
        $item = $rentalItemRepository->create([
            'organization_id' => $organizationId,
            'primary_category_id' => $categoryId,
            'slug' => 'transition-item-' . $suffix,
            'name' => 'Transition Item ' . $suffix,
            'publication_status_key' => 'published',
            'is_active' => true,
            'is_rentable' => true,
        ]);
        $itemRateRepository->create([
            'organization_id' => $organizationId,
            'rental_item_id' => (int) $item->toArray()['id'],
            'rate_type' => 'daily',
            'amount' => '250.00',
            'currency' => 'SEK',
            'is_active' => true,
        ]);

        $booking = $bookingService->createRequest([
            'rental_item_id' => (int) $item->toArray()['id'],
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-02',
            'customer_name' => 'Transition Guest',
            'customer_email' => 'transition@example.com',
            'customer_phone' => '070-444 55 66',
        ]);
        $bookingId = (int) $booking->toArray()['id'];

        $historyCount = static function (int $bookingId): int {
            $statement = pdo()->prepare('SELECT COUNT(*) FROM booking_status_history WHERE booking_id = :booking_id');
            $statement->execute(['booking_id' => $bookingId]);

            return (int) $statement->fetchColumn();
        };

        assertSame(1, $historyCount($bookingId), 'Initial status history should be created.');
        assertTrue($bookingStatusService->canTransition('request', 'approved'), 'request to approved should be allowed.');
        assertFalse($bookingStatusService->canTransition('request', 'active'), 'request to active should be rejected.');

        $approved = $bookingStatusService->transition($organizationId, $bookingId, 'approved');
        assertSame('approved', $approved->toArray()['status_key'] ?? null, 'Booking should transition to approved.');
        assertSame(2, $historyCount($bookingId), 'Approved transition should append status history.');

        assertThrows(
            static fn () => $bookingStatusService->transition($organizationId, $bookingId, 'completed'),
            BookingException::class,
            'approved to completed should be rejected.'
        );
        assertSame(2, $historyCount($bookingId), 'Rejected transition should not append status history.');

        $active = $bookingStatusService->transition($organizationId, $bookingId, 'active');
        assertSame('active', $active->toArray()['status_key'] ?? null, 'Booking should transition to active.');
        assertSame(3, $historyCount($bookingId), 'Active transition should append status history.');
        assertFalse($bookingStatusService->canTransition('active', 'cancelled'), 'Active cancellation should require administrative reason.');

        $completed = $bookingStatusService->transition($organizationId, $bookingId, 'completed');
        assertSame('completed', $completed->toArray()['status_key'] ?? null, 'Booking should transition to completed.');
        assertSame(4, $historyCount($bookingId), 'Completed transition should append status history.');

        $auditStatement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM audit_logs
             WHERE event_name = :event_name
                AND subject_type = :subject_type
                AND subject_id = :subject_id'
        );

        foreach (['booking_created', 'booking_approved', 'booking_started', 'booking_completed'] as $eventName) {
            $auditStatement->execute([
                'event_name' => $eventName,
                'subject_type' => 'booking',
                'subject_id' => $bookingId,
            ]);
            assertSame(1, (int) $auditStatement->fetchColumn(), $eventName . ' audit event should be recorded.');
        }

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('Booking service rolls back partial writes on transaction error', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $organizationId = createOrganization('Booking Rollback ' . $suffix, 'booking-rollback-' . $suffix);
    $globalCategory = $repository->findBySlug('verktyg');
    assertNotNull($globalCategory, 'Global category should exist for rollback tests.');
    $categoryId = (int) $globalCategory->toArray()['id'];
    $item = $rentalItemRepository->create([
        'organization_id' => $organizationId,
        'primary_category_id' => $categoryId,
        'slug' => 'rollback-item-' . $suffix,
        'name' => 'Rollback Item ' . $suffix,
        'publication_status_key' => 'published',
        'is_active' => true,
        'is_rentable' => true,
    ]);
    $itemRateRepository->create([
        'organization_id' => $organizationId,
        'rental_item_id' => (int) $item->toArray()['id'],
        'rate_type' => 'daily',
        'amount' => '175.00',
        'currency' => 'SEK',
        'is_active' => true,
    ]);

    $countForOrganization = static function (string $table, int $organizationId): int {
        $statement = pdo()->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE organization_id = :organization_id');
        $statement->execute(['organization_id' => $organizationId]);

        return (int) $statement->fetchColumn();
    };

    $bookingCountBefore = $countForOrganization('bookings', $organizationId);
    $snapshotCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM booking_customer_snapshots')->fetchColumn();

    assertThrows(
        static fn () => $bookingService->createRequest([
            'rental_item_id' => (int) $item->toArray()['id'],
            'start_date' => '2027-04-01',
            'end_date' => '2027-04-02',
            'customer_name' => 'Rollback Guest',
            'customer_email' => 'rollback@example.com',
            'customer_phone' => '070-555 66 77',
            'changed_by_user_id' => 999999999,
        ]),
        Throwable::class,
        'Invalid changed_by_user_id should trigger a transactional write error.'
    );

    assertSame($bookingCountBefore, $countForOrganization('bookings', $organizationId), 'Failed booking request should roll back booking row.');
    assertSame(
        $snapshotCountBefore,
        (int) $pdo->query('SELECT COUNT(*) FROM booking_customer_snapshots')->fetchColumn(),
        'Failed booking request should roll back customer snapshot row.'
    );
});

$runner->test('public booking request flow validates input, creates snapshots and shows safe confirmation', static function () use (
    $basePath,
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Public Booking Flow ' . $suffix, 'public-booking-flow-' . $suffix);
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for public booking tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $createItem = static function (
            string $slug,
            string $name,
            array $overrides = []
        ) use ($rentalItemRepository, $itemRateRepository, $organizationId, $categoryId): RentalItem {
            $item = $rentalItemRepository->create(array_merge([
                'organization_id' => $organizationId,
                'primary_category_id' => $categoryId,
                'slug' => $slug,
                'name' => $name,
                'description' => 'Publik bokningsbeskrivning.',
                'publication_status_key' => 'published',
                'is_active' => true,
                'is_rentable' => true,
                'deposit_amount' => '300.00',
            ], $overrides));

            $itemRateRepository->create([
                'organization_id' => $organizationId,
                'rental_item_id' => (int) $item->toArray()['id'],
                'rate_type' => 'daily',
                'amount' => '250.00',
                'currency' => 'SEK',
                'is_active' => true,
            ]);

            return $item;
        };

        $bookableItem = $createItem('public-booking-main-' . $suffix, 'Public Booking Main ' . $suffix);
        $draftItem = $createItem('public-booking-draft-' . $suffix, 'Public Booking Draft ' . $suffix, [
            'publication_status_key' => 'draft',
        ]);
        $archivedItem = $createItem('public-booking-archived-' . $suffix, 'Public Booking Archived ' . $suffix, [
            'publication_status_key' => 'archived',
        ]);

        $router = new Router();
        $routes = require $basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $routes($router);

        $itemData = $bookableItem->toArray();
        $detailPath = '/items/' . rawurlencode((string) $itemData['public_id']) . '/' . rawurlencode((string) $itemData['slug']);
        $bookPath = $detailPath . '/book';
        $today = new DateTimeImmutable('today');
        $bookingStartDate = $today->modify('+14 days')->format('Y-m-d');
        $bookingEndDate = $today->modify('+16 days')->format('Y-m-d');
        $invalidStartDate = $today->modify('+18 days')->format('Y-m-d');
        $invalidEndDate = $today->modify('+17 days')->format('Y-m-d');
        $overlapStartDate = $bookingEndDate;
        $overlapEndDate = $today->modify('+17 days')->format('Y-m-d');
        $validPost = [
            'start_date' => $bookingStartDate,
            'end_date' => $bookingEndDate,
            'customer_name' => 'Public Guest',
            'customer_email' => 'Public.Guest@Example.COM',
            'customer_phone' => '070-111 22 33',
            'company_name' => 'Public Guest AB',
            'customer_comment' => 'Jag vill gärna hämta på morgonen.',
            'organization_id' => '999999',
            'unit_price' => '1.00',
            'currency' => 'EUR',
            'deposit_amount' => '0.00',
            'status_key' => 'approved',
        ];

        $detailResponse = $router->dispatch(new Request('GET', $detailPath));
        assertSame(200, $detailResponse->statusCode(), 'Bookable item detail should return 200.');
        assertTrue(str_contains($detailResponse->content(), 'Boka objekt'), 'Bookable item detail should show booking CTA.');
        assertTrue(str_contains($detailResponse->content(), $bookPath), 'Booking CTA should link to booking form.');

        foreach ([$draftItem, $archivedItem] as $hiddenItem) {
            $hiddenData = $hiddenItem->toArray();
            $hiddenDetailPath = '/items/' . rawurlencode((string) $hiddenData['public_id']) . '/' . rawurlencode((string) $hiddenData['slug']);
            assertSame(404, $router->dispatch(new Request('GET', $hiddenDetailPath))->statusCode(), 'Non-public item detail should return 404.');
            assertSame(404, $router->dispatch(new Request('GET', $hiddenDetailPath . '/book'))->statusCode(), 'Non-public booking route should return 404.');
        }

        $formResponse = $router->dispatch(new Request('GET', $bookPath));
        assertSame(200, $formResponse->statusCode(), 'Booking form should be public without login.');
        assertTrue(str_contains($formResponse->content(), 'Skicka f&ouml;rfr&aring;gan'), 'Booking form should render submit action.');
        assertTrue(str_contains($formResponse->content(), 'csrf_token'), 'Booking form should include CSRF token.');
        assertFalse(str_contains($formResponse->content(), 'organization_id'), 'Booking form should not expose organization input.');
        assertFalse(str_contains($formResponse->content(), 'unit_price'), 'Booking form should not expose price input.');
        assertFalse(str_contains($formResponse->content(), 'status_key'), 'Booking form should not expose status input.');

        $missingCsrfResponse = $router->dispatch(new Request('POST', $bookPath, [], $validPost));
        assertSame(200, $missingCsrfResponse->statusCode(), 'Missing CSRF should render form with safe error.');
        assertTrue(
            str_contains($missingCsrfResponse->content(), 'Formuläret kunde inte verifieras.')
            || str_contains($missingCsrfResponse->content(), 'Formul&auml;ret kunde inte verifieras.'),
            'Missing CSRF should show verification error.'
        );

        $validationCases = [
            'missing name' => array_merge($validPost, ['customer_name' => '']),
            'invalid email' => array_merge($validPost, ['customer_email' => 'not-an-email']),
            'missing start date' => array_merge($validPost, ['start_date' => '']),
            'missing end date' => array_merge($validPost, ['end_date' => '']),
            'start after end' => array_merge($validPost, ['start_date' => $invalidStartDate, 'end_date' => $invalidEndDate]),
        ];

        foreach ($validationCases as $case => $post) {
            $response = $router->dispatch(requestWithValidCsrf('POST', $bookPath, $post));
            assertSame(200, $response->statusCode(), $case . ' should render form with validation errors.');
            assertTrue(str_contains($response->content(), 'public-field-error'), $case . ' should show a field validation error.');
        }

        $bookingCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
        $response = $router->dispatch(requestWithValidCsrf('POST', $bookPath, $validPost));
        assertSame(302, $response->statusCode(), 'Successful booking should redirect.');
        $location = $response->headers()['Location'] ?? '';
        assertTrue(is_string($location) && str_starts_with($location, '/bookings/'), 'Successful booking should redirect to confirmation route.');
        assertTrue(str_ends_with((string) $location, '/confirmation'), 'Successful booking should use confirmation suffix.');

        $publicId = trim(str_replace(['/bookings/', '/confirmation'], '', (string) $location), '/');
        assertTrue(str_starts_with($publicId, 'bkg_'), 'Confirmation route should use booking public id.');

        $bookingStatement = $pdo->prepare('SELECT * FROM bookings WHERE public_id = :public_id LIMIT 1');
        $bookingStatement->execute(['public_id' => $publicId]);
        $booking = $bookingStatement->fetch(PDO::FETCH_ASSOC);
        assertTrue(is_array($booking), 'Booking should be stored after successful request.');
        $bookingId = (int) ($booking['id'] ?? 0);
        assertSame('request', $booking['status_key'] ?? null, 'Public booking should start as request.');
        assertSame($organizationId, (int) ($booking['organization_id'] ?? 0), 'Booking organization should come from rental item.');
        assertSame('3', (string) ($booking['total_units'] ?? ''), 'Booking should store inclusive day count.');
        assertSame('750.00', $booking['subtotal_amount'] ?? null, 'Booking price should come from server-side daily rate.');
        assertSame('300.00', $booking['deposit_amount'] ?? null, 'Booking deposit should come from rental item.');
        assertSame('SEK', $booking['currency'] ?? null, 'Booking currency should come from server-side rate.');

        $snapshotStatement = $pdo->prepare(
            'SELECT customer_name, customer_email_normalized, customer_phone, company_name
             FROM booking_customer_snapshots
             WHERE booking_id = :booking_id
             LIMIT 1'
        );
        $snapshotStatement->execute(['booking_id' => $bookingId]);
        $customerSnapshot = $snapshotStatement->fetch(PDO::FETCH_ASSOC);
        assertSame('Public Guest', $customerSnapshot['customer_name'] ?? null, 'Customer snapshot should store guest name.');
        assertSame('public.guest@example.com', $customerSnapshot['customer_email_normalized'] ?? null, 'Customer snapshot should normalize email.');
        assertSame('070-111 22 33', $customerSnapshot['customer_phone'] ?? null, 'Customer snapshot should store phone.');
        assertSame('Public Guest AB', $customerSnapshot['company_name'] ?? null, 'Customer snapshot should store optional company.');

        $priceSnapshotStatement = $pdo->prepare(
            'SELECT unit_price, currency, number_of_units, subtotal_amount, deposit_amount
             FROM booking_price_snapshots
             WHERE booking_id = :booking_id
             LIMIT 1'
        );
        $priceSnapshotStatement->execute(['booking_id' => $bookingId]);
        $priceSnapshot = $priceSnapshotStatement->fetch(PDO::FETCH_ASSOC);
        assertSame('250.00', $priceSnapshot['unit_price'] ?? null, 'Price snapshot should ignore manipulated price input.');
        assertSame('SEK', $priceSnapshot['currency'] ?? null, 'Price snapshot should ignore manipulated currency input.');
        assertSame('3', (string) ($priceSnapshot['number_of_units'] ?? ''), 'Price snapshot should store inclusive days.');
        assertSame('750.00', $priceSnapshot['subtotal_amount'] ?? null, 'Price snapshot should store subtotal.');
        assertSame('300.00', $priceSnapshot['deposit_amount'] ?? null, 'Price snapshot should store deposit.');

        $auditStatement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM audit_logs
             WHERE event_name = :event_name
                AND subject_type = :subject_type
                AND subject_id = :subject_id'
        );
        $auditStatement->execute([
            'event_name' => 'booking_created',
            'subject_type' => 'booking',
            'subject_id' => $bookingId,
        ]);
        assertSame(1, (int) $auditStatement->fetchColumn(), 'booking_created audit event should be recorded.');

        $overlapCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
        $overlapResponse = $router->dispatch(requestWithValidCsrf('POST', $bookPath, array_merge($validPost, [
            'customer_email' => 'second@example.com',
            'start_date' => $overlapStartDate,
            'end_date' => $overlapEndDate,
        ])));
        assertSame(200, $overlapResponse->statusCode(), 'Overlapping booking should render form with safe error.');
        assertTrue(
            str_contains($overlapResponse->content(), 'inte tillgängligt')
            || str_contains($overlapResponse->content(), 'inte tillg&auml;ngligt'),
            'Overlapping booking should show unavailable message.'
        );
        assertSame($overlapCountBefore, (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(), 'Overlapping booking should not create partial booking.');

        $confirmationResponse = $router->dispatch(new Request('GET', (string) $location));
        assertSame(200, $confirmationResponse->statusCode(), 'Confirmation should return 200 for public booking reference.');
        $confirmationContent = $confirmationResponse->content();
        assertTrue(str_contains($confirmationContent, 'Bokningsf&ouml;rfr&aringgan mottagen'), 'Confirmation should show received message.');
        assertTrue(str_contains($confirmationContent, $publicId), 'Confirmation should show public booking reference.');
        assertTrue(str_contains($confirmationContent, 'Public Booking Main ' . $suffix), 'Confirmation should show item name.');
        assertTrue(str_contains($confirmationContent, $bookingStartDate), 'Confirmation should show start date.');
        assertTrue(str_contains($confirmationContent, $bookingEndDate), 'Confirmation should show end date.');
        assertTrue(str_contains($confirmationContent, '750 SEK'), 'Confirmation should show price snapshot.');
        assertFalse(str_contains($confirmationContent, (string) $bookingId . '</span>'), 'Confirmation should not expose technical booking id.');
        assertFalse(str_contains($confirmationContent, 'Public.Guest@Example.COM'), 'Confirmation should not expose submitted customer email.');

        $safeConfirmationHtml = (new View())->render('public/bookings/confirmation', [
            'booking' => [
                'id' => '999999',
                'public_id' => 'bkg_public_only',
                'rental_item_name' => 'Safe Item',
                'start_date' => '2027-05-10',
                'end_date' => '2027-05-12',
                'total_units' => 3,
                'subtotal_amount' => '750.00',
                'deposit_amount' => '300.00',
                'currency' => 'SEK',
                'internal_note' => 'Hemlig intern anteckning',
                'customer_email' => 'secret@example.com',
            ],
        ]);
        assertFalse(str_contains($safeConfirmationHtml, '999999'), 'Confirmation view should not render injected technical id.');
        assertFalse(str_contains($safeConfirmationHtml, 'Hemlig intern anteckning'), 'Confirmation view should not render internal notes.');
        assertFalse(str_contains($safeConfirmationHtml, 'secret@example.com'), 'Confirmation view should not render customer email.');

        assertSame(404, $router->dispatch(new Request('GET', '/bookings/bkg_missing_' . $suffix . '/confirmation'))->statusCode(), 'Unknown confirmation reference should return safe 404.');
        assertTrue((int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn() > $bookingCountBefore, 'Successful booking should be the only new booking in this flow.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('admin booking management enforces access, scoped display, transitions, history, audit and CSRF', static function () use (
    $basePath,
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingRepository,
    $bookingService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationOneId = createOrganization('Admin Booking One ' . $suffix, 'admin-booking-one-' . $suffix);
        $organizationTwoId = createOrganization('Admin Booking Two ' . $suffix, 'admin-booking-two-' . $suffix);
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for admin booking tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $createItem = static function (
            int $organizationId,
            string $slug,
            string $name
        ) use ($rentalItemRepository, $itemRateRepository, $categoryId): RentalItem {
            $item = $rentalItemRepository->create([
                'organization_id' => $organizationId,
                'primary_category_id' => $categoryId,
                'slug' => $slug,
                'name' => $name,
                'publication_status_key' => 'published',
                'is_active' => true,
                'is_rentable' => true,
                'deposit_amount' => '500.00',
            ]);

            $itemRateRepository->create([
                'organization_id' => $organizationId,
                'rental_item_id' => (int) $item->toArray()['id'],
                'rate_type' => 'daily',
                'amount' => '300.00',
                'currency' => 'SEK',
                'is_active' => true,
            ]);

            return $item;
        };

        $createBooking = static function (
            RentalItem $item,
            string $startDate,
            string $customerName,
            array $overrides = []
        ) use ($bookingService): Booking {
            $booking = $bookingService->createRequest(array_merge([
                'rental_item_id' => (int) ($item->toArray()['id'] ?? 0),
                'start_date' => $startDate,
                'end_date' => $startDate,
                'customer_name' => $customerName,
                'customer_email' => strtolower(str_replace(' ', '-', $customerName)) . '@example.com',
                'customer_phone' => '070-100 20 30',
                'company_name' => 'Admin Booking AB',
                'customer_comment' => 'Kunden behöver hämta tidigt.',
                'internal_note' => 'Internal admin note is private.',
            ], $overrides));

            return $booking;
        };

        $router = new Router();
        $routes = require $basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $routes($router);

        $adminItem = $createItem($organizationOneId, 'admin-booking-item-' . $suffix, 'Admin Booking Item ' . $suffix);
        $otherTenantItem = $createItem($organizationTwoId, 'other-booking-item-' . $suffix, 'Other Booking Item ' . $suffix);
        $requestBooking = $createBooking($adminItem, '2027-06-01', 'Admin Booking Guest ' . $suffix);
        $requestBookingData = $requestBooking->toArray();
        $requestBookingId = (int) ($requestBookingData['id'] ?? 0);
        $requestPublicId = (string) ($requestBookingData['public_id'] ?? '');

        $noteStatement = $pdo->prepare(
            'INSERT INTO booking_notes (
                booking_id,
                note_type,
                body,
                is_internal,
                created_at,
                updated_at
            ) VALUES (
                :booking_id,
                :note_type,
                :body,
                1,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $noteStatement->execute([
            'booking_id' => $requestBookingId,
            'note_type' => 'internal',
            'body' => 'Existing internal booking note.',
        ]);

        $otherTenantBooking = $createBooking($otherTenantItem, '2027-06-10', 'Other Tenant Guest ' . $suffix);
        assertSame(
            null,
            $bookingRepository->findAdminByPublicId((string) $otherTenantBooking->toArray()['public_id'], $organizationOneId),
            'Scoped admin lookup should not leak cross-tenant bookings.'
        );

        $adminSession = createAuthenticatedTestUser(true);
        $userSession = createAuthenticatedTestUser(false);
        $adminToken = $adminSession['token'];
        $adminUserId = $adminSession['user_id'];
        $adminCookies = ['uthyrning_session' => $adminToken];
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Uthyrning test runner',
        ];

        $unauthenticatedList = $router->dispatch(new Request('GET', '/admin/bookings'));
        assertSame(302, $unauthenticatedList->statusCode(), 'Unauthenticated users should be redirected from admin bookings.');
        assertSame('/login', $unauthenticatedList->headers()['Location'] ?? null, 'Unauthenticated route should redirect to login.');

        $forbiddenList = $router->dispatch(new Request(
            'GET',
            '/admin/bookings',
            [],
            [],
            ['uthyrning_session' => $userSession['token']],
            $server
        ));
        assertSame(403, $forbiddenList->statusCode(), 'Users without system_admin role should be denied.');

        $adminList = $router->dispatch(new Request('GET', '/admin/bookings', [], [], $adminCookies, $server));
        assertSame(200, $adminList->statusCode(), 'system_admin should show booking list.');
        assertTrue(str_contains($adminList->content(), $requestPublicId), 'Admin list should render booking public id.');
        assertTrue(str_contains($adminList->content(), 'Admin Booking Guest ' . $suffix), 'Admin list should render customer snapshot name.');
        assertTrue(str_contains($adminList->content(), 'Admin Booking Item ' . $suffix), 'Admin list should render item name.');
        assertTrue(str_contains($adminList->content(), '300.00 SEK'), 'Admin list should render total price and currency.');

        $filteredList = $router->dispatch(new Request(
            'GET',
            '/admin/bookings?status=request',
            [],
            [],
            $adminCookies,
            $server
        ));
        assertSame(200, $filteredList->statusCode(), 'Status-filtered admin list should render.');
        assertTrue(str_contains($filteredList->content(), $requestPublicId), 'Status filter should include request booking.');

        $detail = $router->dispatch(new Request(
            'GET',
            '/admin/bookings/' . rawurlencode($requestPublicId),
            [],
            [],
            $adminCookies,
            $server
        ));
        assertSame(200, $detail->statusCode(), 'Admin detail should open by public id.');
        $detailContent = $detail->content();
        assertTrue(str_contains($detailContent, 'Kundsnapshot'), 'Admin detail should show customer snapshot section.');
        assertTrue(str_contains($detailContent, 'Admin Booking Guest ' . $suffix), 'Admin detail should show customer name.');
        assertTrue(str_contains($detailContent, 'Kunden behöver hämta tidigt.'), 'Admin detail should show customer comment.');
        assertTrue(str_contains($detailContent, 'Internal admin note is private.'), 'Admin detail should show booking internal note.');
        assertTrue(str_contains($detailContent, 'Existing internal booking note.'), 'Admin detail should show existing internal notes.');
        assertTrue(str_contains($detailContent, 'Statushistorik'), 'Admin detail should show status history.');
        assertTrue(str_contains($detailContent, 'Förfrågan'), 'Admin detail should render readable status history.');
        assertTrue(str_contains($detailContent, '/admin/bookings/' . rawurlencode($requestPublicId) . '/approve'), 'Admin detail should render approve action.');

        $missingResponse = $router->dispatch(new Request(
            'GET',
            '/admin/bookings/bkg_missing_' . $suffix,
            [],
            [],
            $adminCookies,
            $server
        ));
        assertSame(404, $missingResponse->statusCode(), 'Unknown booking public id should return safe 404.');

        $csrfBooking = $createBooking($adminItem, '2027-06-02', 'Missing CSRF Guest ' . $suffix);
        $csrfPublicId = (string) ($csrfBooking->toArray()['public_id'] ?? '');
        $missingCsrf = $router->dispatch(new Request(
            'POST',
            '/admin/bookings/' . rawurlencode($csrfPublicId) . '/approve',
            [],
            ['status_key' => 'approved'],
            $adminCookies,
            $server
        ));
        assertSame(302, $missingCsrf->statusCode(), 'POST without CSRF should be rejected through a safe redirect.');
        assertSame(
            'request',
            $bookingRepository->findByPublicId($csrfPublicId, $organizationOneId)?->toArray()['status_key'] ?? null,
            'Missing CSRF should not change booking status.'
        );

        $manipulatedBooking = $createBooking($adminItem, '2027-06-03', 'Manipulated Status Guest ' . $suffix);
        $manipulatedPublicId = (string) ($manipulatedBooking->toArray()['public_id'] ?? '');
        $approveResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode($manipulatedPublicId) . '/approve',
            $adminToken,
            ['status_key' => 'completed']
        ));
        assertSame(302, $approveResponse->statusCode(), 'Approved transition should redirect after success.');
        $approvedBooking = $bookingRepository->findByPublicId($manipulatedPublicId, $organizationOneId);
        assertSame('approved', $approvedBooking?->toArray()['status_key'] ?? null, 'Route should decide status and ignore manipulated POST status.');

        $historyActorStatement = $pdo->prepare(
            'SELECT changed_by_user_id
             FROM booking_status_history
             WHERE booking_id = :booking_id
                AND to_status_key = :status_key
             ORDER BY id DESC
             LIMIT 1'
        );
        $historyActorStatement->execute([
            'booking_id' => (int) ($approvedBooking?->toArray()['id'] ?? 0),
            'status_key' => 'approved',
        ]);
        assertSame($adminUserId, (int) $historyActorStatement->fetchColumn(), 'Admin actor should be stored in status history.');

        $auditCount = static function (string $eventName, int $bookingId) use ($pdo): int {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM audit_logs
                 WHERE event_name = :event_name
                    AND actor_user_id IS NOT NULL
                    AND subject_type = :subject_type
                    AND subject_id = :subject_id'
            );
            $statement->execute([
                'event_name' => $eventName,
                'subject_type' => 'booking',
                'subject_id' => $bookingId,
            ]);

            return (int) $statement->fetchColumn();
        };

        assertSame(
            1,
            $auditCount('booking_approved', (int) ($approvedBooking?->toArray()['id'] ?? 0)),
            'booking_approved audit event should be recorded with admin actor.'
        );

        $rejectBooking = $createBooking($adminItem, '2027-06-04', 'Rejected Guest ' . $suffix);
        $rejectResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode((string) ($rejectBooking->toArray()['public_id'] ?? '')) . '/reject',
            $adminToken
        ));
        assertSame(302, $rejectResponse->statusCode(), 'Reject transition should redirect.');
        assertSame(
            'rejected',
            $bookingRepository->findByPublicId((string) ($rejectBooking->toArray()['public_id'] ?? ''), $organizationOneId)?->toArray()['status_key'] ?? null,
            'Request booking should be rejectable.'
        );
        assertSame(1, $auditCount('booking_rejected', (int) ($rejectBooking->toArray()['id'] ?? 0)), 'booking_rejected audit should be recorded.');

        $cancelBooking = $createBooking($adminItem, '2027-06-05', 'Cancelled Guest ' . $suffix);
        $cancelResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode((string) ($cancelBooking->toArray()['public_id'] ?? '')) . '/cancel',
            $adminToken
        ));
        assertSame(302, $cancelResponse->statusCode(), 'Cancel transition should redirect.');
        assertSame(
            'cancelled',
            $bookingRepository->findByPublicId((string) ($cancelBooking->toArray()['public_id'] ?? ''), $organizationOneId)?->toArray()['status_key'] ?? null,
            'Request booking should be cancellable.'
        );
        assertSame(1, $auditCount('booking_cancelled', (int) ($cancelBooking->toArray()['id'] ?? 0)), 'booking_cancelled audit should be recorded.');

        $flowBooking = $createBooking($adminItem, '2027-06-06', 'Lifecycle Guest ' . $suffix);
        $flowPublicId = (string) ($flowBooking->toArray()['public_id'] ?? '');
        $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode($flowPublicId) . '/approve',
            $adminToken
        ));
        $startResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode($flowPublicId) . '/start',
            $adminToken
        ));
        assertSame(302, $startResponse->statusCode(), 'Approved booking should be startable.');
        assertSame(
            'active',
            $bookingRepository->findByPublicId($flowPublicId, $organizationOneId)?->toArray()['status_key'] ?? null,
            'Approved booking should transition to active.'
        );
        assertSame(1, $auditCount('booking_started', (int) ($flowBooking->toArray()['id'] ?? 0)), 'booking_started audit should be recorded.');

        $completeResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode($flowPublicId) . '/complete',
            $adminToken
        ));
        assertSame(302, $completeResponse->statusCode(), 'Active booking should be completable.');
        assertSame(
            'completed',
            $bookingRepository->findByPublicId($flowPublicId, $organizationOneId)?->toArray()['status_key'] ?? null,
            'Active booking should transition to completed.'
        );
        assertSame(1, $auditCount('booking_completed', (int) ($flowBooking->toArray()['id'] ?? 0)), 'booking_completed audit should be recorded.');

        $invalidTransitionBooking = $createBooking($adminItem, '2027-06-07', 'Invalid Transition Guest ' . $suffix);
        $invalidTransitionResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode((string) ($invalidTransitionBooking->toArray()['public_id'] ?? '')) . '/complete',
            $adminToken
        ));
        assertSame(302, $invalidTransitionResponse->statusCode(), 'Invalid transition should redirect with a safe error.');
        assertSame(
            'request',
            $bookingRepository->findByPublicId((string) ($invalidTransitionBooking->toArray()['public_id'] ?? ''), $organizationOneId)?->toArray()['status_key'] ?? null,
            'Invalid transition should not alter status.'
        );

        $activeCancelBooking = $createBooking($adminItem, '2027-06-08', 'Active Cancel Guest ' . $suffix);
        $activeCancelPublicId = (string) ($activeCancelBooking->toArray()['public_id'] ?? '');
        $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode($activeCancelPublicId) . '/approve',
            $adminToken
        ));
        $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode($activeCancelPublicId) . '/start',
            $adminToken
        ));
        $activeCancelResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/bookings/' . rawurlencode($activeCancelPublicId) . '/cancel',
            $adminToken
        ));
        assertSame(302, $activeCancelResponse->statusCode(), 'Active booking should be cancellable with administrative reason.');
        assertSame(
            'cancelled',
            $bookingRepository->findByPublicId($activeCancelPublicId, $organizationOneId)?->toArray()['status_key'] ?? null,
            'Active cancellation should persist.'
        );

        $confirmationResponse = $router->dispatch(new Request(
            'GET',
            '/bookings/' . rawurlencode($requestPublicId) . '/confirmation'
        ));
        assertSame(200, $confirmationResponse->statusCode(), 'Public confirmation flow should continue to work.');
        assertFalse(str_contains($confirmationResponse->content(), 'Internal admin note is private.'), 'Public confirmation should not expose internal notes.');

        $publicItemData = $adminItem->toArray();
        $publicFormPath = '/items/'
            . rawurlencode((string) ($publicItemData['public_id'] ?? ''))
            . '/'
            . rawurlencode((string) ($publicItemData['slug'] ?? ''))
            . '/book';
        assertSame(200, $router->dispatch(new Request('GET', $publicFormPath))->statusCode(), 'Public booking form should continue to work.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('manual availability blocks affect central booking availability', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $availabilityBlockRepository,
    $bookingAvailabilityService,
    $bookingService,
    $bookingStatusService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationOneId = createOrganization('Availability One ' . $suffix, 'availability-one-' . $suffix);
        $organizationTwoId = createOrganization('Availability Two ' . $suffix, 'availability-two-' . $suffix);
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for availability block tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $item = createBookableRentalItem(
            $organizationOneId,
            $categoryId,
            'availability-item-' . $suffix,
            'Availability Item ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );
        $otherTenantItem = createBookableRentalItem(
            $organizationTwoId,
            $categoryId,
            'availability-other-' . $suffix,
            'Availability Other ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );
        $itemId = (int) $item->toArray()['id'];

        assertThrows(
            static fn () => $availabilityBlockRepository->create([
                'organization_id' => $organizationOneId,
                'rental_item_id' => (int) $otherTenantItem->toArray()['id'],
                'start_date' => '2030-01-10',
                'end_date' => '2030-01-10',
                'reason_code' => 'manual',
            ]),
            ModelException::class,
            'Cross-tenant availability block should be rejected.'
        );

        assertThrows(
            static fn () => $availabilityBlockRepository->create([
                'organization_id' => $organizationOneId,
                'rental_item_id' => $itemId,
                'start_date' => '2030-01-12',
                'end_date' => '2030-01-10',
                'reason_code' => 'manual',
            ]),
            ModelException::class,
            'Availability block start after end should be rejected.'
        );

        $block = $availabilityBlockRepository->create([
            'organization_id' => $organizationOneId,
            'rental_item_id' => $itemId,
            'start_date' => '2030-01-10',
            'end_date' => '2030-01-10',
            'reason_code' => 'owner_use',
            'internal_note' => 'Internal availability note',
        ]);
        assertTrue($block instanceof ItemAvailabilityBlock, 'Availability block should be created.');
        assertSame(1, $availabilityBlockRepository->findForItem($organizationOneId, $itemId)->count(), 'Block should be listed for item.');
        assertSame(1, $availabilityBlockRepository->findBlockingForItemAndRange($organizationOneId, $itemId, '2030-01-10', '2030-01-10')->count(), 'Same-day block should use inclusive overlap.');
        assertFalse($bookingAvailabilityService->isAvailable($organizationOneId, $itemId, '2030-01-10', '2030-01-10'), 'Manual block should make item unavailable.');

        assertThrows(
            static fn () => $bookingService->createRequest([
                'rental_item_id' => $itemId,
                'start_date' => '2030-01-10',
                'end_date' => '2030-01-10',
                'customer_name' => 'Blocked Guest',
                'customer_email' => 'blocked@example.com',
                'customer_phone' => '070-000 00 00',
            ]),
            BookingException::class,
            'Booking request should be rejected when manual block overlaps.'
        );

        assertTrue($availabilityBlockRepository->delete((int) $block->toArray()['id'], $organizationOneId), 'Manual block should soft delete.');
        assertTrue($bookingAvailabilityService->isAvailable($organizationOneId, $itemId, '2030-01-10', '2030-01-10'), 'Soft-deleted block should not make item unavailable.');

        $requestBooking = $bookingService->createRequest([
            'rental_item_id' => $itemId,
            'start_date' => '2030-01-11',
            'end_date' => '2030-01-11',
            'customer_name' => 'Request Guest',
            'customer_email' => 'request@example.com',
            'customer_phone' => '070-111 11 11',
        ]);
        assertFalse($bookingAvailabilityService->isAvailable($organizationOneId, $itemId, '2030-01-11', '2030-01-11'), 'Existing request should continue to block availability.');

        $rejected = $bookingStatusService->transition(
            $organizationOneId,
            (int) $requestBooking->toArray()['id'],
            'rejected'
        );
        assertSame('rejected', $rejected->toArray()['status_key'] ?? null, 'Booking should transition to rejected.');
        assertTrue($bookingAvailabilityService->isAvailable($organizationOneId, $itemId, '2030-01-11', '2030-01-11'), 'Rejected booking should not block availability.');

        $approvedBooking = $bookingService->createRequest([
            'rental_item_id' => $itemId,
            'start_date' => '2030-01-12',
            'end_date' => '2030-01-12',
            'customer_name' => 'Approved Guest',
            'customer_email' => 'approved@example.com',
            'customer_phone' => '070-222 22 22',
        ]);
        $bookingStatusService->transition($organizationOneId, (int) $approvedBooking->toArray()['id'], 'approved');
        assertFalse($bookingAvailabilityService->isAvailable($organizationOneId, $itemId, '2030-01-12', '2030-01-12'), 'Approved booking should continue to block availability.');

        $activeBooking = $bookingService->createRequest([
            'rental_item_id' => $itemId,
            'start_date' => '2030-01-13',
            'end_date' => '2030-01-13',
            'customer_name' => 'Active Guest',
            'customer_email' => 'active@example.com',
            'customer_phone' => '070-333 33 33',
        ]);
        $bookingStatusService->transition($organizationOneId, (int) $activeBooking->toArray()['id'], 'approved');
        $bookingStatusService->transition($organizationOneId, (int) $activeBooking->toArray()['id'], 'active');
        assertFalse($bookingAvailabilityService->isAvailable($organizationOneId, $itemId, '2030-01-13', '2030-01-13'), 'Active booking should continue to block availability.');

        $cancelledBooking = $bookingService->createRequest([
            'rental_item_id' => $itemId,
            'start_date' => '2030-01-14',
            'end_date' => '2030-01-14',
            'customer_name' => 'Cancelled Guest',
            'customer_email' => 'cancelled@example.com',
            'customer_phone' => '070-444 44 44',
        ]);
        $bookingStatusService->transition($organizationOneId, (int) $cancelledBooking->toArray()['id'], 'cancelled');
        assertTrue($bookingAvailabilityService->isAvailable($organizationOneId, $itemId, '2030-01-14', '2030-01-14'), 'Cancelled booking should not block availability.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('public availability calendar exposes only safe day states', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $availabilityBlockRepository,
    $availabilityCalendarService,
    $basePath
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Calendar Public ' . $suffix, 'calendar-public-' . $suffix);
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for calendar tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];
        $item = createBookableRentalItem(
            $organizationId,
            $categoryId,
            'calendar-public-item-' . $suffix,
            'Calendar Public Item ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );
        $itemData = $item->toArray();
        $rentalItemId = (int) $itemData['id'];
        $today = new DateTimeImmutable('today');
        $availableDate = $today->modify('+9 days')->format('Y-m-d');
        $blockedDate = $today->modify('+10 days')->format('Y-m-d');
        $rangeEnd = $today->modify('+11 days')->format('Y-m-d');

        $availabilityBlockRepository->create([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
            'start_date' => $blockedDate,
            'end_date' => $blockedDate,
            'reason_code' => 'maintenance',
            'internal_note' => 'Private service reason',
        ]);

        $calendar = $availabilityCalendarService->publicRange(
            $organizationId,
            $rentalItemId,
            $availableDate,
            $rangeEnd,
            $availableDate,
            $rangeEnd
        );

        assertSame($availableDate, $calendar['from_date'] ?? null, 'Calendar should expose requested from date.');
        assertSame($rangeEnd, $calendar['to_date'] ?? null, 'Calendar should expose requested to date.');
        assertTrue(is_array($calendar['days'] ?? null), 'Calendar days should be present.');

        $states = [];
        foreach ($calendar['days'] as $day) {
            assertFalse(array_key_exists('reason_code', $day), 'Public calendar day should not expose block reason.');
            assertFalse(array_key_exists('booking_status', $day), 'Public calendar day should not expose booking status.');
            assertFalse(array_key_exists('customer', $day), 'Public calendar day should not expose customer data.');
            $states[$day['date']] = $day['state'];
        }

        assertSame('available', $states[$availableDate] ?? null, 'Available date should be public-safe available.');
        assertSame('unavailable', $states[$blockedDate] ?? null, 'Blocked date should be public-safe unavailable.');

        assertThrows(
            static fn () => $availabilityCalendarService->publicRange(
                $organizationId,
                $rentalItemId,
                $today->format('Y-m-d'),
                $today->modify('+7 months')->format('Y-m-d')
            ),
            BookingException::class,
            'Public calendar range beyond six months should be rejected.'
        );

        $router = new Router();
        $routes = require $basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $routes($router);
        $bookPath = '/items/'
            . rawurlencode((string) ($itemData['public_id'] ?? ''))
            . '/'
            . rawurlencode((string) ($itemData['slug'] ?? ''))
            . '/book';
        $response = $router->dispatch(new Request('GET', $bookPath));
        assertSame(200, $response->statusCode(), 'Public booking form should render calendar foundation.');
        assertTrue(str_contains($response->content(), 'Tillg&auml;nglighet'), 'Public booking form should show availability calendar.');
        assertTrue(str_contains($response->content(), 'aria-disabled'), 'Public calendar should include accessibility state.');
        assertFalse(str_contains($response->content(), 'Private service reason'), 'Public calendar should not expose internal block reason.');
        assertFalse(str_contains($response->content(), 'maintenance'), 'Public calendar should not expose internal block type.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('interactive public calendar keeps backend availability as source of truth', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $availabilityBlockRepository,
    $availabilityCalendarService,
    $bookingService,
    $bookingStatusService,
    $basePath
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Interactive Calendar ' . $suffix, 'interactive-calendar-' . $suffix);
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for interactive calendar tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];
        $item = createBookableRentalItem(
            $organizationId,
            $categoryId,
            'interactive-calendar-item-' . $suffix,
            'Interactive Calendar Item ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );
        $itemData = $item->toArray();
        $rentalItemId = (int) $itemData['id'];
        $today = new DateTimeImmutable('today');
        $pastDate = $today->modify('-1 day')->format('Y-m-d');
        $startDate = $today->modify('+6 days')->format('Y-m-d');
        $blockedMiddleDate = $today->modify('+7 days')->format('Y-m-d');
        $endDate = $today->modify('+8 days')->format('Y-m-d');
        $sameDayDate = $today->modify('+9 days')->format('Y-m-d');
        $bookingBlockedDate = $today->modify('+10 days')->format('Y-m-d');
        $rejectedDate = $today->modify('+11 days')->format('Y-m-d');
        $cancelledDate = $today->modify('+12 days')->format('Y-m-d');
        $completedDate = $today->modify('+13 days')->format('Y-m-d');
        $tooFarDate = $today->modify('+6 months')->modify('+1 day')->format('Y-m-d');

        $availabilityBlockRepository->create([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
            'start_date' => $blockedMiddleDate,
            'end_date' => $blockedMiddleDate,
            'reason_code' => 'manual',
            'internal_note' => 'Private blocked middle date',
        ]);

        $blockingBooking = $bookingService->createRequest([
            'rental_item_id' => $rentalItemId,
            'start_date' => $bookingBlockedDate,
            'end_date' => $bookingBlockedDate,
            'customer_name' => 'Secret Booking Customer',
            'customer_email' => 'secret-booking@example.com',
            'customer_phone' => '070-999 99 99',
        ]);

        $rejectedBooking = $bookingService->createRequest([
            'rental_item_id' => $rentalItemId,
            'start_date' => $rejectedDate,
            'end_date' => $rejectedDate,
            'customer_name' => 'Rejected Calendar Guest',
            'customer_email' => 'rejected-calendar@example.com',
            'customer_phone' => '070-111 11 12',
        ]);
        $bookingStatusService->transition($organizationId, (int) $rejectedBooking->toArray()['id'], 'rejected');

        $cancelledBooking = $bookingService->createRequest([
            'rental_item_id' => $rentalItemId,
            'start_date' => $cancelledDate,
            'end_date' => $cancelledDate,
            'customer_name' => 'Cancelled Calendar Guest',
            'customer_email' => 'cancelled-calendar@example.com',
            'customer_phone' => '070-111 11 13',
        ]);
        $bookingStatusService->transition($organizationId, (int) $cancelledBooking->toArray()['id'], 'cancelled');

        $completedBooking = $bookingService->createRequest([
            'rental_item_id' => $rentalItemId,
            'start_date' => $completedDate,
            'end_date' => $completedDate,
            'customer_name' => 'Completed Calendar Guest',
            'customer_email' => 'completed-calendar@example.com',
            'customer_phone' => '070-111 11 14',
        ]);
        $bookingStatusService->transition($organizationId, (int) $completedBooking->toArray()['id'], 'approved');
        $bookingStatusService->transition($organizationId, (int) $completedBooking->toArray()['id'], 'active');
        $bookingStatusService->transition($organizationId, (int) $completedBooking->toArray()['id'], 'completed');

        $calendar = $availabilityCalendarService->publicMonths(
            $organizationId,
            $rentalItemId,
            $startDate,
            $endDate
        );

        assertTrue(is_array($calendar['months'] ?? null), 'Interactive calendar should expose grouped months.');
        assertTrue(count($calendar['months']) >= 2, 'Interactive calendar should include month navigation data.');
        assertSame($today->format('Y-m-d'), $calendar['min_date'] ?? null, 'Calendar should expose today as minimum public date.');
        assertSame($today->modify('+6 months')->format('Y-m-d'), $calendar['max_date'] ?? null, 'Calendar should expose six-month maximum public date.');

        $states = [];
        foreach ($calendar['days'] as $day) {
            assertFalse(array_key_exists('reason_code', $day), 'Calendar day should not expose manual block reason.');
            assertFalse(array_key_exists('booking_status', $day), 'Calendar day should not expose booking status.');
            assertFalse(array_key_exists('booking_public_id', $day), 'Calendar day should not expose booking public id.');
            assertFalse(array_key_exists('customer_email', $day), 'Calendar day should not expose customer data.');
            $states[$day['date']] = $day['state'];
        }

        assertSame('unavailable', $states[$pastDate] ?? null, 'Past date should be unavailable in public calendar.');
        assertSame('available', $states[$startDate] ?? null, 'Start date should be available.');
        assertSame('unavailable', $states[$blockedMiddleDate] ?? null, 'Manual block should be unavailable.');
        assertSame('unavailable', $states[$bookingBlockedDate] ?? null, 'Blocking booking should be unavailable.');
        assertSame('available', $states[$rejectedDate] ?? null, 'Rejected booking should not block calendar.');
        assertSame('available', $states[$cancelledDate] ?? null, 'Cancelled booking should not block calendar.');
        assertSame('available', $states[$completedDate] ?? null, 'Completed booking should not block calendar.');

        $formRequest = new BookingRequestFormRequest();
        $pastValidation = $formRequest->validate([
            'start_date' => $pastDate,
            'end_date' => $today->format('Y-m-d'),
            'customer_name' => 'Past Guest',
            'customer_email' => 'past@example.com',
            'customer_phone' => '070-123 45 67',
        ], (string) $itemData['public_id'], (string) $itemData['slug']);
        assertTrue(isset($pastValidation['errors']['start_date']), 'Past booking start date should be rejected server-side.');

        $tooFarValidation = $formRequest->validate([
            'start_date' => $tooFarDate,
            'end_date' => $tooFarDate,
            'customer_name' => 'Future Guest',
            'customer_email' => 'future@example.com',
            'customer_phone' => '070-123 45 68',
        ], (string) $itemData['public_id'], (string) $itemData['slug']);
        assertTrue(isset($tooFarValidation['errors']['end_date']), 'Booking beyond six months should be rejected server-side.');

        $router = new Router();
        $routes = require $basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $routes($router);
        $bookPath = '/items/'
            . rawurlencode((string) ($itemData['public_id'] ?? ''))
            . '/'
            . rawurlencode((string) ($itemData['slug'] ?? ''))
            . '/book';

        $formResponse = $router->dispatch(new Request('GET', $bookPath));
        assertSame(200, $formResponse->statusCode(), 'Public booking form should render.');
        assertTrue(str_contains($formResponse->content(), 'booking-calendar.js'), 'Public booking form should load the progressive calendar script.');
        assertTrue(str_contains($formResponse->content(), 'data-calendar-date'), 'Calendar should render date buttons.');
        assertTrue(str_contains($formResponse->content(), 'data-calendar-available="0"'), 'Calendar should expose only public unavailable state.');
        assertTrue(str_contains($formResponse->content(), 'Rensa datumval'), 'Calendar should allow clearing selection.');
        assertFalse(str_contains($formResponse->content(), 'Private blocked middle date'), 'Calendar HTML should not expose internal block notes.');
        assertFalse(str_contains($formResponse->content(), 'Secret Booking Customer'), 'Calendar HTML should not expose booking customer name.');
        assertFalse(str_contains($formResponse->content(), 'secret-booking@example.com'), 'Calendar HTML should not expose booking customer email.');
        assertFalse(str_contains($formResponse->content(), (string) ($blockingBooking->toArray()['public_id'] ?? '')), 'Calendar HTML should not expose booking public id.');

        $bookingCountBeforeBlockedPost = (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
        $blockedPostResponse = $router->dispatch(requestWithValidCsrf('POST', $bookPath, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'customer_name' => 'Blocked Period Guest',
            'customer_email' => 'blocked-period@example.com',
            'customer_phone' => '070-222 22 22',
        ]));
        assertSame(200, $blockedPostResponse->statusCode(), 'Period with blocked middle date should render safe error.');
        assertTrue(
            str_contains($blockedPostResponse->content(), 'inte tillgängligt')
            || str_contains($blockedPostResponse->content(), 'inte tillg&auml;ngligt'),
            'Blocked period should show user-friendly unavailable message.'
        );
        assertSame($bookingCountBeforeBlockedPost, (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(), 'Blocked period should not create booking.');

        $sameDayResponse = $router->dispatch(requestWithValidCsrf('POST', $bookPath, [
            'start_date' => $sameDayDate,
            'end_date' => $sameDayDate,
            'customer_name' => 'Same Day Guest',
            'customer_email' => 'same-day@example.com',
            'customer_phone' => '070-333 33 33',
        ]));
        assertSame(302, $sameDayResponse->statusCode(), 'Same-day no-JS date field booking should work when available.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('admin availability block flow enforces auth, CSRF, scope, audit and archive', static function () use (
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $availabilityBlockRepository,
    $bookingService,
    $bookingAvailabilityService,
    $basePath
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationId = createOrganization('Admin Availability ' . $suffix, 'admin-availability-' . $suffix);
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for admin availability tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];
        $item = createBookableRentalItem(
            $organizationId,
            $categoryId,
            'admin-availability-item-' . $suffix,
            'Admin Availability Item ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );
        $itemData = $item->toArray();
        $itemPublicId = (string) ($itemData['public_id'] ?? '');
        $itemId = (int) ($itemData['id'] ?? 0);
        $basePathForItem = '/admin/items/' . rawurlencode($itemPublicId) . '/availability';
        $router = new Router();
        $routes = require $basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $routes($router);

        $adminSession = createAuthenticatedTestUser(true);
        $userSession = createAuthenticatedTestUser(false);
        $adminToken = $adminSession['token'];
        $adminUserId = $adminSession['user_id'];
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Uthyrning test runner',
        ];

        assertSame(302, $router->dispatch(new Request('GET', $basePathForItem))->statusCode(), 'Unauthenticated users should not list availability blocks.');
        assertSame(403, $router->dispatch(new Request(
            'GET',
            $basePathForItem,
            [],
            [],
            ['uthyrning_session' => $userSession['token']],
            $server
        ))->statusCode(), 'Non-admin users should not list availability blocks.');

        $adminList = $router->dispatch(new Request(
            'GET',
            $basePathForItem,
            [],
            [],
            ['uthyrning_session' => $adminToken],
            $server
        ));
        assertSame(200, $adminList->statusCode(), 'Admin should list availability blocks.');
        assertTrue(str_contains($adminList->content(), 'Ny blockering'), 'Admin list should link to create block.');

        $createForm = $router->dispatch(new Request(
            'GET',
            $basePathForItem . '/create',
            [],
            [],
            ['uthyrning_session' => $adminToken],
            $server
        ));
        assertSame(200, $createForm->statusCode(), 'Admin should open create form.');
        assertTrue(str_contains($createForm->content(), 'csrf_token'), 'Create form should include CSRF token.');

        $validPost = [
            'start_date' => '2030-02-10',
            'end_date' => '2030-02-10',
            'reason_code' => 'owner_use',
            'internal_note' => 'Owner needs the item.',
        ];
        $missingCsrf = $router->dispatch(new Request(
            'POST',
            $basePathForItem,
            [],
            $validPost,
            ['uthyrning_session' => $adminToken],
            $server
        ));
        assertSame(200, $missingCsrf->statusCode(), 'Admin create without CSRF should render safe error.');
        assertSame(0, $availabilityBlockRepository->findForItem($organizationId, $itemId)->count(), 'Missing CSRF should not create block.');

        $invalidRange = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            $basePathForItem,
            $adminToken,
            array_merge($validPost, ['start_date' => '2030-02-12', 'end_date' => '2030-02-10'])
        ));
        assertSame(200, $invalidRange->statusCode(), 'Admin create should reject start after end.');
        assertSame(0, $availabilityBlockRepository->findForItem($organizationId, $itemId)->count(), 'Invalid range should not create block.');

        $bookingService->createRequest([
            'rental_item_id' => $itemId,
            'start_date' => '2030-02-11',
            'end_date' => '2030-02-11',
            'customer_name' => 'Existing Booking',
            'customer_email' => 'existing-booking@example.com',
            'customer_phone' => '070-777 77 77',
        ]);
        $overBooking = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            $basePathForItem,
            $adminToken,
            array_merge($validPost, ['start_date' => '2030-02-11', 'end_date' => '2030-02-11'])
        ));
        assertSame(200, $overBooking->statusCode(), 'Admin create should reject block over existing booking.');
        assertSame(0, $availabilityBlockRepository->findForItem($organizationId, $itemId)->count(), 'Overlapping booking should not create block.');

        $createResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            $basePathForItem,
            $adminToken,
            $validPost
        ));
        assertSame(302, $createResponse->statusCode(), 'Admin create should redirect after success.');
        assertSame(1, $availabilityBlockRepository->findForItem($organizationId, $itemId)->count(), 'Admin create should store block.');
        assertFalse($bookingAvailabilityService->isAvailable($organizationId, $itemId, '2030-02-10', '2030-02-10'), 'Admin block should affect booking availability.');

        $createdBlock = $availabilityBlockRepository->findBlockingForItemAndRange(
            $organizationId,
            $itemId,
            '2030-02-10',
            '2030-02-10'
        )->toArray()[0] ?? null;
        assertTrue(is_array($createdBlock), 'Created availability block should be retrievable.');
        $createdBlockId = (int) ($createdBlock['id'] ?? 0);

        $auditStatement = $pdo->prepare(
            'SELECT context_json
             FROM audit_logs
             WHERE event_name = :event_name
                AND actor_user_id = :actor_user_id
                AND subject_type = :subject_type
                AND subject_id = :subject_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $auditStatement->execute([
            'event_name' => 'availability_block_created',
            'actor_user_id' => $adminUserId,
            'subject_type' => 'blocked_period',
            'subject_id' => $createdBlockId,
        ]);
        $createdAuditContext = (string) $auditStatement->fetchColumn();
        assertTrue(str_contains($createdAuditContext, 'reason_code'), 'Create audit should include safe reason code.');
        assertFalse(str_contains($createdAuditContext, 'Owner needs the item.'), 'Create audit should not include internal note.');

        $duplicateResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            $basePathForItem,
            $adminToken,
            array_merge($validPost, ['internal_note' => 'Duplicate'])
        ));
        assertSame(200, $duplicateResponse->statusCode(), 'Duplicate overlapping block type should render safe error.');
        assertSame(1, $availabilityBlockRepository->findForItem($organizationId, $itemId)->count(), 'Duplicate overlapping block should not be created.');

        $missingArchiveCsrf = $router->dispatch(new Request(
            'POST',
            $basePathForItem . '/' . $createdBlockId . '/archive',
            [],
            [],
            ['uthyrning_session' => $adminToken],
            $server
        ));
        assertSame(302, $missingArchiveCsrf->statusCode(), 'Archive without CSRF should redirect safely.');
        assertFalse($bookingAvailabilityService->isAvailable($organizationId, $itemId, '2030-02-10', '2030-02-10'), 'Missing archive CSRF should keep block active.');

        $archiveResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            $basePathForItem . '/' . $createdBlockId . '/archive',
            $adminToken
        ));
        assertSame(302, $archiveResponse->statusCode(), 'Archive should redirect after success.');
        assertSame(0, $availabilityBlockRepository->findForItem($organizationId, $itemId)->count(), 'Archived block should disappear from active list.');
        assertTrue($bookingAvailabilityService->isAvailable($organizationId, $itemId, '2030-02-10', '2030-02-10'), 'Archived block should no longer affect booking availability.');

        $auditStatement->execute([
            'event_name' => 'availability_block_archived',
            'actor_user_id' => $adminUserId,
            'subject_type' => 'blocked_period',
            'subject_id' => $createdBlockId,
        ]);
        $archivedAuditContext = (string) $auditStatement->fetchColumn();
        assertTrue(str_contains($archivedAuditContext, 'reason_code'), 'Archive audit should include safe reason code.');
        assertFalse(str_contains($archivedAuditContext, 'Owner needs the item.'), 'Archive audit should not include internal note.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

$runner->test('admin notification operations enforce access, privacy, retry, audit and independence', static function () use (
    $basePath,
    $seederRunner,
    $repository,
    $rentalItemRepository,
    $itemRateRepository,
    $bookingRepository,
    $bookingItemRepository,
    $bookingAvailabilityService,
    $bookingPricingService
): void {
    $seederRunner->run();

    $pdo = pdo();
    $suffix = bin2hex(random_bytes(4));
    $pdo->beginTransaction();

    try {
        $organizationOneId = createOrganization('Notification Admin One ' . $suffix, 'notification-admin-one-' . $suffix);
        $organizationTwoId = createOrganization('Notification Admin Two ' . $suffix, 'notification-admin-two-' . $suffix);
        setOrganizationEmail($organizationOneId, 'admin-notify-' . $suffix . '@example.com');
        setOrganizationEmail($organizationTwoId, 'other-notify-' . $suffix . '@example.com');
        $globalCategory = $repository->findBySlug('verktyg');
        assertNotNull($globalCategory, 'Global category should exist for notification admin tests.');
        $categoryId = (int) $globalCategory->toArray()['id'];

        $item = createBookableRentalItem(
            $organizationOneId,
            $categoryId,
            'notification-admin-item-' . $suffix,
            'Notification Admin Item ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );
        $otherTenantItem = createBookableRentalItem(
            $organizationTwoId,
            $categoryId,
            'notification-other-item-' . $suffix,
            'Notification Other Item ' . $suffix,
            $rentalItemRepository,
            $itemRateRepository
        );

        $notificationRepository = new NotificationRepository();
        $notificationAttemptRepository = new NotificationAttemptRepository();
        $templateService = new NotificationTemplateService();
        $failingTransport = new DevelopmentEmailTransport(true);
        $failingNotificationService = new NotificationService(
            $notificationRepository,
            new NotificationDispatcher(
                $notificationRepository,
                $notificationAttemptRepository,
                $templateService,
                new AuditService(),
                $failingTransport
            ),
            $templateService,
            new AuditService()
        );
        $successTransport = new DevelopmentEmailTransport();
        $successNotificationService = new NotificationService(
            $notificationRepository,
            new NotificationDispatcher(
                $notificationRepository,
                $notificationAttemptRepository,
                $templateService,
                new AuditService(),
                $successTransport
            ),
            $templateService,
            new AuditService()
        );
        $failingBookingService = new BookingService(
            $bookingRepository,
            $bookingItemRepository,
            $rentalItemRepository,
            $bookingAvailabilityService,
            $bookingPricingService,
            new AuditService(),
            $failingNotificationService
        );
        $successBookingService = new BookingService(
            $bookingRepository,
            $bookingItemRepository,
            $rentalItemRepository,
            $bookingAvailabilityService,
            $bookingPricingService,
            new AuditService(),
            $successNotificationService
        );

        $retryBooking = $failingBookingService->createRequest([
            'rental_item_id' => (int) $item->toArray()['id'],
            'start_date' => '2032-01-10',
            'end_date' => '2032-01-11',
            'customer_name' => 'Retry Guest ' . $suffix,
            'customer_email' => 'retry-' . $suffix . '@example.com',
            'customer_phone' => '070-700 00 10',
            'customer_comment' => 'Public comment stays with booking.',
            'internal_note' => 'Booking snapshot should not change on retry.',
        ]);
        $retryBookingId = (int) $retryBooking->toArray()['id'];
        $retryNotification = notificationForBooking($retryBookingId, 'booking_created', 'customer');
        $retryData = $retryNotification->toArray();
        $retryNotificationId = (int) ($retryData['id'] ?? 0);
        $retryPublicId = (string) ($retryData['public_id'] ?? '');
        $originalIdempotencyKey = (string) ($retryData['idempotency_key'] ?? '');

        $sentBooking = $successBookingService->createRequest([
            'rental_item_id' => (int) $item->toArray()['id'],
            'start_date' => '2032-01-12',
            'end_date' => '2032-01-12',
            'customer_name' => 'Sent Guest ' . $suffix,
            'customer_email' => 'sent-' . $suffix . '@example.com',
            'customer_phone' => '070-700 00 12',
        ]);
        $sentNotification = notificationForBooking((int) $sentBooking->toArray()['id'], 'booking_created', 'customer');
        $sentPublicId = (string) ($sentNotification->toArray()['public_id'] ?? '');

        $approvedBooking = $successBookingService->createRequest([
            'rental_item_id' => (int) $item->toArray()['id'],
            'start_date' => '2032-01-13',
            'end_date' => '2032-01-13',
            'customer_name' => 'Approved Notification Guest ' . $suffix,
            'customer_email' => 'approved-notification-' . $suffix . '@example.com',
            'customer_phone' => '070-700 00 13',
        ]);
        $statusService = new BookingStatusService($bookingRepository, new AuditService(), $successNotificationService);
        $statusService->transition($organizationOneId, (int) $approvedBooking->toArray()['id'], 'approved');
        $approvedNotification = notificationForBooking((int) $approvedBooking->toArray()['id'], 'booking_approved', 'customer');
        $approvedPublicId = (string) ($approvedNotification->toArray()['public_id'] ?? '');

        $maxBooking = $failingBookingService->createRequest([
            'rental_item_id' => (int) $item->toArray()['id'],
            'start_date' => '2032-01-14',
            'end_date' => '2032-01-14',
            'customer_name' => 'Max Attempts Guest ' . $suffix,
            'customer_email' => 'max-' . $suffix . '@example.com',
            'customer_phone' => '070-700 00 14',
        ]);
        $failingNotificationService->notifyBookingCreated($maxBooking);
        $failingNotificationService->notifyBookingCreated($maxBooking);
        $maxNotification = notificationForBooking((int) $maxBooking->toArray()['id'], 'booking_created', 'customer');
        $maxPublicId = (string) ($maxNotification->toArray()['public_id'] ?? '');

        $otherBooking = $successBookingService->createRequest([
            'rental_item_id' => (int) $otherTenantItem->toArray()['id'],
            'start_date' => '2032-01-15',
            'end_date' => '2032-01-15',
            'customer_name' => 'Other Tenant Notification ' . $suffix,
            'customer_email' => 'other-tenant-' . $suffix . '@example.com',
            'customer_phone' => '070-700 00 15',
        ]);
        $otherNotification = notificationForBooking((int) $otherBooking->toArray()['id'], 'booking_created', 'customer');
        $otherPublicId = (string) ($otherNotification->toArray()['public_id'] ?? '');

        assertSame('pending', $retryData['status_key'] ?? null, 'Retryable failed delivery should stay pending while attempts remain.');
        assertSame(1, (int) ($retryData['attempts_count'] ?? 0), 'Initial failed delivery should register one attempt.');
        assertSame('failed', $maxNotification->toArray()['status_key'] ?? null, 'Maxed notification should be failed.');
        assertSame(3, (int) ($maxNotification->toArray()['attempts_count'] ?? 0), 'Maxed notification should have three attempts.');
        assertSame(
            null,
            $notificationRepository->findAdminByPublicId($otherPublicId, $organizationOneId),
            'Scoped notification lookup should not leak cross-tenant notifications.'
        );

        $router = new Router();
        $routes = require $basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $routes($router);

        $adminSession = createAuthenticatedTestUser(true);
        $userSession = createAuthenticatedTestUser(false);
        $adminToken = $adminSession['token'];
        $adminUserId = $adminSession['user_id'];
        $adminCookies = ['uthyrning_session' => $adminToken];
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Uthyrning test runner',
        ];

        $unauthenticatedList = $router->dispatch(new Request('GET', '/admin/notifications'));
        assertSame(302, $unauthenticatedList->statusCode(), 'Unauthenticated users should be redirected from notification admin.');

        $forbiddenList = $router->dispatch(new Request(
            'GET',
            '/admin/notifications',
            [],
            [],
            ['uthyrning_session' => $userSession['token']],
            $server
        ));
        assertSame(403, $forbiddenList->statusCode(), 'Non-admin users should be denied notification admin.');

        $adminList = $router->dispatch(new Request('GET', '/admin/notifications', [], [], $adminCookies, $server));
        assertSame(200, $adminList->statusCode(), 'system_admin should list notifications.');
        $listContent = $adminList->content();
        assertTrue(str_contains($listContent, $retryPublicId), 'Notification list should include public reference.');
        assertTrue(str_contains($listContent, 'booking_created'), 'Notification list should include event.');
        assertTrue(str_contains($listContent, 'email'), 'Notification list should include channel.');
        assertTrue(str_contains($listContent, 're***@example.com'), 'Notification list should show minimized recipient.');
        assertFalse(str_contains($listContent, 'retry-' . $suffix . '@example.com'), 'Notification list should not expose full recipient.');
        assertTrue(str_contains($listContent, 'Notification Admin One ' . $suffix), 'Notification list should show organization.');
        assertFalse(str_contains($listContent, 'htmlBody'), 'Notification list should not expose mail body.');
        assertFalse(str_contains($listContent, 'smtp_password'), 'Notification list should not expose credentials.');
        assertFalse(str_contains($listContent, 'Booking snapshot should not change on retry.'), 'Notification list should not expose booking snapshot details.');

        $pendingFilter = $router->dispatch(new Request(
            'GET',
            '/admin/notifications?status=pending',
            [],
            [],
            $adminCookies,
            $server
        ));
        assertSame(200, $pendingFilter->statusCode(), 'Status filter should render.');
        assertTrue(str_contains($pendingFilter->content(), $retryPublicId), 'Pending filter should include retryable notification.');

        $failedFilter = $router->dispatch(new Request(
            'GET',
            '/admin/notifications?status=failed',
            [],
            [],
            $adminCookies,
            $server
        ));
        assertSame(200, $failedFilter->statusCode(), 'Failed status filter should render.');
        assertTrue(str_contains($failedFilter->content(), $maxPublicId), 'Failed filter should include maxed notification.');

        $eventFilter = $router->dispatch(new Request(
            'GET',
            '/admin/notifications?event=booking_approved',
            [],
            [],
            $adminCookies,
            $server
        ));
        assertSame(200, $eventFilter->statusCode(), 'Event filter should render.');
        assertTrue(str_contains($eventFilter->content(), $approvedPublicId), 'Event filter should include matching notification.');
        assertFalse(str_contains($eventFilter->content(), $retryPublicId), 'Event filter should exclude other events.');

        $detail = $router->dispatch(new Request(
            'GET',
            '/admin/notifications/' . rawurlencode($retryPublicId),
            [],
            [],
            $adminCookies,
            $server
        ));
        assertSame(200, $detail->statusCode(), 'Notification detail should open by public id.');
        $detailContent = $detail->content();
        assertTrue(str_contains($detailContent, 'retry-' . $suffix . '@example.com'), 'Detail may show full recipient to authorized admin.');
        assertTrue(str_contains($detailContent, 'Försökshistorik'), 'Detail should show attempt history.');
        assertTrue(str_contains($detailContent, 'development_failure'), 'Detail should show safe error category.');
        assertFalse(str_contains($detailContent, 'smtp_password'), 'Detail should not expose credentials.');
        assertFalse(str_contains($detailContent, 'htmlBody'), 'Detail should not expose mail body.');
        assertFalse(str_contains($detailContent, 'Booking snapshot should not change on retry.'), 'Detail should not expose complete booking snapshot.');

        $missingDetail = $router->dispatch(new Request(
            'GET',
            '/admin/notifications/ntf_missing_' . $suffix,
            [],
            [],
            $adminCookies,
            $server
        ));
        assertSame(404, $missingDetail->statusCode(), 'Unknown notification public id should return safe 404.');

        $missingCsrf = $router->dispatch(new Request(
            'POST',
            '/admin/notifications/' . rawurlencode($retryPublicId) . '/retry',
            [],
            [],
            $adminCookies,
            $server
        ));
        assertSame(302, $missingCsrf->statusCode(), 'Retry without CSRF should redirect safely.');
        assertSame(
            1,
            $notificationAttemptRepository->countForNotification($retryNotificationId),
            'Missing CSRF should not create a new attempt.'
        );

        $bookingStatusBefore = $bookingRepository->findByPublicId((string) $retryBooking->toArray()['public_id'], $organizationOneId)?->toArray()['status_key'] ?? null;
        $snapshotBefore = pdo()->prepare(
            'SELECT customer_email, customer_name
             FROM booking_customer_snapshots
             WHERE booking_id = :booking_id
             LIMIT 1'
        );
        $snapshotBefore->execute(['booking_id' => $retryBookingId]);
        $snapshotBeforeData = $snapshotBefore->fetch(PDO::FETCH_ASSOC);
        $notificationCountBefore = countRows('notifications');

        $retryResponse = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/notifications/' . rawurlencode($retryPublicId) . '/retry',
            $adminToken
        ));
        assertSame(302, $retryResponse->statusCode(), 'Manual retry should redirect after processing.');

        $retriedNotification = $notificationRepository->findByPublicId($retryPublicId);
        assertNotNull($retriedNotification, 'Retried notification should still exist.');
        $retriedData = $retriedNotification->toArray();
        assertSame('sent', $retriedData['status_key'] ?? null, 'Development retry success should mark notification sent.');
        assertSame(2, (int) ($retriedData['attempts_count'] ?? 0), 'Retry should append one new attempt.');
        assertSame(2, $notificationAttemptRepository->countForNotification($retryNotificationId), 'Retry should create one new append-only attempt.');
        assertSame($originalIdempotencyKey, $retriedData['idempotency_key'] ?? null, 'Retry should keep idempotency key unchanged.');
        assertSame($notificationCountBefore, countRows('notifications'), 'Retry should not create a new logical notification.');
        assertSame(
            $bookingStatusBefore,
            $bookingRepository->findByPublicId((string) $retryBooking->toArray()['public_id'], $organizationOneId)?->toArray()['status_key'] ?? null,
            'Retry should not change booking status.'
        );
        $snapshotAfter = pdo()->prepare(
            'SELECT customer_email, customer_name
             FROM booking_customer_snapshots
             WHERE booking_id = :booking_id
             LIMIT 1'
        );
        $snapshotAfter->execute(['booking_id' => $retryBookingId]);
        assertSame($snapshotBeforeData, $snapshotAfter->fetch(PDO::FETCH_ASSOC), 'Retry should not change booking snapshot.');
        assertFalse(
            $bookingAvailabilityService->isAvailable(
                $organizationOneId,
                (int) $item->toArray()['id'],
                '2032-01-10',
                '2032-01-11'
            ),
            'Retry should not change booking availability rules.'
        );

        $auditStatement = $pdo->prepare(
            'SELECT context_json
             FROM audit_logs
             WHERE event_name = :event_name
                AND actor_user_id = :actor_user_id
                AND subject_type = :subject_type
                AND subject_id = :subject_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $auditStatement->execute([
            'event_name' => 'notification_retried',
            'actor_user_id' => $adminUserId,
            'subject_type' => 'notification',
            'subject_id' => $retryNotificationId,
        ]);
        $retryAuditContext = (string) $auditStatement->fetchColumn();
        assertTrue(str_contains($retryAuditContext, 'attempts_before'), 'Retry audit should include safe operational context.');
        assertFalse(str_contains($retryAuditContext, 'retry-' . $suffix . '@example.com'), 'Retry audit should not include full recipient.');
        assertFalse(str_contains($retryAuditContext, 'htmlBody'), 'Retry audit should not include mail body.');

        $detailAfterRetry = $router->dispatch(new Request(
            'GET',
            '/admin/notifications/' . rawurlencode($retryPublicId),
            [],
            [],
            $adminCookies,
            $server
        ));
        $detailAfterRetryContent = $detailAfterRetry->content();
        assertTrue(
            strpos($detailAfterRetryContent, '>1<') < strpos($detailAfterRetryContent, '>2<'),
            'Attempt history should render in chronological order.'
        );

        $maxBeforeAttempts = $notificationAttemptRepository->countForNotification((int) $maxNotification->toArray()['id']);
        $maxRetry = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/notifications/' . rawurlencode($maxPublicId) . '/retry',
            $adminToken
        ));
        assertSame(302, $maxRetry->statusCode(), 'Retry above max attempts should redirect safely.');
        assertSame(
            $maxBeforeAttempts,
            $notificationAttemptRepository->countForNotification((int) $maxNotification->toArray()['id']),
            'Fourth attempt should be denied.'
        );

        $sentBeforeAttempts = $notificationAttemptRepository->countForNotification((int) $sentNotification->toArray()['id']);
        $sentRetry = $router->dispatch(requestWithValidCsrfAndSession(
            'POST',
            '/admin/notifications/' . rawurlencode($sentPublicId) . '/retry',
            $adminToken
        ));
        assertSame(302, $sentRetry->statusCode(), 'Retry on sent notification should redirect safely.');
        assertSame(
            $sentBeforeAttempts,
            $notificationAttemptRepository->countForNotification((int) $sentNotification->toArray()['id']),
            'Sent notification should not get a new attempt.'
        );

        $failureRetryBooking = $failingBookingService->createRequest([
            'rental_item_id' => (int) $item->toArray()['id'],
            'start_date' => '2032-01-16',
            'end_date' => '2032-01-16',
            'customer_name' => 'Retry Failure Guest ' . $suffix,
            'customer_email' => 'retry-failure-' . $suffix . '@example.com',
            'customer_phone' => '070-700 00 16',
        ]);
        $failureRetryNotification = notificationForBooking((int) $failureRetryBooking->toArray()['id'], 'booking_created', 'customer');
        $failureRetryRequest = requestWithValidCsrfAndSession(
            'POST',
            '/admin/notifications/' . rawurlencode((string) ($failureRetryNotification->toArray()['public_id'] ?? '')) . '/retry',
            $adminToken
        );
        $failureRetryRequest->setRouteParams([
            'public_id' => (string) ($failureRetryNotification->toArray()['public_id'] ?? ''),
        ]);
        $failureRetryRequest->setAuthenticatedUserId($adminUserId);
        $failingRetryController = new AdminNotificationController(
            $notificationRepository,
            $notificationAttemptRepository,
            new NotificationDispatcher(
                $notificationRepository,
                $notificationAttemptRepository,
                $templateService,
                new AuditService(),
                $failingTransport
            ),
            $failingNotificationService,
            new AuditService()
        );
        $failureRetryResponse = $failingRetryController->retry($failureRetryRequest);
        assertSame(302, $failureRetryResponse->statusCode(), 'Simulated retry failure should redirect safely.');
        $failureRetryUpdated = $notificationRepository->findByPublicId(
            (string) ($failureRetryNotification->toArray()['public_id'] ?? '')
        );
        assertNotNull($failureRetryUpdated, 'Retry failure notification should remain visible.');
        assertSame(
            2,
            (int) ($failureRetryUpdated->toArray()['attempts_count'] ?? 0),
            'Simulated retry failure should register a new failed attempt.'
        );
        assertSame(0, count($failingTransport->capturedMessages()), 'Development failure transport should not send real mail.');

        $adminBookingList = $router->dispatch(new Request('GET', '/admin/bookings', [], [], $adminCookies, $server));
        assertSame(200, $adminBookingList->statusCode(), 'Admin booking management should continue to work.');

        $publicItemData = $item->toArray();
        $publicFormPath = '/items/'
            . rawurlencode((string) ($publicItemData['public_id'] ?? ''))
            . '/'
            . rawurlencode((string) ($publicItemData['slug'] ?? ''))
            . '/book';
        assertSame(200, $router->dispatch(new Request('GET', $publicFormPath))->statusCode(), 'Public booking flow should continue to work.');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

exit($runner->finish());
