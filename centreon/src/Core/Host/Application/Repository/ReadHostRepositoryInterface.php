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

namespace Core\Host\Application\Repository;

use Core\Host\Domain\Model\Host;

interface ReadHostRepositoryInterface
{
    /**
     * Determine if a host exists by its name.
     * (include both host templates and hosts names).
     *
     * @param string $hostName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(string $hostName): bool;

    /**
     * Find a host by its id.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return ?Host
     */
    public function findById(int $hostId): ?Host;

    /**
     * Retrieve all parent template ids of a host.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return array<array{parent_id:int,child_id:int,order:int}>
     */
    public function findParents(int $hostId): array;
}
