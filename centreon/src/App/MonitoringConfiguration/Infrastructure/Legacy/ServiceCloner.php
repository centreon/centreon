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

namespace App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\Security\Domain\Aggregate\UserId;

/**
 * Internal seam of the legacy host-service duplication adapter: the exclusive-service clone has no
 * new-architecture equivalent yet, so it is delegated to the legacy procedural step. It lives in
 * Infrastructure because only {@see LegacyHostServiceDuplicatorWrapper} consumes it (its contract even
 * refers to the legacy session); no Domain or Application code depends on it.
 */
interface ServiceCloner
{
    /**
     * Clones the services exclusive to the source host onto the freshly duplicated host.
     *
     * $duplicatedBy is the acting user: the cloned services take their author and ACL scope from the
     * ambient legacy session, so when the request carries none (e.g. token-authenticated) a session is
     * rebuilt from this user for the clone to run either way.
     *
     * @param list<int> $serviceIds services exclusive to the source, to be cloned onto the copy
     *
     * @throws \Throwable
     */
    public function cloneServices(array $serviceIds, HostId $newHostId, UserId $duplicatedBy): void;
}
