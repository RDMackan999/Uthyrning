<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BookingException;
use App\Repositories\BookingItemRepository;
use App\Repositories\RentalItemRepository;
use DateTimeImmutable;

/**
 * Central availability checks for booking requests.
 */
final class BookingAvailabilityService
{
    public function __construct(
        private readonly BookingItemRepository $bookingItemRepository = new BookingItemRepository(),
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

        return !$this->bookingItemRepository->hasBlockingOverlap(
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
