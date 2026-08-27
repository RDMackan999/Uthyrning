<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Collection;
use App\Models\BookingItem;
use App\Models\RentalFulfillmentItem;

/**
 * Validates admin handover and return forms before fulfillment service calls.
 */
final class RentalFulfillmentFormRequest
{
    /**
     * @var array<string, string>
     */
    private const CONDITION_OPTIONS = [
        'good' => 'Bra',
        'acceptable' => 'Acceptabelt',
        'damaged' => 'Skadat',
    ];

    /**
     * @var array<string, string>
     */
    private const DEPOSIT_OPTIONS = [
        'not_required' => 'Krävs inte',
        'required' => 'Krävs',
        'received' => 'Mottagen',
        'returned' => 'Återbetald',
        'partially_retained' => 'Delvis innehållen',
        'retained' => 'Innehållen',
    ];

    /**
     * @return array<string, string>
     */
    public static function conditionOptions(): array
    {
        return self::CONDITION_OPTIONS;
    }

    /**
     * @return array<string, string>
     */
    public static function depositOptions(): array
    {
        return self::DEPOSIT_OPTIONS;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public function validateHandover(array $input, Collection $bookingItems): array
    {
        $data = [
            'actual_handover_at' => $this->dateTimeValue($input['actual_handover_at'] ?? null),
            'received_by_name' => $this->nullableString($input['received_by_name'] ?? null, 255),
            'handover_note' => $this->nullableString($input['handover_note'] ?? null, 2000),
            'terms_version_key' => $this->nullableString($input['terms_version_key'] ?? null, 100),
            'deposit_received_amount' => $this->nullableDecimal($input['deposit_received_amount'] ?? null),
            'deposit_status_key' => $this->depositStatus($input['deposit_status_key'] ?? 'not_required'),
            'items' => [],
        ];
        $errors = $this->commonErrors($data, 'actual_handover_at');

        foreach ($this->postedItems($input) as $bookingItemId => $itemInput) {
            if (!$this->collectionHasBookingItem($bookingItems, $bookingItemId)) {
                $errors['items'] = 'Ett objekt hör inte till bokningen.';
                continue;
            }

            $data['items'][] = [
                'booking_item_id' => $bookingItemId,
                'condition_key' => $this->conditionKey($itemInput['condition_key'] ?? null),
                'condition_note' => $this->nullableString($itemInput['condition_note'] ?? null, 1000),
                'meter_value' => $this->nullableDecimal($itemInput['meter_value'] ?? null),
            ];
        }

        foreach ($bookingItems as $bookingItem) {
            if (!$bookingItem instanceof BookingItem) {
                continue;
            }

            $bookingItemId = (int) ($bookingItem->toArray()['id'] ?? 0);

            if ($bookingItemId > 0 && !$this->dataContainsBookingItem($data['items'], $bookingItemId)) {
                $errors['items'] = 'Alla bokningsobjekt måste ha dokumenterat skick.';
            }
        }

        $this->validateItemPayload($data['items'], $errors);

        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public function validateReturn(array $input, Collection $fulfillmentItems): array
    {
        $data = [
            'actual_return_at' => $this->dateTimeValue($input['actual_return_at'] ?? null),
            'return_note' => $this->nullableString($input['return_note'] ?? null, 2000),
            'deposit_returned_amount' => $this->nullableDecimal($input['deposit_returned_amount'] ?? null),
            'deposit_retained_amount' => $this->nullableDecimal($input['deposit_retained_amount'] ?? null),
            'deposit_status_key' => $this->depositStatus($input['deposit_status_key'] ?? 'not_required'),
            'items' => [],
        ];
        $errors = $this->commonErrors($data, 'actual_return_at');

        foreach ($this->postedItems($input) as $bookingItemId => $itemInput) {
            if (!$this->collectionHasFulfillmentItem($fulfillmentItems, $bookingItemId)) {
                $errors['items'] = 'Ett objekt hör inte till uthyrningen.';
                continue;
            }

            $data['items'][] = [
                'booking_item_id' => $bookingItemId,
                'condition_key' => $this->conditionKey($itemInput['condition_key'] ?? null),
                'condition_note' => $this->nullableString($itemInput['condition_note'] ?? null, 1000),
                'has_return_deviation' => $this->checkboxValue($itemInput, 'has_return_deviation'),
                'damage_note' => $this->nullableString($itemInput['damage_note'] ?? null, 1000),
                'meter_value' => $this->nullableDecimal($itemInput['meter_value'] ?? null),
            ];
        }

        foreach ($fulfillmentItems as $fulfillmentItem) {
            if (!$fulfillmentItem instanceof RentalFulfillmentItem) {
                continue;
            }

            $bookingItemId = (int) ($fulfillmentItem->toArray()['booking_item_id'] ?? 0);

            if ($bookingItemId > 0 && !$this->dataContainsBookingItem($data['items'], $bookingItemId)) {
                $errors['items'] = 'Alla uthyrningsobjekt måste ha dokumenterat återlämningsskick.';
            }
        }

        $this->validateItemPayload($data['items'], $errors);

        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function commonErrors(array $data, string $dateTimeKey): array
    {
        $errors = [];

        if (!$this->isDateTime($data[$dateTimeKey] ?? null)) {
            $errors[$dateTimeKey] = 'Ange en giltig UTC-tid i formatet YYYY-MM-DD HH:MM:SS.';
        }

        if (!$this->isDepositStatus($data['deposit_status_key'] ?? null)) {
            $errors['deposit_status_key'] = 'Välj en giltig depositionsstatus.';
        }

        foreach ([
            'deposit_received_amount',
            'deposit_returned_amount',
            'deposit_retained_amount',
        ] as $moneyKey) {
            if (array_key_exists($moneyKey, $data) && !$this->isNullableDecimal($data[$moneyKey])) {
                $errors[$moneyKey] = 'Belopp måste vara noll eller högre.';
            }
        }

        return $errors;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, string> $errors
     */
    private function validateItemPayload(array $items, array &$errors): void
    {
        foreach ($items as $item) {
            if (!$this->isConditionKey($item['condition_key'] ?? null)) {
                $errors['items'] = 'Välj ett giltigt skick för varje objekt.';
            }

            if (!$this->isNullableDecimal($item['meter_value'] ?? null)) {
                $errors['items'] = 'Mätarvärde måste vara noll eller högre.';
            }
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<int, array<string, mixed>>
     */
    private function postedItems(array $input): array
    {
        $items = $input['items'] ?? [];

        if (!is_array($items)) {
            return [];
        }

        $posted = [];

        foreach ($items as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $bookingItemId = is_numeric($key) ? (int) $key : (int) ($value['booking_item_id'] ?? 0);

            if ($bookingItemId > 0) {
                $posted[$bookingItemId] = $value + ['booking_item_id' => $bookingItemId];
            }
        }

        return $posted;
    }

    private function collectionHasBookingItem(Collection $bookingItems, int $bookingItemId): bool
    {
        foreach ($bookingItems as $bookingItem) {
            if (!$bookingItem instanceof BookingItem) {
                continue;
            }

            if ((int) ($bookingItem->toArray()['id'] ?? 0) === $bookingItemId) {
                return true;
            }
        }

        return false;
    }

    private function collectionHasFulfillmentItem(Collection $fulfillmentItems, int $bookingItemId): bool
    {
        foreach ($fulfillmentItems as $fulfillmentItem) {
            if (!$fulfillmentItem instanceof RentalFulfillmentItem) {
                continue;
            }

            if ((int) ($fulfillmentItem->toArray()['booking_item_id'] ?? 0) === $bookingItemId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function dataContainsBookingItem(array $items, int $bookingItemId): bool
    {
        foreach ($items as $item) {
            if ((int) ($item['booking_item_id'] ?? 0) === $bookingItemId) {
                return true;
            }
        }

        return false;
    }

    private function dateTimeValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function isDateTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1;
    }

    private function conditionKey(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? strtolower(trim((string) $value)) : '';
    }

    private function isConditionKey(mixed $value): bool
    {
        return is_string($value) && array_key_exists($value, self::CONDITION_OPTIONS);
    }

    private function depositStatus(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? strtolower(trim((string) $value)) : 'not_required';
    }

    private function isDepositStatus(mixed $value): bool
    {
        return is_string($value) && array_key_exists($value, self::DEPOSIT_OPTIONS);
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return substr($text, 0, $maxLength);
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : (string) $value;
    }

    private function isNullableDecimal(mixed $value): bool
    {
        return $value === null || (is_numeric($value) && (float) $value >= 0);
    }

    /**
     * HTML checkboxes are false when absent from an item payload.
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
