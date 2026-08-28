<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\MediaStorageInterface;
use App\Core\BaseController;
use App\Core\MediaException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\MediaVariantRepository;
use App\Services\Media\LocalMediaStorage;
use App\Services\OrganizationAuthorizationService;

/**
 * Delivers stored media through controlled routes without exposing storage paths.
 */
final class MediaDeliveryController extends BaseController
{
    private const ALLOWED_VARIANTS = ['thumbnail', 'card', 'detail'];

    public function __construct(
        private readonly MediaVariantRepository $mediaVariantRepository = new MediaVariantRepository(),
        private readonly MediaStorageInterface $storage = new LocalMediaStorage(),
        private readonly OrganizationAuthorizationService $authorizationService = new OrganizationAuthorizationService(),
    ) {
        parent::__construct();
    }

    public function publicImage(Request $request): Response
    {
        $variant = $this->variantFromRoute($request);
        $row = $this->mediaVariantRepository->findPublicDeliverable(
            $this->publicIdFromRoute($request),
            $variant
        );

        if ($row === null) {
            throw new NotFoundException();
        }

        return $this->deliver($row, false);
    }

    public function adminImage(Request $request): Response
    {
        $variant = $this->variantFromRoute($request);
        $row = $this->mediaVariantRepository->findAdminDeliverable(
            $this->publicIdFromRoute($request),
            $variant
        );

        if ($row === null) {
            throw new NotFoundException();
        }

        $this->authorizationService->assertCanAccessResource(
            $request,
            (int) ($row['organization_id'] ?? 0),
            'media_asset',
            (string) ($row['media_public_id'] ?? ''),
            'view'
        );

        return $this->deliver($row, true);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function deliver(array $row, bool $private): Response
    {
        if ((string) ($row['storage_disk_key'] ?? '') !== 'local') {
            throw new NotFoundException();
        }

        try {
            $content = $this->storage->read((string) ($row['storage_key'] ?? ''));
        } catch (MediaException) {
            throw new NotFoundException();
        }

        return new Response($content, 200, [
            'Content-Type' => (string) ($row['mime_type'] ?? 'application/octet-stream'),
            'Cache-Control' => $private ? 'private, max-age=300' : 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function publicIdFromRoute(Request $request): string
    {
        $publicId = (string) $request->route('public_id', '');

        if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $publicId)) {
            throw new NotFoundException();
        }

        return $publicId;
    }

    private function variantFromRoute(Request $request): string
    {
        $variant = strtolower((string) $request->route('variant', ''));

        if (!in_array($variant, self::ALLOWED_VARIANTS, true)) {
            throw new NotFoundException();
        }

        return $variant;
    }
}
