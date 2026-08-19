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

    /**
     * Build public-safe availability data grouped by month.
     *
     * @return array<string, mixed>
     */
    public function publicMonths(
        int $organizationId,
        int $rentalItemId,
        ?string $selectedStartDate = null,
        ?string $selectedEndDate = null
    ): array {
        $today = new DateTimeImmutable('today');
        $maxDate = $this->maxPublicDate();
        $selectedStart = $this->optionalDate($selectedStartDate);
        $activeMonth = $selectedStart !== null && $selectedStart >= $today && $selectedStart <= $maxDate
            ? $selectedStart->format('Y-m')
            : $today->format('Y-m');
        $range = $this->publicRange(
            $organizationId,
            $rentalItemId,
            $today->modify('first day of this month')->format('Y-m-d'),
            $maxDate->format('Y-m-d'),
            $selectedStartDate,
            $selectedEndDate
        );
        $months = [];

        foreach ($range['days'] as $day) {
            $date = (string) ($day['date'] ?? '');
            $monthKey = substr($date, 0, 7);

            if (!isset($months[$monthKey])) {
                $monthDate = $this->date($monthKey . '-01', 'month');
                $months[$monthKey] = [
                    'key' => $monthKey,
                    'label' => $this->monthLabel($monthDate),
                    'leading_empty_days' => (int) $monthDate->format('N') - 1,
                    'is_active' => $monthKey === $activeMonth,
                    'days' => [],
                ];
            }

            $months[$monthKey]['days'][] = $day;
        }

        if ($months === []) {
            return $range + [
                'months' => [],
                'weekdays' => $this->weekdays(),
                'min_date' => $today->format('Y-m-d'),
                'max_date' => $maxDate->format('Y-m-d'),
            ];
        }

        $monthValues = array_values($months);
        $activeIndex = 0;

        foreach ($monthValues as $index => $month) {
            if (($month['key'] ?? '') === $activeMonth) {
                $activeIndex = $index;
                break;
            }
        }

        return $range + [
            'months' => $monthValues,
            'weekdays' => $this->weekdays(),
            'active_month_index' => $activeIndex,
            'min_date' => $today->format('Y-m-d'),
            'max_date' => $maxDate->format('Y-m-d'),
        ];
    }

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
        $today = new DateTimeImmutable('today');

        if ($from > $to) {
            throw new BookingException('Calendar from date must be before or equal to to date.');
        }

        $maxDate = $this->maxPublicDate();
        if ($to > $maxDate) {
            throw new BookingException('Public availability range is too large.');
        }

        $selectedStart = $this->optionalDate($selectedStartDate);
        $selectedEnd = $this->optionalDate($selectedEndDate);
        $days = [];
        $period = new DatePeriod($from, new DateInterval('P1D'), $to->modify('+1 day'));

        foreach ($period as $day) {
            $date = $day->format('Y-m-d');
            $isPast = $day < $today;
            $isAvailable = !$isPast
                && $this->availabilityService->isAvailable($organizationId, $rentalItemId, $date, $date);
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
                'is_today' => $date === $today->format('Y-m-d'),
                'is_past' => $isPast,
                'is_selected_start' => $isSelectedStart,
                'is_selected_end' => $isSelectedEnd,
                'is_selected' => $isSelectedStart || $isSelectedEnd || $isInSelectedRange,
                'aria_label' => $this->dayAriaLabel($date, $isAvailable, $date === $today->format('Y-m-d')),
            ];
        }

        return [
            'from_date' => $from->format('Y-m-d'),
            'to_date' => $to->format('Y-m-d'),
            'min_date' => $today->format('Y-m-d'),
            'max_date' => $maxDate->format('Y-m-d'),
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

    private function maxPublicDate(): DateTimeImmutable
    {
        return (new DateTimeImmutable('today'))->modify('+' . self::MAX_PUBLIC_MONTHS . ' months');
    }

    /**
     * @return list<string>
     */
    private function weekdays(): array
    {
        return ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
    }

    private function monthLabel(DateTimeImmutable $date): string
    {
        $names = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Mars',
            '04' => 'April',
            '05' => 'Maj',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Augusti',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'December',
        ];

        return ($names[$date->format('m')] ?? $date->format('F')) . ' ' . $date->format('Y');
    }

    private function dayAriaLabel(string $date, bool $isAvailable, bool $isToday): string
    {
        $parts = [$date];

        if ($isToday) {
            $parts[] = 'idag';
        }

        $parts[] = $isAvailable ? 'ledigt' : 'ej tillgängligt';

        return implode(', ', $parts);
    }
}
