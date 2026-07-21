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

namespace CentreonRemote\Domain\Resources\RemoteConfig;

use App\MonitoringConfiguration\Infrastructure\Service\SnowflakePollerUidGenerator;
use Godruoyi\Snowflake\Snowflake;

/**
 * Get broker configuration template.
 */
class NagiosServer
{
    // gorgone_communication_type enum values (mirrored from nagios_server column values)
    public const ZMQ = '1';
    public const SSH = '2';
    public const PULL = '3';
    public const PULLWSS = '4';
    public const DEFAULT_GORGONE_PORT = 5556;
    public const PULLWSS_GORGONE_PORT = 8086;
    public const DEFAULT_ENGINE_START_COMMAND = 'systemctl start centengine';
    public const DEFAULT_ENGINE_STOP_COMMAND = 'systemctl stop centengine';
    public const DEFAULT_ENGINE_RESTART_COMMAND = 'systemctl restart centengine';
    public const DEFAULT_ENGINE_RELOAD_COMMAND = 'systemctl reload centengine';

    /**
     * Get template configuration.
     *
     * @todo move it as yml
     *
     * @param string $name the poller name
     * @param string $ip the poller ip address
     * @param string|null $gorgoneCommunicationType override for the gorgone_communication_type column
     * @param int|null $gorgonePort override for the gorgone_port column
     *
     * @return array<string,int|string> the configuration template
     */
    public static function getConfiguration(
        string $name,
        string $ip,
        ?string $gorgoneCommunicationType = null,
        ?int $gorgonePort = null,
    ): array {
        return [
            'name' => $name,
            'localhost' => '0',
            'is_default' => '0',
            'ns_ip_address' => $ip,
            'ns_activate' => '1',
            'ns_status' => '0',
            'engine_start_command' => self::DEFAULT_ENGINE_START_COMMAND,
            'engine_stop_command' => self::DEFAULT_ENGINE_STOP_COMMAND,
            'engine_restart_command' => self::DEFAULT_ENGINE_RESTART_COMMAND,
            'engine_reload_command' => self::DEFAULT_ENGINE_RELOAD_COMMAND,
            'nagios_bin' => '/usr/sbin/centengine',
            'nagiostats_bin' => '/usr/sbin/centenginestats',
            'nagios_perfdata' => '/var/log/centreon-engine/service-perfdata',
            'centreonbroker_cfg_path' => '/etc/centreon-broker',
            'centreonbroker_module_path' => '/usr/share/centreon/lib/centreon-broker',
            'centreonconnector_path' => '/usr/lib64/centreon-connector',
            'ssh_port' => 22,
            'gorgone_communication_type' => $gorgoneCommunicationType ?? self::ZMQ,
            'gorgone_port' => $gorgonePort ?? self::DEFAULT_GORGONE_PORT,
            'init_script_centreontrapd' => 'centreontrapd',
            'snmp_trapd_path_conf' => '/etc/snmp/centreon_traps/',
            'centreonbroker_logs_path' => '/var/log/centreon-broker/',
            'uid' => self::generateSnowflakeUid(),
        ];
    }

    private static function generateSnowflakeUid(): int
    {
        $snowflake = new Snowflake(0, 0);
        $snowflake->setStartTimeStamp(SnowflakePollerUidGenerator::CUSTOM_EPOCH_MS);

        return (int) $snowflake->id();
    }
}
