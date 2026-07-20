<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ModelException;
use App\Models\RentalItem;
use App\Repositories\ItemRateRepository;
use App\Repositories\RentalItemRepository;

/**
 * Central domain service for Version 1 rental item publication rules.
 */
final class RentalItemPublicationService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public function __construct(
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
        private readonly ItemRateRepository $itemRateRepository = new ItemRateRepository(),
    ) {
    }

    /**
     * Return true when the rental item satisfies every Version 1 publication rule.
     */
    public function canPublish(RentalItem $item): bool
    {
        return $this->publicationErrors($item) === [];
    }

    /**
     * Publish a valid draft rental item.
     */
    public function publish(RentalItem $item): RentalItem
    {
        $errors = $this->publicationErrors($item);

        if ($errors !== []) {
            throw new ModelException('Objektet kan inte publiceras: ' . implode(' ', $errors));
        }

        return $this->rentalItemRepository->updatePublicationStatus(
            $this->requiredId($item),
            self::STATUS_PUBLISHED
        );
    }

    /**
     * Move a published rental item back to draft.
     */
    public function unpublish(RentalItem $item): RentalItem
    {
        if ($this->publicationStatus($item) !== self::STATUS_PUBLISHED) {
            throw new ModelException('Endast publicerade objekt kan avpubliceras.');
        }

        return $this->rentalItemRepository->updatePublicationStatus(
            $this->requiredId($item),
            self::STATUS_DRAFT
        );
    }

    /**
     * Archive a draft or published rental item using the existing soft-delete behavior.
     */
    public function archive(RentalItem $item): bool
    {
        if ($this->isSoftDeleted($item)) {
            throw new ModelException('Soft delete:ade objekt är redan arkiverade.');
        }

        if ($this->publicationStatus($item) === self::STATUS_ARCHIVED) {
            throw new ModelException('Arkiverade objekt kan inte arkiveras igen.');
        }

        return $this->rentalItemRepository->delete($this->requiredId($item));
    }

    /**
     * Return user-facing rule failures for publication attempts.
     *
     * @return list<string>
     */
    public function publicationErrors(RentalItem $item): array
    {
        $data = $item->toArray();
        $errors = [];

        if ($this->publicationStatus($item) === self::STATUS_ARCHIVED) {
            $errors[] = 'Arkiverade objekt kan inte publiceras direkt.';
        }

        if ($this->isSoftDeleted($item)) {
            $errors[] = 'Soft delete:ade objekt kan inte publiceras.';
        }

        if ($this->stringValue($data['name'] ?? null) === '') {
            $errors[] = 'Namn saknas.';
        }

        if ($this->stringValue($data['slug'] ?? null) === '') {
            $errors[] = 'Slug saknas.';
        }

        if (!$this->hasPositiveInt($data['organization_id'] ?? null)) {
            $errors[] = 'Organisation saknas.';
        }

        if (!$this->hasPositiveInt($data['primary_category_id'] ?? null)) {
            $errors[] = 'Primär kategori saknas.';
        }

        if (!$this->boolValue($data['is_active'] ?? false)) {
            $errors[] = 'Objektet måste vara aktivt.';
        }

        if (!$this->boolValue($data['is_rentable'] ?? false)) {
            $errors[] = 'Objektet måste vara uthyrningsbart.';
        }

        if ($this->hasPositiveInt($data['id'] ?? null) && $this->hasPositiveInt($data['organization_id'] ?? null)) {
            $hasDailyRate = $this->itemRateRepository->hasActiveDailyRate(
                (int) $data['organization_id'],
                (int) $data['id']
            );

            if (!$hasDailyRate) {
                $errors[] = 'Aktivt dagspris saknas.';
            }
        } else {
            $errors[] = 'Aktivt dagspris kan inte kontrolleras.';
        }

        return $errors;
    }

    private function publicationStatus(RentalItem $item): string
    {
        return strtolower($this->stringValue($item->toArray()['publication_status_key'] ?? self::STATUS_DRAFT));
    }

    private function isSoftDeleted(RentalItem $item): bool
    {
        $deletedAt = $item->toArray()['deleted_at'] ?? null;

        return $deletedAt !== null && $deletedAt !== '';
    }

    private function requiredId(RentalItem $item): int
    {
        $id = $item->toArray()['id'] ?? null;

        if (!$this->hasPositiveInt($id)) {
            throw new ModelException('Rental item id is required.');
        }

        return (int) $id;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function hasPositiveInt(mixed $value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }

    private function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
