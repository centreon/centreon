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

namespace App\Shared\Domain\Aggregate;

use Webmozart\Assert\Assert;

/**
 * @template T of object
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Collection implements \IteratorAggregate, \Countable
{
    /**
     * @var array<T>|null
     */
    private ?array $elements = null;

    /**
     * @param array<T>|\Closure(): void $elements
     * @param class-string<T>           $className
     */
    public function __construct(
        array|\Closure $elements,
        private readonly string $className,
    ) {
        if (\is_array($elements)) {
            $this->elements = $elements;
        }
    }

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable
    {
        $this->initialize();

        return new \ArrayIterator($this->elements);
    }

    public function count(): int
    {
        $this->initialize();

        return \count($this->elements);
    }

    /**
     * @return array<T>
     */
    public function toArray(): array
    {
        $this->initialize();

        return $this->elements;
    }

    /**
     * @param T $element
     */
    public function contains(object $element): bool
    {
        return in_array($element, $this->elements, true);
    }

    /**
     * @param T $element
     */
    public function add(object $element): void
    {
        Assert::isInstanceOf($element, $this->className);

        $this->elements[] = $element;
    }

    public function remove(string|int $key): void
    {
        if (!isset($this->elements[$key]) && !\array_key_exists($key, $this->elements)) {
            return;
        }

        $removed = $this->elements[$key];

        unset($this->elements[$key]);
    }

    /**
     * @param T $element
     */
    public function removeElement(object $element): void
    {
        $key = array_search($element, $this->elements, true);

        if (false === $key) {
            return;
        }

        $this->remove($key);
    }

    private function initialize(): void
    {
        if (null !== $this->elements) {
            return;
        }

        $elements = ($this->initializer)();

        foreach ($elements as $element) {
            Assert::isInstanceOf($element, $this->className);
        }

        $this->elements = $elements;
    }
}
