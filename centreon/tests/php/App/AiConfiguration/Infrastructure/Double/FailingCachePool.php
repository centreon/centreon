<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\App\AiConfiguration\Infrastructure\Double;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * A cache that can not be read (it throws) or that can not save anything (it reports
 * the failure, or throws), as a broken cache backend does.
 */
final readonly class FailingCachePool implements CacheItemPoolInterface
{
    private ArrayAdapter $items;

    public function __construct(private bool $failsToRead, private bool $throwsOnSave = false)
    {
        $this->items = new ArrayAdapter();
    }

    /**
     * @throws InvalidArgumentException when the cache fails to read
     */
    public function getItem(string $key): CacheItemInterface
    {
        if ($this->failsToRead) {
            throw new InvalidArgumentException('The cache can not be read');
        }

        return $this->items->getItem($key);
    }

    /**
     * @param array<string> $keys
     *
     * @throws InvalidArgumentException when the cache fails to read
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        if ($this->failsToRead) {
            throw new InvalidArgumentException('The cache can not be read');
        }

        return $this->items->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return false;
    }

    public function clear(): bool
    {
        return true;
    }

    public function deleteItem(string $key): bool
    {
        return true;
    }

    public function deleteItems(array $keys): bool
    {
        return true;
    }

    /**
     * @throws InvalidArgumentException when the cache throws on save
     */
    public function save(CacheItemInterface $item): bool
    {
        if ($this->throwsOnSave) {
            throw new InvalidArgumentException('The cache can not be written');
        }

        return false;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return false;
    }

    public function commit(): bool
    {
        return false;
    }
}
