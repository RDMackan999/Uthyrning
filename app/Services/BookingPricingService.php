<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BookingException;
use App\Models\RentalItem;
use App\Repositories\ItemRateRepository;
use DateTimeImmutable;

/**
 * Calculates deterministic Version 1 booking price snapshots.
 */
final class BookingPricingService
{
    public function __construct(
        private readonly ItemRateRepository $itemRateRepository = new ItemRateRepository()
    ) {
    }

    /**
     * Calculate a daily-rate snapshot for one rental item and inclusive date range.
     *
     * @return array{
     *     rate_type: string,
     *     unit_price: string,
     *     currency: string,
     *     quantity: int,
     *     number_of_units: int,
     *     subtotal_amount: string,
     *     deposit_amount: ?string
     * }
     */
    public function calculateDailySnapshot(
        int $organizationId,
        RentalItem $rentalItem,
        string $startDate,
        string $endDate
    ): array {
        $itemData = $rentalItem->toArray();
        $rentalItemId = (int) ($itemData['id'] ?? 0);

        if ($rentalItemId <= 0 || (int) ($itemData['organization_id'] ?? 0) !== $organizationId) {
            throw new BookingException('Rental item is not available for this organization.');
        }

        $numberOfUnits = $this->inclusiveDayCount($startDate, $endDate);
        $rate = $this->itemRateRepository->findActiveDailyForItem($organizationId, $rentalItemId);
        $rateData = $rate->toArray();
        $unitPrice = $this->decimal($rateData['amount'] ?? null);
        $subtotalAmount = number_format((float) $unitPrice * $numberOfUnits, 2, '.', '');

        return [
            'rate_type' => 'daily',
            'unit_price' => $unitPrice,
            'currency' => strtoupper((string) ($rateData['currency'] ?? 'SEK')) ?: 'SEK',
            'quantity' => 1,
            'number_of_units' => $numberOfUnits,
            'subtotal_amount' => $subtotalAmount,
            'deposit_amount' => $this->nullableDecimal($itemData['deposit_amount'] ?? null),
        ];
    }

    /**
     * Count charged days using inclusive DATE boundaries.
     */
    public function inclusiveDayCount(string $startDate, string $endDate): int
    {
        $start = $this->date($startDate, 'start_date');
        $end = $this->date($endDate, 'end_date');

        if ($start > $end) {
            throw new BookingException('Booking start_date must be before or equal to end_date.');
        }

        return (int) $start->diff($end)->days + 1;
    }

    private function date(string $date, string $field): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));

        if ($parsed === false || $parsed->format('Y-m-d') !== trim($date)) {
            throw new BookingException($field . ' must be a YYYY-MM-DD date.');
        }

        return $parsed;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->decimal($value);
    }

    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '' || !is_numeric($value) || (float) $value < 0) {
            throw new BookingException('Decimal value must be zero or greater.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
