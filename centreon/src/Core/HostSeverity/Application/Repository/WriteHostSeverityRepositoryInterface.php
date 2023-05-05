<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\HostSeverity\Application\Repository;

use Core\HostSeverity\Domain\Model\HostSeverity;
use Core\HostSeverity\Domain\Model\NewHostSeverity;

interface WriteHostSeverityRepositoryInterface
{
    /**
     * Delete host severity by id.
     *
     * @param int $hostSeverityId
     */
    public function deleteById(int $hostSeverityId): void;

    /**
     * Add a host severity
     * Return the id of the host severity.
     *
     * @param NewHostSeverity $hostSeverity
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewHostSeverity $hostSeverity): int;

    /**
     * Update a host severity.
     *
     * @param HostSeverity $hostSeverity
     *
     * @throws \Throwable
     */
    public function update(HostSeverity $hostSeverity): void;
}
