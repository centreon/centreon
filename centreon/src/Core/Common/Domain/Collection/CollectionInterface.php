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
 * Interface
 *
 * @class    CollectionInterface
 * @package  Core\Common\Domain\Collection
 * @template TItem
 * @extends \IteratorAggregate<string|int,TItem>
 */
interface CollectionInterface extends \IteratorAggregate, \JsonSerializable
{
    /**
     * Return all items of collection
     *
     * @return TItem[]
     */
    public function toArray(): array;

    /**
     * Delete all items of collection
     *
     * @return CollectionInterface<TItem>
     */
    public function clear(): self;

    /**
     * @param TItem $item
     *
     * @return bool
     */
    public function contains($item): bool;

    /**
     * @param int|string $key
     *
     * @throws CollectionException
     * @return TItem
     */
    public function get(int|string $key): mixed;

    /**
     * @param int|string $key
     *
     * @return bool
     */
    public function has(int|string $key): bool;

    /**
     * Return an array with the keys of collection
     *
     * @return array<int|string>
     */
    public function keys(): array;

    /**
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Return the number of items of collection
     *
     * @return int
     */
    public function length(): int;

    /**
     * @param TItem $item
     *
     * @throws CollectionException
     * @return int|string
     */
    public function indexOf(mixed $item): int|string;

    /**
     * @param callable $callable
     *
     * @return true
     */
    public function sortByValues(callable $callable): true;

    /**
     * @param callable $callable
     *
     * @return true
     */
    public function sortByKeys(callable $callable): true;

    /**
     * Filter the collection by a callable using the item as parameter
     *
     * @param callable $callable
     *
     * @throws CollectionException
     * @return CollectionInterface<TItem>
     *
     * @example $collection = new Collection([1, 2, 3, 4, 5]);
     *          $filteredCollection = $collection->filterValues(fn($item) => $item > 2);
     *          // $filteredCollection = [3, 4, 5]
     */
    public function filterOnValue(callable $callable): self;

    /**
     * Filter the collection by a callable using the key as parameter
     *
     * @param callable $callable
     *
     * @throws CollectionException
     * @return CollectionInterface<TItem>
     *
     * @example $collection = new Collection(['key1' => 'foo', 'key2' => 'bar']);
     *         $filteredCollection = $collection->filterKeys(fn($key) => $key === 'key1');
     *        // $filteredCollection = ['key1' => 'foo']
     */
    public function filterOnKey(callable $callable): self;

    /**
     * Filter the collection by a callable using the item and key as parameters
     *
     * @param callable $callable
     *
     * @throws CollectionException
     * @return CollectionInterface<TItem>
     *
     * @example $collection = new Collection(['key1' => 'foo', 'key2' => 'bar']);
     *        $filteredCollection = $collection->filterValueKey(fn($item, $key) => $key === 'key1' && $item === 'foo');
     *       // $filteredCollection = ['key1' => 'foo']
     */
    public function filterOnValueKey(callable $callable): self;

    /**
     * Merge collections with actual collection. Collections must to be the same of actual.
     *
     * @param CollectionInterface<TItem> ...$collections
     *
     * @throws CollectionException
     * @return CollectionInterface<TItem>
     */
    public function mergeWith(self ...$collections): self;

    /**
     * @param int|string $key
     * @param TItem $item
     *
     * @throws CollectionException
     * @return CollectionInterface<TItem>
     */
    public function add(int|string $key, mixed $item): self;

    /**
     * @param int|string $key
     * @param TItem $item
     *
     * @throws CollectionException
     * @return CollectionInterface<TItem>
     */
    public function put(int|string $key, mixed $item): self;

    /**
     * @param int|string $key
     *
     * @return bool
     */
    public function remove(int|string $key): bool;

    /**
     * Return an array of items of collection
     *
     * @return TItem[]
     */
    public function values(): array;

    /**
     * @throws CollectionException
     * @return string
     */
    public function toJson(): string;

    /**
     * @return \Traversable<string|int,TItem>
     */
    public function getIterator(): \Traversable;
}
