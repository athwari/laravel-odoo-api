<?php

namespace Athwari\LaravelOdooApi\Odoo\Models;

use ArrayAccess;
use Athwari\LaravelOdooApi\Odoo\OdooModel;
use Countable;
use Iterator;

/**
 * Lazily-loaded collection backing a #[HasMany] relation property.
 *
 * The related records are not fetched from Odoo until the collection
 * is actually accessed (iterated, counted, or indexed into). This
 * avoids loading potentially large related collections for relations
 * that are declared on a model but never used by a given code path.
 *
 * @template T of OdooModel
 *
 * @implements ArrayAccess<int, T>
 * @implements Iterator<int, T>
 */
final class LazyHasMany implements ArrayAccess, Countable, Iterator
{
    private bool $loaded = false;

    /** @var T[] */
    private array $items = [];

    private int $position = 0;

    /**
     * @param  class-string<T>  $modelClass
     * @param  int[]  $ids  The related record IDs, as returned by Odoo for the o2m/m2m field
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly array $ids,
    ) {}

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Discard any loaded data so the next access re-fetches from Odoo.
     */
    public function reload(): self
    {
        $this->loaded = false;
        $this->items = [];
        $this->position = 0;

        return $this;
    }

    /**
     * @internal For eager loading
     * @return int[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @internal For eager loading
     * @param T[] $items
     */
    public function setLoadedItems(array $items): void
    {
        $this->items = array_values($items);
        $this->loaded = true;
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->items = $this->ids === []
            ? []
            : array_values(($this->modelClass)::read($this->ids));

        $this->loaded = true;
    }

    public function toArray(): array
    {
        $this->ensureLoaded();

        return $this->items;
    }

    // -- Countable ----------------------------------------------------

    public function count(): int
    {
        $this->ensureLoaded();

        return count($this->items);
    }

    // -- ArrayAccess ----------------------------------------------------

    public function offsetExists(mixed $offset): bool
    {
        $this->ensureLoaded();

        return isset($this->items[$offset]);
    }

    /**
     * @return T|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        $this->ensureLoaded();

        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->ensureLoaded();

        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->ensureLoaded();

        unset($this->items[$offset]);
    }

    // -- Iterator ----------------------------------------------------

    /**
     * @return T|null
     */
    public function current(): mixed
    {
        $this->ensureLoaded();

        return $this->items[$this->position] ?? null;
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->ensureLoaded();
        $this->position = 0;
    }

    public function valid(): bool
    {
        $this->ensureLoaded();

        return isset($this->items[$this->position]);
    }
}
