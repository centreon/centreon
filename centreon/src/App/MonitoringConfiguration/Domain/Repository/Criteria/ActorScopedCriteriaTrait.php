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
 * to see. It fails closed: a criteria must either be scoped to an actor or explicitly opt out, so a
 * forgotten scoping cannot silently return every resource.
 */
trait ActorScopedCriteriaTrait
{
    private ?ActingUser $actor = null;

    private bool $accessScoped = true;

    public function withActor(ActingUser $actor): self
    {
        $new = clone $this;
        $new->actor = $actor;
        $new->accessScoped = true;

        return $new;
    }

    /**
     * For a read that must see every resource whoever asks, as a listing legacy never scoped.
     */
    public function withoutAccessScope(): self
    {
        $new = clone $this;
        $new->actor = null;
        $new->accessScoped = false;

        return $new;
    }

    /**
     * The scope the results are restricted to: what the actor may access, as `$resolve` tells, or every
     * resource once {@see withoutAccessScope()} was called.
     *
     * @template T of AggregateRootId
     *
     * @param \Closure(ActingUser): AccessScope<T> $resolve
     * @param class-string<T> $idClass
     *
     * @throws \LogicException when neither withActor() nor withoutAccessScope() was called
     *
     * @return AccessScope<T>
     */
    public function accessScope(\Closure $resolve, string $idClass): AccessScope
    {
        if (! $this->accessScoped) {
            return AccessScope::unrestricted($idClass);
        }

        if (! $this->actor instanceof ActingUser) {
            throw new \LogicException(sprintf('"%s" must be scoped with withActor(), or explicitly opt out with withoutAccessScope().', static::class));
        }

        return $resolve($this->actor);
    }
}
