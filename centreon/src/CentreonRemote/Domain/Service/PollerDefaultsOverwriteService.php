<?php

<<<<<<< HEAD
/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

=======
>>>>>>> centreon/dev-21.10.x
namespace CentreonRemote\Domain\Service;

use CentreonRemote\Domain\Resources\DefaultConfig\CfgNagiosBrokerModule;
use CentreonRemote\Domain\Resources\DefaultConfig\CfgCentreonBrokerInfo;

class PollerDefaultsOverwriteService
{
<<<<<<< HEAD
    /** @var int|null */
    private $pollerID = null;

    /** @var int[] */
    private $brokerConfigIDs = [];

    /** @var int[] */
    private $nagiosConfigIDs = [];

    /**
     * @param int|null $pollerID
     */
    public function setPollerID($pollerID): void
=======

    private $pollerID = null;

    private $brokerConfigIDs = [];

    private $nagiosConfigIDs = [];

    /**
     * @param null $pollerID
     */
    public function setPollerID($pollerID)
>>>>>>> centreon/dev-21.10.x
    {
        $this->pollerID = $pollerID;
    }

    /**
<<<<<<< HEAD
     * @param array<string|int, array<string,int>>  $data - the table data for all pollers
     * @param string $columnName - the name of the column which is FK to the poller
     * @param string $resourceName - the name of the table for which the data is
     *
     * @return array<string|int, array<string,int>>
     */
    private function findPollerAndSetResourceData(array $data, $columnName, $resourceName): array
=======
     * @param array  $data - the table data for all pollers
     * @param string $columnName - the name of the column which is FK to the poller
     * @param string $resourceName - the name of the table for which the data is
     *
     * @return array
     */
    private function findPollerAndSetResourceData(array $data, $columnName, $resourceName)
>>>>>>> centreon/dev-21.10.x
    {
        // Remove remote poller resources in the array by the column name and pollerID
        $data = array_filter($data, function ($pollerData) use ($columnName) {
            return $pollerData[$columnName] != $this->pollerID;
        });

        // Get default data for the specified resource
        $defaultData = $resourceName::getConfiguration();

        // Make the data multidimensional array if its not, so it can be merged
        $dataToMerge = is_array($defaultData[key($defaultData)]) ? $defaultData : [$defaultData];

        // Set the correct pollerID in the column name which is FK to the poller
        foreach ($dataToMerge as $key => $arrayData) {
            $dataToMerge[$key][$columnName] = $this->pollerID;
        }

        return array_merge($data, $dataToMerge);
    }

    /**
     * Get poller information
     *
<<<<<<< HEAD
     * @param array<string|int, array<string,int>> $data the poller data
     * @return array<string|int, array<string,int>> the complete poller data
=======
     * @param array $data the poller data
     * @return array the complete poller data
>>>>>>> centreon/dev-21.10.x
     */
    public function getNagiosServer(array $data): array
    {
        return $this->findPollerAndSetResourceData(
            $data,
            'id',
            'CentreonRemote\Domain\Resources\DefaultConfig\NagiosServer'
        );
    }

    /**
     * Get engine information
     *
<<<<<<< HEAD
     * @param array<string|int, array<string,int>> $data the engine data
     * @return array<string|int, array<string,int>> the complete engine data
=======
     * @param array $data the engine data
     * @return array the complete engine data
>>>>>>> centreon/dev-21.10.x
     */
    public function getCfgNagios(array $data): array
    {
        $configsOfRemote = array_filter($data, function ($pollerData) {
            return $pollerData['nagios_server_id'] == $this->pollerID;
        });
        $this->nagiosConfigIDs = array_column($configsOfRemote, 'nagios_id');

        return $this->findPollerAndSetResourceData(
            $data,
            'nagios_server_id',
            'CentreonRemote\Domain\Resources\DefaultConfig\CfgNagios'
        );
    }

    /**
     * Get engine broker module information
     *
<<<<<<< HEAD
     * @param array<string|int, array<string,int>> $data the engine broker module data
     * @return array<string|int, array<string,int>> the complete engine broker module data
=======
     * @param array $data the engine broker module data
     * @return array the complete engine broker module data
>>>>>>> centreon/dev-21.10.x
     */
    public function getCfgNagiosBroker(array $data): array
    {
        // Remove nagios config info which is related to the broker module of the remote poller
        $data = array_filter($data, function ($pollerData) {
            return !in_array($pollerData['cfg_nagios_id'], $this->nagiosConfigIDs);
        });

        $defaultData = CfgNagiosBrokerModule::getConfiguration();

        return array_merge($defaultData, $data);
    }

    /**
     * Get broker information
     *
<<<<<<< HEAD
     * @param array<string|int, array<string,int>> $data the broker data
     * @return array<string|int, array<string,int>> the complete broker data
=======
     * @param array $data the broker data
     * @return array the complete broker data
>>>>>>> centreon/dev-21.10.x
     */
    public function getCfgCentreonBroker(array $data): array
    {
        $configsOfRemote = array_filter($data, function ($pollerData) {
            return $pollerData['ns_nagios_server'] == $this->pollerID;
        });
        $this->brokerConfigIDs = array_column($configsOfRemote, 'config_id');

        return $this->findPollerAndSetResourceData(
            $data,
            'ns_nagios_server',
            'CentreonRemote\Domain\Resources\DefaultConfig\CfgCentreonBroker'
        );
    }

    /**
     * Get broker detailed information
     *
<<<<<<< HEAD
     * @param array<string|int, array<string,int>> $data the broker detailed data
     * @return array<string|int, array<string,int>> the complete broker detailed data
=======
     * @param array $data the broker detailed data
     * @return array the complete broker detailed data
>>>>>>> centreon/dev-21.10.x
     */
    public function getCfgCentreonBrokerInfo(array $data): array
    {
        // Remove broker config info which is related to the broker module of the remote poller
        $data = array_filter($data, function ($pollerData) {
            return !in_array($pollerData['config_id'], $this->brokerConfigIDs);
        });

        $defaultData = CfgCentreonBrokerInfo::getConfiguration();

        return array_merge($defaultData, $data);
    }

    /**
     * Get global macro information
     *
<<<<<<< HEAD
     * @param array<string|int, array<string,int>> $data the global macro data
     * @return array<string|int, array<string, int>> the complete global macro data
=======
     * @param array $data the global macro data
     * @return array the complete global macro data
>>>>>>> centreon/dev-21.10.x
     */
    public function getCfgResource(array $data): array
    {
        // prepare _instance_id for method findPollerAndSetResourceData
        foreach ($data as $key => $val) {
            $instanceIds = explode(',', $val['_instance_id']);

            if (in_array($this->pollerID, $instanceIds)) {
                $data[$key]['_instance_id'] = $this->pollerID;
            } else {
                $data[$key]['_instance_id'] = '';
            }
        }

        return $this->findPollerAndSetResourceData(
            $data,
            '_instance_id',
            'CentreonRemote\Domain\Resources\DefaultConfig\CfgResource'
        );
    }
}
