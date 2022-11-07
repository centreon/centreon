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

namespace CentreonRemote\Domain\Service\ConfigurationWizard;

use CentreonRemote\Domain\Resources\RemoteConfig\CfgCentreonBroker;
use CentreonRemote\Domain\Resources\DefaultConfig\CfgCentreonBrokerLog;
=======
namespace CentreonRemote\Domain\Service\ConfigurationWizard;

use CentreonRemote\Domain\Resources\RemoteConfig\CfgCentreonBroker;
>>>>>>> centreon/dev-21.10.x
use CentreonRemote\Domain\Resources\RemoteConfig\CfgCentreonBrokerInfo;

class RemoteConnectionConfigurationService extends ServerConnectionConfigurationService
{
<<<<<<< HEAD
    /**
     * @inheritDoc
     */
    protected function isRemote(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function insertConfigCentreonBroker(int $serverID): void
    {
        $brokerConfiguration = CfgCentreonBroker::getConfiguration($serverID, $this->name);
        $brokerInfoConfiguration = CfgCentreonBrokerInfo::getConfiguration(
=======

    protected function insertConfigCentreonBroker(int $serverID): void
    {
        $configCentreonBrokerData = CfgCentreonBroker::getConfiguration($serverID, $this->name);
        $configCentreonBrokerInfoData = CfgCentreonBrokerInfo::getConfiguration(
>>>>>>> centreon/dev-21.10.x
            $this->name,
            $this->dbUser,
            $this->dbPassword
        );

<<<<<<< HEAD
        $this->brokerID = (int) $this->insertWithAdapter('cfg_centreonbroker', $brokerConfiguration['broker']);
        $moduleID = (int) $this->insertWithAdapter('cfg_centreonbroker', $brokerConfiguration['module']);
        $rrdID = (int) $this->insertWithAdapter('cfg_centreonbroker', $brokerConfiguration['rrd']);

        $this->insertBrokerLog(
            CfgCentreonBrokerLog::getConfiguration(
                $this->getDbAdapter()->getCentreonDBInstance(),
                $this->brokerID
            )
        );
        $this->insertBrokerLog(
            CfgCentreonBrokerLog::getConfiguration(
                $this->getDbAdapter()->getCentreonDBInstance(),
                $moduleID
            )
        );
        $this->insertBrokerLog(
            CfgCentreonBrokerLog::getConfiguration(
                $this->getDbAdapter()->getCentreonDBInstance(),
                $rrdID
            )
        );

        $this->insertBrokerInfo($this->brokerID, $brokerInfoConfiguration['central-broker']);
        $this->insertBrokerInfo($moduleID, $brokerInfoConfiguration['central-module']);
        $this->insertBrokerInfo($rrdID, $brokerInfoConfiguration['central-rrd']);
    }

    /**
     * insert broker information
     *
     * @param int $configurationId
     * @param array<string,array<string,mixed> $brokerInfo
     */
    private function insertBrokerInfo(int $configurationId, array $brokerInfo): void
    {
        foreach ($brokerInfo as $brokerConfig => $brokerData) {
            foreach ($brokerData as $row) {
                $row['config_id'] = $configurationId;

                if ($brokerConfig === 'output_forward' && $row['config_key'] === 'host') {
                    $row['config_value'] = $this->centralIp;
                }

=======
        $this->brokerID = $this->insertWithAdapter('cfg_centreonbroker', $configCentreonBrokerData['broker']);
        $moduleID = $this->insertWithAdapter('cfg_centreonbroker', $configCentreonBrokerData['module']);
        $rrdID = $this->insertWithAdapter('cfg_centreonbroker', $configCentreonBrokerData['rrd']);

        foreach ($configCentreonBrokerInfoData['central-broker'] as $brokerConfig => $brokerData) {
            foreach ($brokerData as $row) {
                if ($brokerConfig == 'output_forward' && $row['config_key'] == 'host') {
                    $row['config_value'] = $this->centralIp;
                }

                $row['config_id'] = $this->brokerID;
                $this->insertWithAdapter('cfg_centreonbroker_info', $row);
            }
        }

        foreach ($configCentreonBrokerInfoData['central-module'] as $brokerConfig => $brokerData) {
            foreach ($brokerData as $row) {
                $row['config_id'] = $moduleID;
                $this->insertWithAdapter('cfg_centreonbroker_info', $row);
            }
        }

        foreach ($configCentreonBrokerInfoData['central-rrd'] as $brokerConfig => $brokerData) {
            foreach ($brokerData as $row) {
                $row['config_id'] = $rrdID;
>>>>>>> centreon/dev-21.10.x
                $this->insertWithAdapter('cfg_centreonbroker_info', $row);
            }
        }
    }
<<<<<<< HEAD
=======

    protected function isRemote(): bool
    {
        return true;
    }
>>>>>>> centreon/dev-21.10.x
}
