<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\Customer;
use App\Repositories\CustomerRepository;

/**
 * Validates customer administration form input before persistence.
 */
final class CustomerFormRequest
{
    /**
     * @var list<string>
     */
    private const TYPE_KEYS = ['private', 'company'];

    /**
     * @var list<string>
     */
    private const STATUS_KEYS = ['active', 'inactive', 'blocked'];

    public function __construct(private readonly CustomerRepository $customerRepository = new CustomerRepository())
    {
    }

    /**
     * Validate editable customer fields.
     *
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(array $input, Customer $current): array
    {
        $currentData = $current->toArray();
        $organizationId = (int) ($currentData['organization_id'] ?? 0);
        $name = $this->stringValue($input['name'] ?? '');
        $email = $this->nullableEmail($input['email'] ?? null);
        $phone = $this->nullableString($input['phone'] ?? null, 50);
        $customerTypeKey = $this->customerType($input['customer_type_key'] ?? null);
        $companyId = $customerTypeKey === 'company' ? $this->nullableInt($input['company_id'] ?? null) : null;
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Namn är obligatoriskt.';
        } elseif (strlen($name) > 255) {
            $errors['name'] = 'Namn är för långt.';
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-postadressen är inte giltig.';
        }

        if (!in_array($customerTypeKey, self::TYPE_KEYS, true)) {
            $errors['customer_type_key'] = 'Kundtypen är inte giltig.';
        }

        if ($companyId !== null && !$this->customerRepository->companyBelongsToOrganization($companyId, $organizationId)) {
            $errors['company_id'] = 'Valt företag tillhör inte kundens organisation.';
        }

        return [
            'data' => [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'customer_type_key' => $customerTypeKey,
                'company_id' => $companyId,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * Validate lifecycle status changes.
     *
     * @param array<string, mixed> $input
     * @return array{status_key: ?string, errors: array<string, string>}
     */
    public function validateStatus(array $input): array
    {
        $statusKey = strtolower($this->stringValue($input['status_key'] ?? ''));

        if (!in_array($statusKey, self::STATUS_KEYS, true)) {
            return [
                'status_key' => null,
                'errors' => ['status_key' => 'Statusen är inte giltig.'],
            ];
        }

        return [
            'status_key' => $statusKey,
            'errors' => [],
        ];
    }

    private function customerType(mixed $value): string
    {
        $type = strtolower($this->stringValue($value));

        return $type === '' ? 'private' : $type;
    }

    private function nullableEmail(mixed $value): ?string
    {
        $email = $this->nullableString($value, 255);

        return $email === null ? null : strtolower($email);
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $text = $this->stringValue($value);

        if ($text === '') {
            return null;
        }

        return strlen($text) > $maxLength ? substr($text, 0, $maxLength) : $text;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
