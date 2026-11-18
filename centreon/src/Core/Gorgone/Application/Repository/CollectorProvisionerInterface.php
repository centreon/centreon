<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Application\Repository;

/**
 * Pollers of collectors, created the way the poller wizard creates them.
 */
interface CollectorProvisionerInterface
{
    /**
     * Create a poller linked to the central, run by centreon-engine: platform topology, engine and
     * broker configuration (its module sends to $brokerAddress), as the poller wizard does.
     *
     * @param string $name
     * @param string $address
     * @param string $brokerAddress address of the central broker input
     * @param int $creatorId
     * @param string $creatorName
     *
     * @throws \Throwable
     *
     * @return int poller id, which is its Gorgone node id
     */
    public function createPoller(
        string $name,
        string $address,
        string $brokerAddress,
        int $creatorId,
        string $creatorName,
    ): int;

    /**
     * Mark an existing poller as run by centreon-engine.
     *
     * @param int $pollerId
     */
    public function markRunByCentreonEngine(int $pollerId): void;

    /**
     * @return list<array{id: int, name: string, address: string}> every poller
     */
    public function findPollers(): array;

    /**
     * @return string|null address of the central in the platform topology
     */
    public function findCentralAddress(): ?string;

    /**
     * @throws \Throwable when there is none
     *
     * @return array{int, string} id and alias of an administrator
     */
    public function findAdministrator(): array;
}
