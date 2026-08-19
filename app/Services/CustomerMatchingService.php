<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BookingException;
use App\Models\Customer;
use App\Repositories\CustomerRepository;

/**
 * Resolves guest booking contact data to organization-scoped customer records.
 */
final class CustomerMatchingService
{
    public const BLOCKED_CUSTOMER_ERROR_CODE = 8101;

    public function __construct(
        private readonly CustomerRepository $customerRepository = new CustomerRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {
    }

    /**
     * Reuse one safe email match or create a minimal customer for guest booking.
     */
    public function resolveForGuestBooking(
        int $organizationId,
        string $name,
        string $email,
        string $phone,
        ?int $companyId = null,
        ?string $companyName = null
    ): Customer {
        $emailNormalized = $this->normalizeEmail($email);
        $existing = $this->customerRepository->findUniqueByNormalizedEmailForOrganization(
            $organizationId,
            $emailNormalized
        );

        if ($existing !== null) {
            $existingData = $existing->toArray();
            $statusKey = (string) ($existingData['status_key'] ?? '');

            if ($statusKey === 'blocked') {
                $this->auditService->record(
                    'customer_booking_blocked',
                    null,
                    'customer',
                    (int) ($existingData['id'] ?? 0),
                    null,
                    null,
                    [
                        'organization_id' => $organizationId,
                        'result' => 'blocked',
                    ]
                );

                throw new BookingException(
                    'Booking request could not be submitted.',
                    self::BLOCKED_CUSTOMER_ERROR_CODE
                );
            }

            if ($statusKey === 'active') {
                return $existing;
            }
        }

        $customer = $this->customerRepository->create([
            'organization_id' => $organizationId,
            'company_id' => $companyId,
            'customer_type_key' => $this->customerType($companyId, $companyName),
            'name' => $name,
            'email' => $emailNormalized,
            'phone' => $phone,
            'status_key' => 'active',
        ]);
        $customerData = $customer->toArray();

        $this->auditService->record(
            'customer_created_from_booking',
            null,
            'customer',
            (int) ($customerData['id'] ?? 0),
            null,
            null,
            [
                'organization_id' => $organizationId,
                'customer_type_key' => (string) ($customerData['customer_type_key'] ?? ''),
                'source' => 'guest_booking',
            ]
        );

        return $customer;
    }

    private function customerType(?int $companyId, ?string $companyName): string
    {
        if ($companyId !== null) {
            return 'company';
        }

        return $companyName !== null && trim($companyName) !== '' ? 'company' : 'private';
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new BookingException('customer_email must be a valid email address.');
        }

        return $normalized;
    }
}
