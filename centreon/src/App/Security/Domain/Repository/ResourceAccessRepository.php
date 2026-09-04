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

namespace App\Security\Domain\Repository;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;

interface ResourceAccessRepository
{
    public function hasAccessToAllPollers(UserId $userId): bool;

    public function hasAccessToPoller(PollerId $pollerId, UserId $userId): bool;

    /**
     * @return Collection<PollerId>|null null means no restriction applies (the user can access
     *                                   all pollers); an empty Collection means the user can access none
     */
    public function findAccessiblePollerIds(UserId $userId): ?Collection;
}
