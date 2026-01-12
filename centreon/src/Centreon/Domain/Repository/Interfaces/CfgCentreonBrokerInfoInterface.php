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

namespace Centreon\Domain\Repository\Interfaces;

use Centreon\Domain\Entity\CfgCentreonBrokerInfo;

interface CfgCentreonBrokerInfoInterface
{
    /**
     * Get new config group id by config id for a specific flow
     * once the config group is got from this method, it is possible to insert a new flow in the broker configuration
     *
     * @param int $configId the broker configuration id
     * @param string $flow the flow type : input, output, log...
     * @return int the new config group id
     */
    public function getNewConfigGroupId(int $configId, string $flow): int;

    /**
     * Insert broker configuration in database (table cfg_centreonbroker_info)
     *
     * @param CfgCentreonBrokerInfo $cfgCentreonBrokerInfo the broker info entity
     */
    public function add(CfgCentreonBrokerInfo $cfgCentreonBrokerInfo): void;
}
