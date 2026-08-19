<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\BookingException;
use App\Core\CsrfTokenManager;
use App\Core\ModelException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Http\BookingRequestFormRequest;
use App\Models\RentalItem;
use App\Repositories\RentalItemRepository;
use App\Services\AvailabilityCalendarService;
use App\Services\BookingPricingService;
use App\Services\BookingService;
use Throwable;

/**
 * Handles public guest booking requests for published rental items.
 */
final class PublicBookingController extends BaseController
{
    private const UNAVAILABLE_MESSAGE = 'Objektet är inte tillgängligt för valda datum. Välj andra datum och försök igen.';
    private const GENERIC_ERROR_MESSAGE = 'Bokningsförfrågan kunde inte skickas just nu. Kontrollera uppgifterna och försök igen.';

    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
        private readonly BookingService $bookingService = new BookingService(),
        private readonly BookingRequestFormRequest $formRequest = new BookingRequestFormRequest(),
        private readonly BookingPricingService $pricingService = new BookingPricingService(),
        private readonly AvailabilityCalendarService $calendarService = new AvailabilityCalendarService(),
        ?CsrfTokenManager $csrfTokenManager = null,
    ) {
        parent::__construct();

        $this->csrfTokenManager = $csrfTokenManager ?? CsrfTokenManager::fromConfig();
    }

    /**
     * Show the public booking form for one bookable item.
     */
    public function create(Request $request): Response
    {
        $item = $this->itemFromRoute($request);

        return $this->renderForm($request, $item, $this->defaultFormData());
    }

    /**
     * Validate and submit a guest booking request.
     */
    public function store(Request $request): Response
    {
        $item = $this->itemFromRoute($request);
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->renderForm($request, $item, $postData, [
                'form' => 'Formuläret kunde inte verifieras. Försök igen.',
            ]);
        }

        $validated = $this->formRequest->validate(
            $postData,
            (string) $request->route('public_id', ''),
            (string) $request->route('slug', '')
        );

        if ($validated['errors'] !== []) {
            return $this->renderForm($request, $item, $validated['data'], $validated['errors']);
        }

        $itemData = $item->toArray();

        try {
            $booking = $this->bookingService->createRequest([
                'rental_item_id' => (int) ($itemData['id'] ?? 0),
                'start_date' => $validated['data']['start_date'],
                'end_date' => $validated['data']['end_date'],
                'customer_name' => $validated['data']['customer_name'],
                'customer_email' => $validated['data']['customer_email'],
                'customer_phone' => $validated['data']['customer_phone'],
                'company_name' => $validated['data']['company_name'],
                'customer_comment' => $validated['data']['customer_comment'],
            ]);
        } catch (BookingException) {
            return $this->renderForm($request, $item, $validated['data'], [
                'form' => self::UNAVAILABLE_MESSAGE,
            ]);
        } catch (Throwable) {
            return $this->renderForm($request, $item, $validated['data'], [
                'form' => self::GENERIC_ERROR_MESSAGE,
            ]);
        }

        $bookingData = $booking->toArray();

        return $this->redirect('/bookings/' . rawurlencode((string) ($bookingData['public_id'] ?? '')) . '/confirmation');
    }

    /**
     * Show a public-safe confirmation page by non-sequential booking reference.
     */
    public function confirmation(Request $request): Response
    {
        $publicId = $this->stringValue($request->route('public_id'));

        if ($publicId === '') {
            throw new NotFoundException();
        }

        $booking = $this->bookingService->publicConfirmation($publicId);

        if ($booking === null) {
            throw new NotFoundException();
        }

        return $this->viewWithLayout('public/bookings/confirmation', 'layouts/public', [
            'pageTitle' => 'Bokningsförfrågan mottagen',
            'booking' => $booking,
        ]);
    }

    /**
     * Render the form with item display data and current price preview.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderForm(Request $request, RentalItem $item, array $data, array $errors = []): Response
    {
        $itemData = $item->toArray();
        $pricePreview = null;
        $availabilityCalendar = $this->availabilityCalendar($item, $data);

        if ($this->stringValue($data['start_date'] ?? '') !== '' && $this->stringValue($data['end_date'] ?? '') !== '') {
            try {
                $pricePreview = $this->pricingService->calculateDailySnapshot(
                    (int) ($itemData['organization_id'] ?? 0),
                    $item,
                    $this->stringValue($data['start_date'] ?? ''),
                    $this->stringValue($data['end_date'] ?? '')
                );
            } catch (Throwable) {
                $pricePreview = null;
            }
        }

        return $this->viewWithLayout('public/bookings/create', 'layouts/public', [
            'pageTitle' => 'Boka objekt',
            'item' => $itemData,
            'data' => $data,
            'errors' => $errors,
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'pricePreview' => $pricePreview,
            'availabilityCalendar' => $availabilityCalendar,
            'publicScripts' => ['/assets/scripts/booking-calendar.js'],
        ]);
    }

    /**
     * Build public-safe availability data for the booking form.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function availabilityCalendar(RentalItem $item, array $data): array
    {
        $itemData = $item->toArray();

        try {
            return $this->calendarService->publicMonths(
                (int) ($itemData['organization_id'] ?? 0),
                (int) ($itemData['id'] ?? 0),
                $this->stringValue($data['start_date'] ?? null),
                $this->stringValue($data['end_date'] ?? null)
            );
        } catch (Throwable) {
            return [];
        }
    }

    private function itemFromRoute(Request $request): RentalItem
    {
        try {
            return $this->rentalItemRepository->findBookableByPublicRoute(
                $this->stringValue($request->route('public_id')),
                $this->stringValue($request->route('slug'))
            );
        } catch (ModelException) {
            throw new NotFoundException();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function postData(Request $request): array
    {
        $postData = $request->post();

        return is_array($postData) ? $postData : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormData(): array
    {
        return [
            'start_date' => '',
            'end_date' => '',
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'company_name' => '',
            'customer_comment' => '',
        ];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}
