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
 * @class ObjectCollection
 * @package Core\Common\Domain\Collection
 * @template TItem
 * @extends Collection<TItem>
 * @phpstan-consistent-constructor
 */
abstract class ObjectCollection extends Collection
{
    /**
     * @throws CollectionException
     * @return array<int|string,mixed>
     */
    public function jsonSerialize(): array
    {
        $serializedItems = [];
        foreach ($this->items as $key => $item) {
            if (method_exists($item, 'jsonSerialize')) {
                $serializedItems[$key] = $item->jsonSerialize();
            } else {
                $serializedItems[$key] = get_object_vars($item);
            }
        }

        return $serializedItems;
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
                sprintf('Item must be an instance of %s, %s given', $class, $item::class)
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
