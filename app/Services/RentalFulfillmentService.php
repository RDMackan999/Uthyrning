<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BookingException;
use App\Core\Collection;
use App\Core\Database;
use App\Core\Request;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\RentalFulfillment;
use App\Models\RentalFulfillmentItem;
use App\Repositories\BookingItemRepository;
use App\Repositories\BookingRepository;
use App\Repositories\RentalFulfillmentItemRepository;
use App\Repositories\RentalFulfillmentRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Coordinates actual rental handover and return without owning booking plans.
 */
final class RentalFulfillmentService
{
    /**
     * @var list<string>
     */
    private const CONDITION_KEYS = ['good', 'acceptable', 'damaged'];

    /**
     * @var list<string>
     */
    private const DEPOSIT_STATUS_KEYS = [
        'not_required',
        'required',
        'received',
        'returned',
        'partially_retained',
        'retained',
    ];

    public function __construct(
        private readonly BookingRepository $bookingRepository = new BookingRepository(),
        private readonly BookingItemRepository $bookingItemRepository = new BookingItemRepository(),
        private readonly RentalFulfillmentRepository $fulfillmentRepository = new RentalFulfillmentRepository(),
        private readonly RentalFulfillmentItemRepository $fulfillmentItemRepository = new RentalFulfillmentItemRepository(),
        private readonly BookingStatusService $bookingStatusService = new BookingStatusService(),
        private readonly OrganizationAuthorizationService $authorizationService = new OrganizationAuthorizationService(),
        private readonly AuditService $auditService = new AuditService()
    ) {
    }

