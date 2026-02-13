<?php

namespace Concept7\Kite\Support;

use ArrayAccess;
use Countable;

class Collection implements ArrayAccess, Countable
{
    public function __construct(
        protected array $items = [],
    ) {}

    public function push(mixed $value): self
    {
        $this->items[] = $value;

        return $this;
    }

    public function filter(?callable $callback = null): self
    {
        return new self(array_filter($this->items, $callback));
    }

    public function values(): self
    {
        return new self(array_values($this->items));
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
