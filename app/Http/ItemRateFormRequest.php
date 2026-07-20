<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\ItemRate;
use App\Repositories\ItemRateRepository;

/**
 * Validates rental item rate admin form input before persistence.
 */
final class ItemRateFormRequest
{
    /**
     * @var array<string, string>
     */
    private const RATE_TYPES = [
        'daily' => 'Dagspris',
        'weekend' => 'Helgpris',
        'weekly' => 'Veckopris',
        'monthly' => 'Månadspris',
    ];

    public function __construct(
        private readonly ItemRateRepository $itemRateRepository = new ItemRateRepository(),
    ) {
    }

    /**
     * Return Version 1 rate type labels.
     *
     * @return array<string, string>
     */
    public static function rateTypes(): array
    {
        return self::RATE_TYPES;
    }

    /**
     * Validate create or update data for the current foundation form.
     *
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(
        array $input,
        int $organizationId,
        int $rentalItemId,
        ?ItemRate $current = null
    ): array {
        $rateType = strtolower($this->stringValue($input['rate_type'] ?? ''));
        $amount = $this->stringValue($input['amount'] ?? '');
        $currency = strtoupper($this->stringValue($input['currency'] ?? 'SEK'));
        $isActive = $this->checkboxValue($input, 'is_active');
        $errors = [];

        if ($rateType === '') {
            $errors['rate_type'] = 'Pristyp är obligatorisk.';
        } elseif (!array_key_exists($rateType, self::RATE_TYPES)) {
            $errors['rate_type'] = 'Pristypen stöds inte i Version 1.';
        }

        if ($amount === '') {
            $errors['amount'] = 'Pris är obligatoriskt.';
        } elseif (!is_numeric($amount) || (float) $amount <= 0) {
            $errors['amount'] = 'Pris måste vara större än 0.';
        }

        if ($currency === '') {
            $errors['currency'] = 'Valuta är obligatorisk.';
        } elseif ($currency !== 'SEK') {
            $errors['currency'] = 'Version 1 stödjer endast SEK.';
        }

        $currentId = $current === null ? null : (int) ($current->toArray()['id'] ?? 0);
        if (
            $isActive
            && $rateType !== ''
            && array_key_exists($rateType, self::RATE_TYPES)
            && $this->itemRateRepository->activeRateTypeExists($organizationId, $rentalItemId, $rateType, $currentId)
        ) {
            $errors['rate_type'] = 'Det finns redan ett aktivt pris för vald pristyp.';
        }

        return [
            'data' => [
                'rate_type' => $rateType,
                'amount' => is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : $amount,
                'currency' => $currency === '' ? 'SEK' : $currency,
                'is_active' => $isActive,
            ],
            'errors' => $errors,
        ];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
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