    /**
     * Record actual handover and move an approved booking to active.
     *
     * @param array<string, mixed> $data
     */
    public function recordHandover(Request $request, string $bookingPublicId, array $data): RentalFulfillment
    {
        $booking = $this->authorizedBooking($request, $bookingPublicId, 'handover');
        $bookingData = $booking->toArray();
        $organizationId = (int) ($bookingData['organization_id'] ?? 0);
        $bookingId = (int) ($bookingData['id'] ?? 0);

        if (($bookingData['status_key'] ?? null) !== 'approved') {
            throw new BookingException('Booking must be approved before handover.');
        }

        if ($this->fulfillmentRepository->findByBookingId($bookingId, $organizationId) !== null) {
            throw new BookingException('Booking handover has already been recorded.');
        }

        $bookingItems = $this->bookingItemRepository->findAdminForBooking($organizationId, $bookingId);
        $this->ensureBookingItemsExist($bookingItems);
        $itemConditions = $this->conditionDataForBookingItems($bookingItems, $data, 'handover');
        $actualHandoverAt = $this->utcDateTime($data['actual_handover_at'] ?? null);
        $actorUserId = $request->authenticatedUserId();
        $pdo = Database::pdo();
        $startedTransaction = !$pdo->inTransaction();

        try {
            if ($startedTransaction) {
                $pdo->beginTransaction();
            }

            $fulfillment = $this->fulfillmentRepository->create([
                'organization_id' => $organizationId,
                'booking_id' => $bookingId,
                'planned_start_date' => (string) ($bookingData['start_date'] ?? ''),
                'planned_end_date' => (string) ($bookingData['end_date'] ?? ''),
                'actual_handover_at' => $actualHandoverAt,
                'handed_over_by_user_id' => $actorUserId,
                'received_by_name' => $data['received_by_name'] ?? null,
                'handover_note' => $data['handover_note'] ?? null,
                'terms_version_key' => $data['terms_version_key'] ?? null,
                'deposit_required_amount' => $bookingData['deposit_amount'] ?? null,
                'deposit_received_amount' => $data['deposit_received_amount'] ?? null,
                'deposit_status_key' => $this->depositStatus($data['deposit_status_key'] ?? 'not_required'),
            ]);
            $fulfillmentId = (int) ($fulfillment->toArray()['id'] ?? 0);

            foreach ($bookingItems as $bookingItem) {
                if (!$bookingItem instanceof BookingItem) {
                    continue;
                }

                $bookingItemData = $bookingItem->toArray();
                $bookingItemId = (int) ($bookingItemData['id'] ?? 0);
                $condition = $itemConditions[$bookingItemId];

                $this->fulfillmentItemRepository->create([
                    'rental_fulfillment_id' => $fulfillmentId,
                    'booking_item_id' => $bookingItemId,
                    'rental_item_id' => (int) ($bookingItemData['rental_item_id'] ?? 0),
                    'item_public_id_snapshot' => (string) ($bookingItemData['rental_item_public_id'] ?? ''),
                    'item_name_snapshot' => (string) ($bookingItemData['rental_item_name'] ?? ''),
                    'handover_condition_key' => $condition['condition_key'],
                    'handover_condition_note' => $condition['condition_note'],
                    'meter_value_handover' => $condition['meter_value'],
                ]);
            }

            $this->bookingStatusService->transition(
                $organizationId,
                $bookingId,
                'active',
                $actorUserId,
                'Rental handover recorded.'
            );
            $this->auditService->record(
                'rental_handover_recorded',
                $actorUserId,
                'rental_fulfillment',
                $fulfillmentId,
                $request->ipAddress(),
                $request->userAgent(),
                [
                    'organization_id' => $organizationId,
                    'booking_id' => $bookingId,
                    'booking_public_id' => $bookingPublicId,
                    'actual_handover_at' => $actualHandoverAt,
                    'item_count' => $bookingItems->count(),
                    'deposit_status_key' => $this->depositStatus($data['deposit_status_key'] ?? 'not_required'),
                ]
            );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $this->fulfillmentRepository->findById($fulfillmentId, $organizationId);
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Record actual return and move an active booking to completed.
     *
     * @param array<string, mixed> $data
     */
    public function recordReturn(Request $request, string $bookingPublicId, array $data): RentalFulfillment
    {
        $booking = $this->authorizedBooking($request, $bookingPublicId, 'return');
        $bookingData = $booking->toArray();
        $organizationId = (int) ($bookingData['organization_id'] ?? 0);
        $bookingId = (int) ($bookingData['id'] ?? 0);

        if (($bookingData['status_key'] ?? null) !== 'active') {
            throw new BookingException('Booking must be active before return.');
        }

        $fulfillment = $this->fulfillmentRepository->findByBookingId($bookingId, $organizationId);

        if ($fulfillment === null) {
            throw new BookingException('Booking handover must be recorded before return.');
        }

        $fulfillmentData = $fulfillment->toArray();

        if (($fulfillmentData['actual_return_at'] ?? null) !== null) {
            throw new BookingException('Booking return has already been recorded.');
        }

        $fulfillmentId = (int) ($fulfillmentData['id'] ?? 0);
        $fulfillmentItems = $this->fulfillmentItemRepository->findForFulfillment($fulfillmentId);
        $this->ensureFulfillmentItemsExist($fulfillmentItems);
        $itemConditions = $this->conditionDataForFulfillmentItems($fulfillmentItems, $data);
        $actualReturnAt = $this->utcDateTime($data['actual_return_at'] ?? null);
        $depositStatusKey = $this->depositStatus($data['deposit_status_key'] ?? 'not_required');
        $actorUserId = $request->authenticatedUserId();
        $pdo = Database::pdo();
        $startedTransaction = !$pdo->inTransaction();

        try {
            if ($startedTransaction) {
                $pdo->beginTransaction();
            }

            foreach ($fulfillmentItems as $fulfillmentItem) {
                if (!$fulfillmentItem instanceof RentalFulfillmentItem) {
                    continue;
                }

                $fulfillmentItemData = $fulfillmentItem->toArray();
                $fulfillmentItemId = (int) ($fulfillmentItemData['id'] ?? 0);
                $condition = $itemConditions[(int) ($fulfillmentItemData['booking_item_id'] ?? 0)];

                $this->fulfillmentItemRepository->recordReturn($fulfillmentItemId, [
                    'return_condition_key' => $condition['condition_key'],
                    'return_condition_note' => $condition['condition_note'],
                    'has_return_deviation' => $condition['has_return_deviation'],
                    'damage_note' => $condition['damage_note'],
                    'meter_value_return' => $condition['meter_value'],
                ]);
            }

            $updatedFulfillment = $this->fulfillmentRepository->markReturned($fulfillmentId, $organizationId, [
                'actual_return_at' => $actualReturnAt,
                'returned_to_user_id' => $actorUserId,
                'return_note' => $data['return_note'] ?? null,
                'deposit_returned_amount' => $data['deposit_returned_amount'] ?? null,
                'deposit_retained_amount' => $data['deposit_retained_amount'] ?? null,
                'deposit_status_key' => $depositStatusKey,
            ]);
            $this->bookingStatusService->transition(
                $organizationId,
                $bookingId,
                'completed',
                $actorUserId,
                'Rental return recorded.'
            );
            $timing = $this->returnTiming((string) ($bookingData['end_date'] ?? ''), $actualReturnAt);
            $this->auditService->record(
                'rental_return_recorded',
                $actorUserId,
                'rental_fulfillment',
                $fulfillmentId,
                $request->ipAddress(),
                $request->userAgent(),
                [
                    'organization_id' => $organizationId,
                    'booking_id' => $bookingId,
                    'booking_public_id' => $bookingPublicId,
                    'actual_return_at' => $actualReturnAt,
                    'planned_end_date' => (string) ($bookingData['end_date'] ?? ''),
                    'is_early_return' => $timing['is_early_return'],
                    'is_late_return' => $timing['is_late_return'],
                    'deposit_status_key' => $depositStatusKey,
                ]
            );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $updatedFulfillment;
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function authorizedBooking(Request $request, string $bookingPublicId, string $action): Booking
    {
        $publicId = trim($bookingPublicId);

        if ($publicId === '' || strlen($publicId) > 80 || !preg_match('/^[A-Za-z0-9_-]+$/', $publicId)) {
            throw new BookingException('Booking reference is invalid.');
        }

        $booking = $this->bookingRepository->findByPublicId($publicId);

        if ($booking === null) {
            throw new BookingException('Booking not found.');
        }

        $bookingData = $booking->toArray();
        $organizationId = (int) ($bookingData['organization_id'] ?? 0);
        $bookingId = (int) ($bookingData['id'] ?? 0);

        $this->authorizationService->assertCanAccessResource(
            $request,
            $organizationId,
            'booking',
            $bookingId,
            $action
        );

        return $booking;
    }

    /**
     * @return array<int, array{condition_key: string, condition_note: ?string, meter_value: ?string}>
     */
    private function conditionDataForBookingItems(Collection $bookingItems, array $data, string $phase): array
    {
        $specific = $this->specificItemData($data);
        $conditions = [];

        foreach ($bookingItems as $bookingItem) {
            if (!$bookingItem instanceof BookingItem) {
                continue;
            }

            $bookingItemData = $bookingItem->toArray();
            $bookingItemId = (int) ($bookingItemData['id'] ?? 0);
            $itemData = $specific[$bookingItemId] ?? [];
            $conditionKey = $itemData['condition_key']
                ?? $itemData[$phase . '_condition_key']
                ?? $data[$phase . '_condition_key']
                ?? null;

            $conditions[$bookingItemId] = [
                'condition_key' => $this->conditionKey($conditionKey),
                'condition_note' => $this->nullableString(
                    $itemData['condition_note'] ?? $itemData[$phase . '_condition_note'] ?? $data[$phase . '_condition_note'] ?? null
                ),
                'meter_value' => $this->nullableDecimal(
                    $itemData['meter_value'] ?? $itemData['meter_value_' . $phase] ?? $data['meter_value_' . $phase] ?? null
                ),
            ];
        }

        $this->ensureNoUnknownItemInput(array_keys($specific), array_keys($conditions));

        return $conditions;
    }

    /**
     * @return array<int, array{
     *     condition_key: string,
     *     condition_note: ?string,
     *     has_return_deviation: bool,
     *     damage_note: ?string,
     *     meter_value: ?string
     * }>
     */
    private function conditionDataForFulfillmentItems(Collection $fulfillmentItems, array $data): array
    {
        $specific = $this->specificItemData($data);
        $conditions = [];

        foreach ($fulfillmentItems as $fulfillmentItem) {
            if (!$fulfillmentItem instanceof RentalFulfillmentItem) {
                continue;
            }

            $fulfillmentItemData = $fulfillmentItem->toArray();
            $bookingItemId = (int) ($fulfillmentItemData['booking_item_id'] ?? 0);
            $itemData = $specific[$bookingItemId] ?? [];
            $conditionKey = $itemData['condition_key']
                ?? $itemData['return_condition_key']
                ?? $data['return_condition_key']
                ?? null;

            $conditions[$bookingItemId] = [
                'condition_key' => $this->conditionKey($conditionKey),
                'condition_note' => $this->nullableString(
                    $itemData['condition_note'] ?? $itemData['return_condition_note'] ?? $data['return_condition_note'] ?? null
                ),
                'has_return_deviation' => filter_var(
                    $itemData['has_return_deviation'] ?? $data['has_return_deviation'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'damage_note' => $this->nullableString($itemData['damage_note'] ?? $data['damage_note'] ?? null),
                'meter_value' => $this->nullableDecimal(
                    $itemData['meter_value'] ?? $itemData['meter_value_return'] ?? $data['meter_value_return'] ?? null
                ),
            ];
        }

        $this->ensureNoUnknownItemInput(array_keys($specific), array_keys($conditions));

        return $conditions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function specificItemData(array $data): array
    {
        $items = $data['items'] ?? [];

        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $bookingItemId = (int) ($item['booking_item_id'] ?? 0);

            if ($bookingItemId > 0) {
                $normalized[$bookingItemId] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param list<int> $inputIds
     * @param list<int> $validIds
     */
    private function ensureNoUnknownItemInput(array $inputIds, array $validIds): void
    {
        foreach ($inputIds as $inputId) {
            if (!in_array($inputId, $validIds, true)) {
                throw new BookingException('Fulfillment item does not belong to the booking.');
            }
        }
    }

    private function conditionKey(mixed $value): string
    {
        $key = strtolower(trim((string) $value));

        if (!in_array($key, self::CONDITION_KEYS, true)) {
            throw new BookingException('Condition key is invalid.');
        }

        return $key;
    }

    private function depositStatus(mixed $value): string
    {
        $key = strtolower(trim((string) $value));

        if (!in_array($key, self::DEPOSIT_STATUS_KEYS, true)) {
            throw new BookingException('Deposit status key is invalid.');
        }

        return $key;
    }

    private function utcDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }

        $text = trim((string) $value);
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $text, new DateTimeZone('UTC'));

        if ($dateTime === false || $dateTime->format('Y-m-d H:i:s') !== $text) {
            throw new BookingException('Datetime must use UTC format YYYY-MM-DD HH:MM:SS.');
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

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value) || (float) $value < 0) {
            throw new BookingException('Decimal value must be zero or greater.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @return array{is_early_return: bool, is_late_return: bool}
     */
    private function returnTiming(string $plannedEndDate, string $actualReturnAt): array
    {
        $actualReturnDate = substr($actualReturnAt, 0, 10);

        return [
            'is_early_return' => $actualReturnDate < $plannedEndDate,
            'is_late_return' => $actualReturnDate > $plannedEndDate,
        ];
    }

    private function ensureBookingItemsExist(Collection $bookingItems): void
    {
        if ($bookingItems->count() === 0) {
            throw new BookingException('Booking must have at least one item before handover.');
        }
    }

    private function ensureFulfillmentItemsExist(Collection $fulfillmentItems): void
    {
        if ($fulfillmentItems->count() === 0) {
            throw new BookingException('Rental fulfillment must have at least one item before return.');
        }
    }
}
