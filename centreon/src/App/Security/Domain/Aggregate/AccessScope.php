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

namespace App\Security\Domain\Aggregate;

use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * The resources of one kind a user may access: every one of them, or only some, by id.
 *
 * @template T of AggregateRootId
 */
final readonly class AccessScope
{
    /**
     * @param class-string<T> $idClass
     * @param array<int, true> $allowedIdValues the allowed id values, as keys for constant-time lookups
     */
    private function __construct(
        private string $idClass,
        private bool $unrestricted,
        private array $allowedIdValues,
        private bool $partial = false,
    ) {
    }

    /**
     * @template U of AggregateRootId
     *
     * @param class-string<U> $idClass
     *
     * @return self<U>
     */
    public static function unrestricted(string $idClass): self
    {
        return new self($idClass, unrestricted: true, allowedIdValues: []);
    }

    /**
     * @template U of AggregateRootId
     *
     * @param class-string<U> $idClass
     * @param iterable<U> $ids
     *
     * @return self<U>
     */
    public static function restrictedTo(string $idClass, iterable $ids): self
    {
        $allowedIdValues = [];
        foreach ($ids as $id) {
            Assert::isInstanceOf($id, $idClass);
            $allowedIdValues[$id->value] = true;
        }

        return new self($idClass, unrestricted: false, allowedIdValues: $allowedIdValues);
    }

    /**
     * A restricted scope looked up among some ids only: it answers for them, but cannot list what it allows
     * nor tell whether it allows nothing, since the ids it was not looked up among are unknown to it.
     *
     * @template U of AggregateRootId
     *
     * @param class-string<U> $idClass
     * @param iterable<U> $ids the allowed ids among the looked up ones
     *
     * @return self<U>
     */
    public static function partiallyRestrictedTo(string $idClass, iterable $ids): self
    {
        $scope = self::restrictedTo($idClass, $ids);

        return new self($idClass, unrestricted: false, allowedIdValues: $scope->allowedIdValues, partial: true);
    }

    public function isUnrestricted(): bool
    {
        return $this->unrestricted;
    }

    /**
     * Whether the scope is restricted to nothing at all.
     *
     * @throws \LogicException for a partial scope, which only knows the ids it was looked up among
     */
    public function allowsNothing(): bool
    {
        $this->assertComplete();

        return ! $this->unrestricted && $this->allowedIdValues === [];
    }

    /**
     * @param T $id
     */
    public function allows(AggregateRootId $id): bool
    {
        return $this->unrestricted || isset($this->allowedIdValues[$id->value]);
    }

    /**
     * The requested ids that cannot be referenced: the unknown ones, missing from `$existingIdValues`, and the
     * ones out of the scope, together so that a restricted user cannot tell them apart.
     *
     * @param iterable<T> $requested
     * @param list<int|string> $existingIdValues
     *
     * @return list<int>
     */
    public function missing(iterable $requested, array $existingIdValues): array
    {
        $existing = array_flip(array_map(intval(...), $existingIdValues));

        $missing = [];
        foreach ($requested as $id) {
            if (! isset($existing[$id->value]) || ! $this->allows($id)) {
                $missing[$id->value] = $id->value;
            }
        }

        return array_values($missing);
    }

    /**
     * @param Collection<T> $ids
     *
     * @return Collection<T>
     */
    public function filter(Collection $ids): Collection
    {
        if ($this->unrestricted) {
            return $ids;
        }

        return new Collection(array_values(array_filter($ids->toArray(), $this->allows(...))), $this->idClass);
    }

    /**
     * The values of the ids a restricted scope allows, for a query to filter on.
     *
     * @throws \LogicException for an unrestricted scope, which has no such list
     * @throws \LogicException for a partial scope, which only knows the ids it was looked up among
     *
     * @return list<int>
     */
    public function allowedIdValues(): array
    {
        if ($this->unrestricted) {
            throw new \LogicException('An unrestricted scope allows every id: check isUnrestricted() first.');
        }

        $this->assertComplete();

        return array_keys($this->allowedIdValues);
    }

    private function assertComplete(): void
    {
        if ($this->partial) {
            throw new \LogicException('A partial scope only knows the ids it was looked up among: it cannot list or count what it allows.');
        }
    }
}
