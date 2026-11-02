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

namespace App\MonitoringConfiguration\Domain\Repository\Criteria;

use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Aggregate\AggregateRootId;

/**
 * ACL scoping shared by the listing criteria that restrict results to what the acting user is allowed
 * to see. It fails closed by construction: a criteria is either scoped to an actor with {@see forActor()}
 * or explicitly opts out with {@see unscoped()}, so a forgotten scoping cannot silently return every
 * resource.
 */
trait ActorScopedCriteriaTrait
{
    /**
     * @param ActingUser|null $actor null only for an {@see unscoped()} criteria
     */
    private function __construct(
        private readonly ?ActingUser $actor,
    ) {
    }

    public static function forActor(ActingUser $actor): static
    {
        return new static($actor);
    }

    /**
     * For a read that must see every resource whoever asks, as a listing legacy never scoped.
     */
    public static function unscoped(): static
    {
        return new static(null);
    }

    /**
     * The scope the results are restricted to: what the actor may access, as `$resolve` tells, or every
     * resource for an {@see unscoped()} criteria.
     *
     * @template T of AggregateRootId
     *
     * @param \Closure(ActingUser): AccessScope<T> $resolve
     * @param class-string<T> $idClass
     *
     * @return AccessScope<T>
     */
    public function accessScope(\Closure $resolve, string $idClass): AccessScope
    {
        return $this->actor instanceof ActingUser ? $resolve($this->actor) : AccessScope::unrestricted($idClass);
    }
}
