<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Common\Domain\Collection;

use Core\Common\Domain\Exception\CollectionException;

/**
 * Class
 *
 * @class      Collection
 * @package    Core\Common\Domain\Collection
 * @template   TItem of object
 * @implements CollectionInterface<TItem>
 */
abstract class Collection implements CollectionInterface
{
    /**
     * Collection constructor
     *
     * @param TItem[] $items Object array to transform in collection
     *
     * @throws CollectionException
     */
    public function __construct(protected array $items = [])
    {
        foreach ($items as $item) {
            $this->validateItem($item);
        }
    }

    /**
     * @return Collection<TItem>
     */
    public function clear(): CollectionInterface
    {
        $this->items = [];

        return $this;
    }

    /**
     * @param TItem $item
     *
     * @return bool
     */
    public function contains($item): bool
    {
        return in_array($item, $this->items, true);
    }

    /**
     * @param int|string $key
     *
     * @throws CollectionException
     * @return TItem
     */
    public function get(int|string $key)
    {
        if (isset($this->items[$key])) {
            return $this->items[$key];
        }

        throw new CollectionException("Key {$key} not found in the collection");
    }

    /**
     * @param int|string $key
     *
     * @return bool
     */
    public function has(int|string $key): bool
    {
        return isset($this->items[$key]);
    }

    /**
     * @return array<int|string>
     */
    public function keys(): array
    {
        return array_keys($this->items);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * @return int
     */
    public function length(): int
    {
        return count($this->items);
    }

    /**
     * @param callable $p
     *
     * @throws CollectionException
     * @return Collection<TItem>
     */
    public function filter(callable $p): CollectionInterface
    {
        return new static(array_filter($this->items, $p));
    }

    /**
     * Merge collections with actual collection. Collections have to be the same of actual.
     *
     * @param CollectionInterface<TItem> ...$collections
     *
     * @throws CollectionException
     * @return Collection<TItem>
     */
    public function mergeWith(CollectionInterface ...$collections): CollectionInterface
    {
        $itemsBackup = $this->items;
        foreach ($collections as $collection) {
            if ($collection::class === static::class) {
                foreach ($collection->all() as $key => $item) {
                    $this->put($key, $item);
                }
            } else {
                // if failure put the collection back in its original state
                $this->items = $itemsBackup;

                throw new CollectionException(
                    'Collections to merge must be instances of ' . $this::class . ', ' . $collection::class . ' given'
                );
            }
        }

        return $this;
    }

    /**
     * @return TItem[]
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Add new item at the collection, if key exists then adding aborted
     *
     * @param int|string $key
     * @param TItem $item
     *
     * @throws CollectionException
     * @return Collection<TItem>
     */
    public function add(int|string $key, $item): CollectionInterface
    {
        if (isset($this->items[$key])) {
            throw new CollectionException("Key {$key} already used in this collection");
        }
        $this->validateItem($item);
        $this->items[$key] = $item;

        return $this;
    }

    /**
     * @param int|string $key
     * @param TItem $item
     *
     * @throws CollectionException
     * @return Collection<TItem>
     */
    public function put(int|string $key, $item): CollectionInterface
    {
        $this->validateItem($item);
        $this->items[$key] = $item;

        return $this;
    }

    /**
     * @param int|string $key
     *
     * @return bool
     */
    public function remove(int|string $key): bool
    {
        if ($this->has($key)) {
            unset($this->items[$key]);

            return true;
        }

        return false;
    }

    /**
     * @return array<TItem>
     */
    public function values(): array
    {
        return array_values($this->items);
    }

    /**
     * @return \Generator<int|string,TItem>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->items as $key => $item) {
            yield $key => $item;
        }
    }

    /**
     * @throws CollectionException
     * @return array<string,mixed>[]
     */
    public function jsonSerialize(): array
    {
        $serializedItems = [];
        foreach ($this->items as $item) {
            if (method_exists($item, 'jsonSerialize')) {
                $serializedItems[] = $item->jsonSerialize();
            } else {
                $serializedItems[] = get_object_vars($item);
            }
        }

        return $serializedItems;
    }

    /**
     * @throws CollectionException
     * @return string
     */
    public function toJson(): string
    {
        $json = json_encode($this->jsonSerialize());
        if (! $json) {

            throw new CollectionException(
                'Stringify the collection to json failed',
                ['serializedCollection' => $this->jsonSerialize()]
            );
        }

        return $json;
    }

    /**
     * @param TItem $item
     *
     * @throws CollectionException
     * @return void
     */
    protected function validateItem($item): void
    {
        $class = $this->itemClass();
        if (! $item instanceof $class) {

            throw new CollectionException(
                'Item must be an instance of ' . $class . ', ' . $item::class . ' given'
            );
        }
    }

    /**
     * Return the name of item class in the collection
     *
     * @return class-string<TItem>
     */
    abstract protected function itemClass(): string;
}
