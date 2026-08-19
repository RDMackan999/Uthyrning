<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BookingException;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

/**
 * Builds public-safe, server-renderable availability calendar data.
 */
final class AvailabilityCalendarService
{
    private const MAX_PUBLIC_MONTHS = 6;

    public function __construct(
        private readonly BookingAvailabilityService $availabilityService = new BookingAvailabilityService()
    ) {
    }

    /**
     * Build one public-safe month for the booking form.
     *
     * @return array<string, mixed>
     */
    public function publicMonth(
        int $organizationId,
        int $rentalItemId,
        ?string $selectedStartDate = null,
        ?string $selectedEndDate = null
    ): array {
        $today = new DateTimeImmutable('today');
        $maxDate = $today->modify('+' . self::MAX_PUBLIC_MONTHS . ' months');
        $selectedStart = $this->optionalDate($selectedStartDate);
        $selectedEnd = $this->optionalDate($selectedEndDate);
        $monthAnchor = $selectedStart !== null && $selectedStart >= $today && $selectedStart <= $maxDate
            ? $selectedStart
            : $today;

        $from = $monthAnchor->modify('first day of this month');
        $to = $monthAnchor->modify('last day of this month');

        if ($to > $maxDate) {
            $to = $maxDate;
        }

        return $this->publicRange(
            $organizationId,
            $rentalItemId,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $selectedStart?->format('Y-m-d'),
            $selectedEnd?->format('Y-m-d')
        );
    }

    /**
     * Build public-safe day states for an inclusive date range.
     *
     * @return array<string, mixed>
     */
    public function publicRange(
        int $organizationId,
        int $rentalItemId,
        string $fromDate,
        string $toDate,
        ?string $selectedStartDate = null,
        ?string $selectedEndDate = null
    ): array {
        $from = $this->date($fromDate, 'from');
        $to = $this->date($toDate, 'to');

        if ($from > $to) {
            throw new BookingException('Calendar from date must be before or equal to to date.');
        }

        $maxDate = (new DateTimeImmutable('today'))->modify('+' . self::MAX_PUBLIC_MONTHS . ' months');
        if ($to > $maxDate) {
            throw new BookingException('Public availability range is too large.');
        }

        $selectedStart = $this->optionalDate($selectedStartDate);
        $selectedEnd = $this->optionalDate($selectedEndDate);
        $days = [];
        $period = new DatePeriod($from, new DateInterval('P1D'), $to->modify('+1 day'));

        foreach ($period as $day) {
            $date = $day->format('Y-m-d');
            $isAvailable = $this->availabilityService->isAvailable($organizationId, $rentalItemId, $date, $date);
            $isSelectedStart = $selectedStart !== null && $date === $selectedStart->format('Y-m-d');
            $isSelectedEnd = $selectedEnd !== null && $date === $selectedEnd->format('Y-m-d');
            $isInSelectedRange = $selectedStart !== null
                && $selectedEnd !== null
                && $day >= $selectedStart
                && $day <= $selectedEnd;

            $days[] = [
                'date' => $date,
                'day_label' => $day->format('j'),
                'state' => $isAvailable ? 'available' : 'unavailable',
                'is_available' => $isAvailable,
                'is_selected_start' => $isSelectedStart,
                'is_selected_end' => $isSelectedEnd,
                'is_selected' => $isSelectedStart || $isSelectedEnd || $isInSelectedRange,
                'aria_label' => $date . ' ' . ($isAvailable ? 'ledigt' : 'ej tillgängligt'),
            ];
        }

        return [
            'from_date' => $from->format('Y-m-d'),
            'to_date' => $to->format('Y-m-d'),
            'selected_start_date' => $selectedStart?->format('Y-m-d'),
            'selected_end_date' => $selectedEnd?->format('Y-m-d'),
            'days' => $days,
        ];
    }

    private function optionalDate(?string $date): ?DateTimeImmutable
    {
        $normalized = trim((string) $date);

        if ($normalized === '') {
            return null;
        }

        try {
            return $this->date($normalized, 'date');
        } catch (BookingException) {
            return null;
        }
    }

    private function date(string $date, string $field): DateTimeImmutable
    {
        $normalized = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);

        if ($parsed === false || $parsed->format('Y-m-d') !== $normalized) {
            throw new BookingException($field . ' must be a YYYY-MM-DD date.');
        }

        return $parsed;
    }
}
