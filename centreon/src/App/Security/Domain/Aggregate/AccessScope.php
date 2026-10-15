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

/**
 * The resources of one kind a user may access: every one of them, or only some, by id.
 *
 * @template T of AggregateRootId
 */
final readonly class AccessScope
{
    /**
     * @param class-string<T> $idClass
     * @param list<int> $allowedIdValues
     */
    private function __construct(
        private string $idClass,
        private bool $unrestricted,
        private array $allowedIdValues,
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
        $idValues = [];
        foreach ($ids as $id) {
            $idValues[] = $id->value;
        }

        return new self($idClass, unrestricted: false, allowedIdValues: array_values(array_unique($idValues)));
    }

    public function isUnrestricted(): bool
    {
        return $this->unrestricted;
    }

    /**
     * @param T $id
     */
    public function allows(AggregateRootId $id): bool
    {
        return $this->unrestricted || in_array($id->value, $this->allowedIdValues, true);
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
     *
     * @return list<int>
     */
    public function allowedIdValues(): array
    {
        if ($this->unrestricted) {
            throw new \LogicException('An unrestricted scope allows every id: check isUnrestricted() first.');
        }

        return $this->allowedIdValues;
    }
}
