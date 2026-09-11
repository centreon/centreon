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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;

final readonly class CreateHostCommand
{
    /**
     * @param Collection<HostGroupId> $hostGroupIds
     * @param ?UserId $viewerId null means the creator is unrestricted (admin); a non-null value
     *                          scopes the poller/host-group existence checks to what that user
     *                          can access, mirroring `HostCriteria::withViewerId()` on the read side
     */
    public function __construct(
        public HostName $name,
        public HostAddress $address,
        public PollerId $pollerId,
        public Collection $hostGroupIds,
        public int $creatorId,
        public ?UserId $viewerId = null,
    ) {
    }
}
