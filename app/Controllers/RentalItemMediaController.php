<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\CsrfTokenManager;
use App\Core\MediaException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Models\RentalItem;
use App\Repositories\RentalItemRepository;
use App\Services\Media\ItemMediaService;
use App\Services\OrganizationAuthorizationService;
use Throwable;

/**
 * Handles protected admin media actions for rental items.
 */
final class RentalItemMediaController extends BaseController
{
    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
        private readonly ItemMediaService $itemMediaService = new ItemMediaService(),
        private readonly OrganizationAuthorizationService $authorizationService = new OrganizationAuthorizationService(),
        ?CsrfTokenManager $csrfTokenManager = null,
    ) {
        parent::__construct();

        $this->csrfTokenManager = $csrfTokenManager ?? CsrfTokenManager::fromConfig();
    }

    public static function fromConfig(): self
    {
        return new self();
    }

    public function store(Request $request): Response
    {
        $item = $this->itemFromRoute($request, 'upload_media');

        if (!$this->validCsrf($request)) {
            return $this->redirectToEdit($item, 'media_error=csrf');
        }

        try {
            $this->itemMediaService->uploadImages($request, $item, $request->files('images'));
        } catch (MediaException) {
            return $this->redirectToEdit($item, 'media_error=upload');
        } catch (Throwable) {
            return $this->redirectToEdit($item, 'media_error=upload');
        }

        return $this->redirectToEdit($item, 'media=uploaded');
    }

    public function sort(Request $request): Response
    {
        $item = $this->itemFromRoute($request, 'sort_media');

        if (!$this->validCsrf($request)) {
            return $this->redirectToEdit($item, 'media_error=csrf');
        }

        $post = $request->post();
        $sortOrder = is_array($post) && is_array($post['sort_order'] ?? null) ? $post['sort_order'] : [];
        $this->itemMediaService->updateSortOrder($request, $item, $sortOrder);

        return $this->redirectToEdit($item, 'media=sorted');
    }

    public function primary(Request $request): Response
    {
        $item = $this->itemFromRoute($request, 'set_primary_media');

        if (!$this->validCsrf($request)) {
            return $this->redirectToEdit($item, 'media_error=csrf');
        }

        $this->itemMediaService->setPrimary($request, $item, (string) $request->route('media_public_id', ''));

        return $this->redirectToEdit($item, 'media=primary');
    }

    public function archive(Request $request): Response
    {
        $item = $this->itemFromRoute($request, 'archive_media');

        if (!$this->validCsrf($request)) {
            return $this->redirectToEdit($item, 'media_error=csrf');
        }

        $this->itemMediaService->archive($request, $item, (string) $request->route('media_public_id', ''));

        return $this->redirectToEdit($item, 'media=archived');
    }

    private function itemFromRoute(Request $request, string $action): RentalItem
    {
        $item = $this->rentalItemRepository->findByPublicId((string) $request->route('public_id', ''));

        if ($item === null) {
            throw new NotFoundException();
        }

        $itemData = $item->toArray();
        $this->authorizationService->assertCanAccessResource(
            $request,
            (int) ($itemData['organization_id'] ?? 0),
            'rental_item',
            (int) ($itemData['id'] ?? 0),
            $action
        );

        return $item;
    }

    private function validCsrf(Request $request): bool
    {
        $post = $request->post();
        $token = is_array($post) && is_scalar($post['csrf_token'] ?? null) ? (string) $post['csrf_token'] : null;

        return $this->csrfTokenManager->validate($request, $token);
    }

    private function redirectToEdit(RentalItem $item, string $queryString): Response
    {
        $itemData = $item->toArray();

        return $this->redirect(
            '/admin/items/' . rawurlencode((string) ($itemData['public_id'] ?? '')) . '/edit?' . $queryString
        );
    }
}
