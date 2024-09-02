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

namespace Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6;

class VmWareConfig
{
    /**
     * @param VSphereServer[] $vSphereServers
     * @param int $port
     */
    public function __construct(private readonly array $vSphereServers, private readonly int $port)
    {
    }

    /**
     * @return VSphereServer[]
     */
    public function getVSphereServers(): array
    {
        return $this->vSphereServers;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
