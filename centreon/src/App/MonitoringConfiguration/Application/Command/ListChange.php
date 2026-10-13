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

namespace App\MonitoringConfiguration\Application\Command;

/**
 * How a partial update changes a list: it replaces the whole list, adds to it, or removes from it.
 *
 * @template T
 */
final readonly class ListChange
{
    /**
     * @param list<T> $values
     */
    private function __construct(
        public ListChangeModeEnum $mode,
        public array $values,
    ) {
    }

    /**
     * @template U
     *
     * @param list<U> $values
     *
     * @return self<U>
     */
    public static function replace(array $values): self
    {
        return new self(ListChangeModeEnum::Replace, $values);
    }

    /**
     * @template U
     *
     * @param list<U> $values
     *
     * @return self<U>
     */
    public static function add(array $values): self
    {
        return new self(ListChangeModeEnum::Add, $values);
    }

    /**
     * @template U
     *
     * @param list<U> $values
     *
     * @return self<U>
     */
    public static function remove(array $values): self
    {
        return new self(ListChangeModeEnum::Remove, $values);
    }

    /**
     * What the list becomes. Order is kept: an added value goes after the current ones, and a
     * replacement is in the order it gives. A value is never repeated.
     *
     * $isVisible tells the values the requester can see: the others are out of their scope, so a
     * replacement keeps them, and a removal leaves them alone. Null means they all can.
     *
     * @param list<T> $current
     * @param (\Closure(T): mixed)|null $identity what two values share when they are the same one; the
     *                                            value itself when null
     * @param (\Closure(T): bool)|null $isVisible
     *
     * @return list<T>
     */
    public function applyTo(array $current, ?\Closure $identity = null, ?\Closure $isVisible = null): array
    {
        $identity ??= static fn (mixed $value): mixed => $value;
        $isVisible ??= static fn (mixed $value): bool => true;

        $result = match ($this->mode) {
            ListChangeModeEnum::Replace => [
                ...$this->values,
                ...array_filter($current, static fn (mixed $value): bool => ! $isVisible($value)),
            ],
            ListChangeModeEnum::Add => [...$current, ...$this->values],
            ListChangeModeEnum::Remove => array_filter(
                $current,
                fn (mixed $value): bool => ! $isVisible($value) || ! $this->contains($value, $identity),
            ),
        };

        return $this->withoutRepeats(array_values($result), $identity);
    }

    /**
     * @param T $value
     * @param \Closure(T): mixed $identity
     */
    private function contains(mixed $value, \Closure $identity): bool
    {
        return in_array($identity($value), array_map($identity, $this->values), true);
    }

    /**
     * @param list<T> $values
     * @param \Closure(T): mixed $identity
     *
     * @return list<T>
     */
    private function withoutRepeats(array $values, \Closure $identity): array
    {
        $kept = [];
        $seen = [];
        foreach ($values as $value) {
            $key = $identity($value);
            if (in_array($key, $seen, true)) {
                continue;
            }

            $seen[] = $key;
            $kept[] = $value;
        }

        return $kept;
    }
}
