<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BookingException;
use App\Core\Database;
use App\Core\Logger;
use App\Models\Booking;
use App\Repositories\BookingItemRepository;
use App\Repositories\BookingRepository;
use App\Repositories\RentalItemRepository;
use Throwable;

/**
 * Orchestrates Version 1 booking request creation.
 */
final class BookingService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository = new BookingRepository(),
        private readonly BookingItemRepository $bookingItemRepository = new BookingItemRepository(),
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
        private readonly BookingAvailabilityService $availabilityService = new BookingAvailabilityService(),
        private readonly BookingPricingService $pricingService = new BookingPricingService(),
        private readonly AuditService $auditService = new AuditService(),
        private readonly NotificationService $notificationService = new NotificationService()
    ) {
    }

    /**
     * Create a guest/customer booking request with immutable snapshots.
     *
     * @param array<string, mixed> $data
     */
    public function createRequest(array $data): Booking
    {
        $rentalItemId = $this->requiredInt($data['rental_item_id'] ?? null, 'rental_item_id');
        $startDate = $this->requiredString($data['start_date'] ?? null, 'start_date');
        $endDate = $this->requiredString($data['end_date'] ?? null, 'end_date');
        $customerName = $this->requiredString($data['customer_name'] ?? null, 'customer_name');
        $customerEmail = $this->normalizeEmail($this->requiredString($data['customer_email'] ?? null, 'customer_email'));
        $customerPhone = $this->requiredString($data['customer_phone'] ?? null, 'customer_phone');
        $actorUserId = $this->nullableInt($data['changed_by_user_id'] ?? null);

        $item = $this->rentalItemRepository->findById($rentalItemId);
        $itemData = $item->toArray();
        $organizationId = (int) ($itemData['organization_id'] ?? 0);

        if ($organizationId <= 0) {
            throw new BookingException('Rental item organization is missing.');
        }

        $requestedOrganizationId = $this->nullableInt($data['organization_id'] ?? null);
        if ($requestedOrganizationId !== null && $requestedOrganizationId !== $organizationId) {
            throw new BookingException('Rental item is not available for this organization.');
        }

        $this->validateCustomerAndCompanyScope($data, $organizationId);
        $item = $this->rentalItemRepository->findBookableForBooking($organizationId, $rentalItemId);

        $pdo = Database::pdo();
        $startedTransaction = !$pdo->inTransaction();

        try {
            if ($startedTransaction) {
                $pdo->beginTransaction();
            }

            $this->availabilityService->assertAvailable($organizationId, $rentalItemId, $startDate, $endDate);
            $priceSnapshot = $this->pricingService->calculateDailySnapshot($organizationId, $item, $startDate, $endDate);

            $booking = $this->bookingRepository->create([
                'organization_id' => $organizationId,
                'customer_id' => $this->nullableInt($data['customer_id'] ?? null),
                'company_id' => $this->nullableInt($data['company_id'] ?? null),
                'status_key' => 'request',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'customer_comment' => $this->nullableString($data['customer_comment'] ?? null),
                'internal_note' => $this->nullableString($data['internal_note'] ?? null),
                'currency' => $priceSnapshot['currency'],
                'total_units' => 0,
                'subtotal_amount' => '0.00',
                'deposit_amount' => null,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'company_name' => $this->nullableString($data['company_name'] ?? null),
                'changed_by_user_id' => $actorUserId,
            ]);
            $bookingData = $booking->toArray();
            $bookingId = (int) ($bookingData['id'] ?? 0);

            if ($bookingId <= 0) {
                throw new BookingException('Booking could not be created.');
            }

            $this->bookingItemRepository->create([
                'organization_id' => $organizationId,
                'booking_id' => $bookingId,
                'rental_item_id' => $rentalItemId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rate_type' => $priceSnapshot['rate_type'],
                'unit_price' => $priceSnapshot['unit_price'],
                'currency' => $priceSnapshot['currency'],
                'quantity' => $priceSnapshot['quantity'],
                'number_of_units' => $priceSnapshot['number_of_units'],
                'subtotal_amount' => $priceSnapshot['subtotal_amount'],
                'deposit_amount' => $priceSnapshot['deposit_amount'],
            ]);
            $booking = $this->bookingRepository->findById($bookingId, $organizationId);
            $this->auditService->record(
                'booking_created',
                $actorUserId,
                'booking',
                $bookingId,
                null,
                null,
                [
                    'organization_id' => $organizationId,
                    'rental_item_id' => $rentalItemId,
                    'status_key' => 'request',
                ]
            );

            if ($startedTransaction) {
                $pdo->commit();
            }

            $this->notifyBookingCreated($booking);

            return $booking;
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function notifyBookingCreated(Booking $booking): void
    {
        try {
            $this->notificationService->notifyBookingCreated($booking);
        } catch (Throwable) {
            $this->logNotificationFailure('Booking notification failed.');
        }
    }

    private function logNotificationFailure(string $message): void
    {
        try {
            (new Logger(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs'))
                ->warning($message);
        } catch (Throwable) {
        }
    }

    /**
     * Return public-safe confirmation data for a submitted booking request.
     *
     * @return array<string, mixed>|null
     */
    public function publicConfirmation(string $publicId): ?array
    {
        $normalized = trim($publicId);

        if ($normalized === '' || strlen($normalized) > 80 || !preg_match('/^[A-Za-z0-9_-]+$/', $normalized)) {
            return null;
        }

        return $this->bookingRepository->findPublicConfirmationByPublicId($normalized);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateCustomerAndCompanyScope(array $data, int $organizationId): void
    {
        $customerId = $this->nullableInt($data['customer_id'] ?? null);
        $companyId = $this->nullableInt($data['company_id'] ?? null);

        if ($customerId !== null && !$this->bookingRepository->customerBelongsToOrganization($customerId, $organizationId)) {
            throw new BookingException('Customer is not available for this organization.');
        }

        if ($companyId !== null && !$this->bookingRepository->companyBelongsToOrganization($companyId, $organizationId)) {
            throw new BookingException('Company is not available for this organization.');
        }
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new BookingException('customer_email must be a valid email address.');
        }

        return $normalized;
    }

    private function requiredString(mixed $value, string $field): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            throw new BookingException($field . ' is required.');
        }

        return $text;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new BookingException($field . ' is required.');
        }

        return (int) $value;
    }
}
