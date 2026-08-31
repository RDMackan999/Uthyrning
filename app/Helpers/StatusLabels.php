<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Centralizes Swedish UI labels for internal status keys.
 */
final class StatusLabels
{
    /**
     * @var array<string, string>
     */
    private const BOOKING = [
        'request' => 'Förfrågan',
        'approved' => 'Godkänd',
        'rejected' => 'Avslagen',
        'cancelled' => 'Avbruten',
        'active' => 'Pågående',
        'completed' => 'Slutförd',
    ];

    /**
     * @var array<string, string>
     */
    private const CUSTOMER = [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'blocked' => 'Blockerad',
    ];

    /**
     * @var array<string, string>
     */
    private const NOTIFICATION = [
        'pending' => 'Väntar',
        'sent' => 'Skickad',
        'failed' => 'Misslyckad',
        'cancelled' => 'Avbruten',
    ];

    /**
     * @var array<string, string>
     */
    private const NOTIFICATION_EVENT = [
        'booking_created' => 'Ny bokningsförfrågan',
        'booking_approved' => 'Bokning godkänd',
        'booking_rejected' => 'Bokning avslagen',
        'booking_cancelled' => 'Bokning avbruten',
    ];

    public static function booking(mixed $statusKey): string
    {
        return self::label(self::BOOKING, $statusKey);
    }

    public static function customer(mixed $statusKey): string
    {
        return self::label(self::CUSTOMER, $statusKey);
    }

    public static function notification(mixed $statusKey): string
    {
        return self::label(self::NOTIFICATION, $statusKey);
    }

    public static function notificationEvent(mixed $eventKey): string
    {
        return self::label(self::NOTIFICATION_EVENT, $eventKey);
    }

    /**
     * @return array<string, string>
     */
    public static function bookingOptions(): array
    {
        return self::BOOKING;
    }

    /**
     * @return array<string, string>
     */
    public static function customerOptions(): array
    {
        return self::CUSTOMER;
    }

    /**
     * @param array<string, string> $labels
     */
    private static function label(array $labels, mixed $key): string
    {
        $statusKey = is_string($key) || is_numeric($key) ? trim((string) $key) : '';

        return $labels[$statusKey] ?? $statusKey;
    }
}
