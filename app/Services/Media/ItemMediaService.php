<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\MediaStorageInterface;
use App\Core\Database;
use App\Core\MediaException;
use App\Core\Request;
use App\Models\RentalItem;
use App\Repositories\ItemMediaRepository;
use App\Repositories\MediaAssetRepository;
use App\Repositories\MediaVariantRepository;
use App\Services\AuditService;
use Throwable;

/**
 * Coordinates safe item image upload and logical media administration.
 */
final class ItemMediaService
{
    public function __construct(
        private readonly ImageValidationService $validationService = new ImageValidationService(),
        private readonly ImageProcessingService $processingService = new ImageProcessingService(),
        private readonly MediaStorageInterface $storage = new LocalMediaStorage(),
        private readonly MediaAssetRepository $mediaAssetRepository = new MediaAssetRepository(),
        private readonly MediaVariantRepository $mediaVariantRepository = new MediaVariantRepository(),
        private readonly ItemMediaRepository $itemMediaRepository = new ItemMediaRepository(),
        private readonly AuditService $auditService = new AuditService(),
    ) {
    }

    /**
     * Store validated images for one rental item and link them to the item.
     *
     * @return list<array<string, mixed>>
     */
    public function uploadImages(Request $request, RentalItem $item, mixed $files): array
    {
        $itemData = $item->toArray();
        $organizationId = (int) ($itemData['organization_id'] ?? 0);
        $rentalItemId = (int) ($itemData['id'] ?? 0);
        $uploadedByUserId = $request->authenticatedUserId();
        $normalizedFiles = $this->validationService->normalizeUploadedFiles($files);

        if ($normalizedFiles === []) {
            throw new MediaException('Välj minst en bild att ladda upp.');
        }

        $createdAssets = [];
        $storedKeys = [];
        $temporaryFiles = [];

        $pdo = Database::pdo();
        $ownsTransaction = !$pdo->inTransaction();

        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }

            foreach ($normalizedFiles as $file) {
                $metadata = $this->validationService->validate($file);
                $baseStorageKey = $this->storageKeyBase($organizationId);
                $originalStorageKey = 'original/' . $baseStorageKey . '.' . $metadata['extension'];
                $this->storage->store($metadata['tmp_name'], $originalStorageKey);
                $storedKeys[] = $originalStorageKey;

                $asset = $this->mediaAssetRepository->create([
                    'organization_id' => $organizationId,
                    'media_type_key' => 'image',
                    'mime_type' => $metadata['mime_type'],
                    'original_filename' => $metadata['original_filename'],
                    'storage_disk_key' => 'local',
                    'storage_key' => $originalStorageKey,
                    'checksum_sha256' => $metadata['checksum_sha256'],
                    'file_size_bytes' => $metadata['file_size_bytes'],
                    'width' => $metadata['width'],
                    'height' => $metadata['height'],
                    'uploaded_by_user_id' => $uploadedByUserId,
                ]);
                $assetData = $asset->toArray();
                $variants = $this->processingService->createVariants($metadata['tmp_name'], $metadata['mime_type']);

                foreach ($variants as $variantKey => $variant) {
                    $temporaryFiles[] = $variant['path'];
                    $variantStorageKey = 'variants/' . $baseStorageKey . '-' . $variantKey . '.' . $metadata['extension'];
                    $this->storage->store($variant['path'], $variantStorageKey);
                    $storedKeys[] = $variantStorageKey;

                    $this->mediaVariantRepository->create([
                        'media_asset_id' => (int) ($assetData['id'] ?? 0),
                        'variant_key' => $variantKey,
                        'mime_type' => $variant['mime_type'],
                        'storage_disk_key' => 'local',
                        'storage_key' => $variantStorageKey,
                        'file_size_bytes' => $variant['file_size_bytes'],
                        'width' => $variant['width'],
                        'height' => $variant['height'],
                    ]);
                }

                $isPrimary = !$this->itemMediaRepository->hasActiveMedia($organizationId, $rentalItemId)
                    && $createdAssets === [];
                $this->itemMediaRepository->link(
                    $organizationId,
                    $rentalItemId,
                    (int) ($assetData['id'] ?? 0),
                    $isPrimary
                );
                $createdAssets[] = $asset->toArray();
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($storedKeys as $storageKey) {
                $this->storage->delete($storageKey);
            }

            $this->removeTemporaryFiles($temporaryFiles);

            if ($exception instanceof MediaException) {
                throw $exception;
            }

            throw new MediaException('Bilderna kunde inte sparas.', 0, $exception);
        }

        $this->removeTemporaryFiles($temporaryFiles);
        $this->auditService->record(
            'item_media_uploaded',
            $uploadedByUserId,
            'rental_item',
            $rentalItemId,
            $request->ipAddress(),
            $request->userAgent(),
            [
                'organization_id' => $organizationId,
                'uploaded_count' => count($createdAssets),
            ]
        );

        return $createdAssets;
    }

    public function setPrimary(Request $request, RentalItem $item, string $mediaPublicId): void
    {
        $itemData = $item->toArray();
        $this->itemMediaRepository->setPrimaryByMediaPublicId(
            (int) ($itemData['organization_id'] ?? 0),
            (int) ($itemData['id'] ?? 0),
            $mediaPublicId
        );
        $this->auditItemMediaAction($request, $item, 'item_media_primary_set', $mediaPublicId);
    }

    /**
     * @param array<string, mixed> $sortOrders
     */
    public function updateSortOrder(Request $request, RentalItem $item, array $sortOrders): void
    {
        $itemData = $item->toArray();
        $this->itemMediaRepository->updateSortOrder(
            (int) ($itemData['organization_id'] ?? 0),
            (int) ($itemData['id'] ?? 0),
            $sortOrders
        );
        $this->auditItemMediaAction($request, $item, 'item_media_sorted');
    }

    public function archive(Request $request, RentalItem $item, string $mediaPublicId): void
    {
        $itemData = $item->toArray();
        $organizationId = (int) ($itemData['organization_id'] ?? 0);
        $rentalItemId = (int) ($itemData['id'] ?? 0);
        $relation = $this->itemMediaRepository->findActiveRelationByMediaPublicId(
            $organizationId,
            $rentalItemId,
            $mediaPublicId
        );
        $this->itemMediaRepository->archiveByMediaPublicId($organizationId, $rentalItemId, $mediaPublicId);
        $this->mediaAssetRepository->archive((int) ($relation->toArray()['media_asset_id'] ?? 0), $organizationId);
        $this->auditItemMediaAction($request, $item, 'item_media_archived', $mediaPublicId);
    }

    private function storageKeyBase(int $organizationId): string
    {
        $today = gmdate('Y/m');

        return $organizationId . '/' . $today . '/' . bin2hex(random_bytes(16));
    }

    /**
     * @param list<string> $temporaryFiles
     */
    private function removeTemporaryFiles(array $temporaryFiles): void
    {
        foreach ($temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function auditItemMediaAction(
        Request $request,
        RentalItem $item,
        string $eventName,
        ?string $mediaPublicId = null
    ): void {
        $itemData = $item->toArray();

        $this->auditService->record(
            $eventName,
            $request->authenticatedUserId(),
            'rental_item',
            (int) ($itemData['id'] ?? 0),
            $request->ipAddress(),
            $request->userAgent(),
            [
                'organization_id' => (int) ($itemData['organization_id'] ?? 0),
                'media_public_id' => $mediaPublicId,
            ]
        );
    }
}
