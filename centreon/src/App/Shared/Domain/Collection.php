<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace App\Shared\Domain;

use Webmozart\Assert\Assert;

/**
 * @template T of object
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Collection implements \IteratorAggregate, \Countable
{
    /** @var array<T>|null */
    private ?array $elements = null;

    /** @var (\Closure(): array<T>)|null */
    private ?\Closure $initializer = null;

    /** @var \Closure(T, T): bool */
    private readonly \Closure $compare;

    /**
     * @param array<T>|\Closure(): array<T> $elements
     * @param class-string<T> $className
     * @param null|\Closure(T, T): bool $compare
     */
    public function __construct(
        array|\Closure $elements,
        private readonly string $className,
        ?\Closure $compare = null,
    ) {
        $this->compare = $compare ?? static fn (object $self, object $other): bool => $self === $other;

        if (\is_array($elements)) {
            foreach ($elements as $element) {
                Assert::isInstanceOf($element, $this->className);
            }

            $this->elements = $elements;
        } else {
            $this->initializer = $elements;
        }
    }

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable
    {
        $this->initialize();
        Assert::notNull($this->elements);

        return new \ArrayIterator($this->elements);
    }

    public function count(): int
    {
        $this->initialize();
        Assert::notNull($this->elements);

        return \count($this->elements);
    }

    /**
     * @return array<T>
     */
    public function toArray(): array
    {
        $this->initialize();
        Assert::notNull($this->elements);

        return $this->elements;
    }

    /**
     * @param T $element
     */
    public function contains(object $element): bool
    {
        $this->initialize();
        Assert::notNull($this->elements);

        foreach ($this->elements as $e) {
            if (($this->compare)($e, $element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param T $element
     */
    public function add(object $element): void
    {
        Assert::isInstanceOf($element, $this->className);

        $this->initialize();
        Assert::notNull($this->elements);

        $this->elements[] = $element;
    }

    public function remove(string|int $key): void
    {
        $this->initialize();
        Assert::notNull($this->elements);

        if (! isset($this->elements[$key]) && ! \array_key_exists($key, $this->elements)) {
            return;
        }

        unset($this->elements[$key]);
    }

    /**
     * @param T $element
     */
    public function removeElement(object $element): void
    {
        $this->initialize();
        Assert::notNull($this->elements);

        foreach ($this->elements as $key => $e) {
            if (($this->compare)($e, $element)) {
                $this->remove($key);

                return;
            }
        }
    }

    private function initialize(): void
    {
        if ($this->elements !== null || ! $this->initializer instanceof \Closure) {
            return;
        }

        $elements = ($this->initializer)();

        foreach ($elements as $element) {
            Assert::isInstanceOf($element, $this->className);
        }

        $this->elements = $elements;
    }
}
