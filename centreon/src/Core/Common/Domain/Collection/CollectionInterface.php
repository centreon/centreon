<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
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

/**
 * Interface.
 *
 * @class    CollectionInterface
 *
 * @template TItem
 *
 * @extends \IteratorAggregate<string|int,TItem>
 */
interface CollectionInterface extends \IteratorAggregate, \JsonSerializable
{
    /**
     * Return all items of collection.
     *
     * @return TItem[]
     */
    public function all(): array;

    /**
     * Delete all items of collection.
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
     * @return TItem
     */
    public function get(int|string $key);

    /**
     * @param int|string $key
     *
     * @return bool
     */
    public function has(int|string $key): bool;

    /**
     * Return an array with the keys of collection.
     *
     * @return array<int|string>
     */
    public function keys(): array;

    /**
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Return the number of items of collection.
     *
     * @return int
     */
    public function length(): int;

    /**
     * @param callable $callable
     *
     * @return CollectionInterface<TItem>
     */
    public function filter(callable $callable): self;

    /**
     * Merge collections with actual collection. Collections must to be the same of actual.
     *
     * @param CollectionInterface<TItem> ...$collections
     *
     * @return CollectionInterface<TItem>
     */
    public function mergeWith(self ...$collections): self;

    /**
     * @param int|string $key
     * @param TItem $item
     *
     * @return CollectionInterface<TItem>
     */
    public function add(int|string $key, mixed $item): self;

    /**
     * @param int|string $key
     * @param TItem $item
     *
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
     * Return an array of items of collection.
     *
     * @return TItem[]
     */
    public function values(): array;

    /**
     * @return string
     */
    public function toJson(): string;

    /**
     * @return \Traversable<string|int,TItem>
     */
    public function getIterator(): \Traversable;
}
