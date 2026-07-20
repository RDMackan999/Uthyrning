<?php

declare(strict_types=1);

use App\Core\Collection;
use App\Core\Config;
use App\Core\Database;
use App\Core\MigrationRunner;
use App\Core\ModelException;
use App\Core\SeederRunner;
use App\Models\Category;
use App\Repositories\CategoryRepository;

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

$runner = new TestRunner();
$migrationRunner = new MigrationRunner($basePath);
$seederRunner = new SeederRunner($basePath);
$repository = new CategoryRepository();

$runner->test('migrations create category tables', static function () use ($migrationRunner): void {
    $migrationRunner->run();

    assertTrue(tableExists('item_categories'), 'item_categories table should exist.');
    assertTrue(tableExists('item_category_relations'), 'item_category_relations table should exist.');
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
    assertFalse(foreignKeyExists('item_category_relations', 'rental_items'), 'rental_items FK should wait until rental_items exists.');
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

exit($runner->finish());
