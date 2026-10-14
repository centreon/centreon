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

use App\Security\Domain\Aggregate\ActingUser;

/**
 * ACL scoping shared by the listing criteria that restrict results to what the acting user is allowed
 * to see. Without an actor, the criteria carry no ACL scoping at all.
 */
trait ActorScopedCriteriaTrait
{
    private ?ActingUser $actor = null;

    public function withActor(ActingUser $actor): self
    {
        $new = clone $this;
        $new->actor = $actor;

        return $new;
    }

    public function getActor(): ?ActingUser
    {
        return $this->actor;
    }
}
