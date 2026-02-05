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

namespace Centreon\Domain\Service;

use Centreon\Domain\Repository\Interfaces\CfgCentreonBrokerInfoInterface;

/**
 * Service to manage broker flows configuration
 */
class BrokerConfigurationService
{
    /** @var CfgCentreonBrokerInfoInterface */
    private $brokerInfoRepository;

    /**
     * Set broker infos repository to manage flows (input, output, log...)
     *
     * @param CfgCentreonBrokerInfoInterface $cfgCentreonBrokerInfo the broker info repository
     */
    public function setBrokerInfoRepository(CfgCentreonBrokerInfoInterface $cfgCentreonBrokerInfo): void
    {
        $this->brokerInfoRepository = $cfgCentreonBrokerInfo;
    }

    /**
     * Add flow (input, output, log...)
     *
     * @param int $configId the config id to update
     * @param string $configGroup the config group to add (input, output...)
     * @param \Centreon\Domain\Entity\CfgCentreonBrokerInfo[] $brokerInfoEntities the flow parameters to insert
     */
    public function addFlow(int $configId, string $configGroup, array $brokerInfoEntities): void
    {
        // get new input config group id on central broker configuration
        // to add new IPv4 input
        $configGroupId = $this->brokerInfoRepository->getNewConfigGroupId($configId, $configGroup);

        // insert each line of configuration in database thanks to BrokerInfoEntity
        foreach ($brokerInfoEntities as $brokerInfoEntity) {
            $brokerInfoEntity->setConfigId($configId);
            $brokerInfoEntity->setConfigGroup($configGroup);
            $brokerInfoEntity->setConfigGroupId($configGroupId);
            $this->brokerInfoRepository->add($brokerInfoEntity);
        }
    }
}
