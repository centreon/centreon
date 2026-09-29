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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\Security\Domain\Aggregate\UserId;

final readonly class PatchHostCommand
{
    /**
     * @param ?UserId $viewerId null means the requester is unrestricted (admin); a non-null value
     *                          scopes the host lookup to what that user is allowed to see, so a
     *                          host outside their ACL scope is reported as not found rather than
     *                          leaking its existence
     */
    public function __construct(
        public HostId $id,
        public bool $activated,
        public int $updatedBy,
        public ?UserId $viewerId = null,
    ) {
    }
}
