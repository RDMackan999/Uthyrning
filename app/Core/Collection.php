<?php

declare(strict_types=1);

namespace App\Core;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Simple iterable collection for future model results.
 *
 * @template TItem
 * @implements IteratorAggregate<int, TItem>
 */
final class Collection implements Countable, IteratorAggregate
{
    /**
     * @param list<TItem> $items
     */
    public function __construct(private readonly array $items = [])
    {
    }

    /**
     * Return an iterator for foreach support.
     *
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Count collection items.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Convert collection items to arrays when possible.
     *
     * @return list<mixed>
     */
    public function toArray(): array
    {
        return array_map(static function (mixed $item): mixed {
            if ($item instanceof BaseModel) {
                return $item->toArray();
            }

            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }

            return $item;
        }, $this->items);
    }
}
