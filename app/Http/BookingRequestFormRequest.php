<?php

declare(strict_types=1);

namespace App\Http;

use DateTimeImmutable;

/**
 * Validates public booking request form input before service orchestration.
 */
final class BookingRequestFormRequest
{
    private const MAX_NAME_LENGTH = 255;
    private const MAX_EMAIL_LENGTH = 255;
    private const MAX_PHONE_LENGTH = 50;
    private const MAX_COMPANY_LENGTH = 255;
    private const MAX_COMMENT_LENGTH = 1000;
    private const MAX_BOOKING_MONTHS = 6;

    /**
     * Validate the public booking request form.
     *
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(array $input, string $publicId, string $slug): array
    {
        $publicId = $this->stringValue($publicId);
        $slug = $this->stringValue($slug);
        $startDate = $this->stringValue($input['start_date'] ?? '');
        $endDate = $this->stringValue($input['end_date'] ?? '');
        $customerName = $this->stringValue($input['customer_name'] ?? '');
        $customerEmail = strtolower($this->stringValue($input['customer_email'] ?? ''));
        $customerPhone = $this->stringValue($input['customer_phone'] ?? '');
        $companyName = $this->nullableString($input['company_name'] ?? null, self::MAX_COMPANY_LENGTH);
        $customerComment = $this->nullableString($input['customer_comment'] ?? null, self::MAX_COMMENT_LENGTH);
        $errors = [];

        if ($publicId === '' || strlen($publicId) > 80 || !preg_match('/^[A-Za-z0-9_-]+$/', $publicId)) {
            $errors['public_id'] = 'Objektlänken är inte giltig.';
        }

        if ($slug === '' || strlen($slug) > 160 || !preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
            $errors['slug'] = 'Objektlänken är inte giltig.';
        }

        if ($startDate === '') {
            $errors['start_date'] = 'Startdatum är obligatoriskt.';
        } elseif (!$this->isDate($startDate)) {
            $errors['start_date'] = 'Startdatum måste vara ett giltigt datum.';
        }

        if ($endDate === '') {
            $errors['end_date'] = 'Slutdatum är obligatoriskt.';
        } elseif (!$this->isDate($endDate)) {
            $errors['end_date'] = 'Slutdatum måste vara ett giltigt datum.';
        }

        if ($startDate !== '' && $endDate !== '' && $this->isDate($startDate) && $this->isDate($endDate)) {
            $start = $this->date($startDate);
            $end = $this->date($endDate);
            $today = new DateTimeImmutable('today');
            $maxDate = $today->modify('+' . self::MAX_BOOKING_MONTHS . ' months');

            if ($start > $end) {
                $errors['end_date'] = 'Slutdatum måste vara samma dag eller efter startdatum.';
            }

            if ($start < $today) {
                $errors['start_date'] = 'Startdatum kan inte vara tidigare än idag.';
            }

            if ($end < $today) {
                $errors['end_date'] = 'Slutdatum kan inte vara tidigare än idag.';
            } elseif ($end > $maxDate) {
                $errors['end_date'] = 'Slutdatum ligger utanför bokningsperioden.';
            }
        }

        if ($customerName === '') {
            $errors['customer_name'] = 'Namn är obligatoriskt.';
        } elseif (strlen($customerName) > self::MAX_NAME_LENGTH) {
            $errors['customer_name'] = 'Namn är för långt.';
        }

        if ($customerEmail === '') {
            $errors['customer_email'] = 'E-post är obligatorisk.';
        } elseif (strlen($customerEmail) > self::MAX_EMAIL_LENGTH || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = 'E-postadressen är inte giltig.';
        }

        if ($customerPhone === '') {
            $errors['customer_phone'] = 'Telefon är obligatoriskt.';
        } elseif (strlen($customerPhone) > self::MAX_PHONE_LENGTH || !$this->isValidPhone($customerPhone)) {
            $errors['customer_phone'] = 'Telefonnumret är inte giltigt.';
        }

        return [
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'company_name' => $companyName,
                'customer_comment' => $customerComment,
            ],
            'errors' => $errors,
        ];
    }

    private function isDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function date(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false) {
            return new DateTimeImmutable('today');
        }

        return $parsed;
    }

    private function isValidPhone(string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) >= 6;
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
}
