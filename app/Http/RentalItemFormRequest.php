<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\ModelException;
use App\Models\RentalItem;
use App\Repositories\CategoryRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\RentalItemRepository;

/**
 * Validates rental item admin form input before repository persistence.
 */
final class RentalItemFormRequest
{
    public function __construct(
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
        private readonly CategoryRepository $categoryRepository = new CategoryRepository(),
        private readonly OrganizationRepository $organizationRepository = new OrganizationRepository(),
    ) {
    }

    /**
     * Validate create or update data for the current foundation form.
     *
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(array $input, ?RentalItem $current = null): array
    {
        $organizationId = $this->intValue($input['organization_id'] ?? null);
        $primaryCategoryId = $this->intValue($input['primary_category_id'] ?? null);
        $name = $this->stringValue($input['name'] ?? '');
        $slug = $this->normalizeSlug($this->stringValue($input['slug'] ?? ''));

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Namn är obligatoriskt.';
        }

        if ($slug === '') {
            $errors['slug'] = 'Slug är obligatorisk.';
        }

        if ($organizationId === null) {
            $errors['organization_id'] = 'Organisation är obligatorisk.';
        } elseif (!$this->organizationExists($organizationId)) {
            $errors['organization_id'] = 'Vald organisation är inte giltig.';
        }

        if ($primaryCategoryId === null) {
            $errors['primary_category_id'] = 'Primär kategori är obligatorisk.';
        }

        if ($organizationId !== null && $primaryCategoryId !== null && !$this->categoryIsAvailable($primaryCategoryId, $organizationId)) {
            $errors['primary_category_id'] = 'Vald kategori är inte giltig för organisationen.';
        }

        if ($organizationId !== null && $slug !== '' && $this->slugIsUsed($organizationId, $slug, $current)) {
            $errors['slug'] = 'Slug används redan inom organisationen.';
        }

        return [
            'data' => [
                'organization_id' => $organizationId,
                'primary_category_id' => $primaryCategoryId,
                'slug' => $slug,
                'name' => $name,
                'short_name' => $this->nullableString($input['short_name'] ?? null),
                'description' => $this->nullableString($input['description'] ?? null),
                'is_active' => $this->checkboxValue($input, 'is_active'),
                'is_rentable' => $this->checkboxValue($input, 'is_rentable'),
            ],
            'errors' => $errors,
        ];
    }

    private function organizationExists(int $organizationId): bool
    {
        try {
            $this->organizationRepository->findById($organizationId);
        } catch (ModelException) {
            return false;
        }

        return true;
    }

    private function categoryIsAvailable(int $categoryId, int $organizationId): bool
    {
        return $this->categoryRepository->findAvailableForOrganizationById($categoryId, $organizationId) !== null;
    }

    private function slugIsUsed(int $organizationId, string $slug, ?RentalItem $current): bool
    {
        $existing = $this->rentalItemRepository->findBySlug($organizationId, $slug);

        if ($existing === null) {
            return false;
        }

        if ($current === null) {
            return true;
        }

        return (string) ($existing->toArray()['id'] ?? '') !== (string) ($current->toArray()['id'] ?? '');
    }

    private function normalizeSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $text = $this->stringValue($value);

        return $text === '' ? null : $text;
    }

    private function intValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * HTML checkboxes are false when absent from the POST payload.
     *
     * @param array<string, mixed> $input
     */
    private function checkboxValue(array $input, string $key): bool
    {
        if (!array_key_exists($key, $input)) {
            return false;
        }

        return filter_var($input[$key], FILTER_VALIDATE_BOOLEAN);
    }
}
