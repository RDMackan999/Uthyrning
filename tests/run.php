<?php

declare(strict_types=1);

use App\Core\Collection;
use App\Core\Config;
use App\Core\Database;
use App\Core\MigrationRunner;
use App\Core\ModelException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\SeederRunner;
use App\Core\View;
use App\Http\RentalItemFormRequest;
use App\Models\Category;
use App\Models\ItemRate;
use App\Models\RentalItem;
use App\Repositories\CategoryRepository;
use App\Repositories\ItemRateRepository;
use App\Repositories\RentalItemRepository;
use App\Services\RentalItemPublicationService;

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

$runner = new TestRunner();
$migrationRunner = new MigrationRunner($basePath);
$seederRunner = new SeederRunner($basePath);
$repository = new CategoryRepository();
$rentalItemRepository = new RentalItemRepository();
$itemRateRepository = new ItemRateRepository();

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

exit($runner->finish());
