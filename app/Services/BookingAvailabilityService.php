<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BookingException;
use App\Repositories\BookingItemRepository;
use App\Repositories\ItemAvailabilityBlockRepository;
use App\Repositories\RentalItemRepository;
use DateTimeImmutable;

/**
 * Central availability checks for booking requests.
 */
final class BookingAvailabilityService
{
    public function __construct(
        private readonly BookingItemRepository $bookingItemRepository = new BookingItemRepository(),
        private readonly ItemAvailabilityBlockRepository $availabilityBlockRepository = new ItemAvailabilityBlockRepository(),
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository()
    ) {
    }

    /**
     * Check whether one item is bookable for an inclusive date interval.
     */
    public function isAvailable(
        int $organizationId,
        int $rentalItemId,
        string $startDate,
        string $endDate,
        ?int $excludeBookingId = null
    ): bool {
        $this->assertValidInterval($startDate, $endDate);
        $this->rentalItemRepository->findBookableForBooking($organizationId, $rentalItemId);

        return !$this->hasBlockingOverlap(
            $organizationId,
            $rentalItemId,
            $startDate,
            $endDate,
            $excludeBookingId
        );
    }

    /**
     * Require availability or throw a booking-domain exception.
     */
    public function assertAvailable(
        int $organizationId,
        int $rentalItemId,
        string $startDate,
        string $endDate,
        ?int $excludeBookingId = null
    ): void {
        if (!$this->isAvailable($organizationId, $rentalItemId, $startDate, $endDate, $excludeBookingId)) {
            throw new BookingException('Rental item is not available for the selected dates.');
        }
    }

    /**
     * Check only date blockers for callers that already validated item scope.
     */
    public function hasBlockingBookings(
        int $organizationId,
        int $rentalItemId,
        string $startDate,
        string $endDate,
        ?int $excludeBookingId = null
    ): bool {
        $this->assertValidInterval($startDate, $endDate);

        return $this->bookingItemRepository->hasBlockingOverlap(
            $organizationId,
            $rentalItemId,
            $startDate,
            $endDate,
            $excludeBookingId
        );
    }

    /**
     * Check whether booking statuses or manual blocks reserve this interval.
     */
    public function hasBlockingOverlap(
        int $organizationId,
        int $rentalItemId,
        string $startDate,
        string $endDate,
        ?int $excludeBookingId = null
    ): bool {
        $this->assertValidInterval($startDate, $endDate);

        if ($this->bookingItemRepository->hasBlockingOverlap(
            $organizationId,
            $rentalItemId,
            $startDate,
            $endDate,
            $excludeBookingId
        )) {
            return true;
        }

        return $this->availabilityBlockRepository->hasBlockingOverlap(
            $organizationId,
            $rentalItemId,
            $startDate,
            $endDate
        );
    }

    /**
     * Check only manual availability blocks for callers that already validated item scope.
     */
    public function hasBlockingManualBlocks(
        int $organizationId,
        int $rentalItemId,
        string $startDate,
        string $endDate
    ): bool {
        $this->assertValidInterval($startDate, $endDate);

        return $this->availabilityBlockRepository->hasBlockingOverlap(
            $organizationId,
            $rentalItemId,
            $startDate,
            $endDate
        );
    }

    private function assertValidInterval(string $startDate, string $endDate): void
    {
        $start = $this->date($startDate, 'start_date');
        $end = $this->date($endDate, 'end_date');

        if ($start > $end) {
            throw new BookingException('Booking start_date must be before or equal to end_date.');
        }
    }

    private function date(string $date, string $field): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));

        if ($parsed === false || $parsed->format('Y-m-d') !== trim($date)) {
            throw new BookingException($field . ' must be a YYYY-MM-DD date.');
        }

        return $parsed;
    }
}
