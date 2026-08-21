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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\ConnectorConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\EngineInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneCommunicationTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
use App\MonitoringConfiguration\Domain\Exception\InvalidGorgoneCommunicationTypeException;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalPollerRepository
 *
 * @implements TransformerInterface<RowTypeAlias, Poller>
 */
final readonly class DbalPollerTransformer implements TransformerInterface
{
    public function transform(mixed $from): mixed
    {
        return new Poller(
            id: new PollerId($from['poller_id']),
            name: new PollerName($from['poller_name']),
            address: new PollerAddress($from['poller_address']),
            isCentral: (bool) $from['is_central'],
            isDefault: (bool) $from['is_default'],
            isActivated: $from['is_activated'] === '1',
            pollerType: PollerTypeEnum::from($from['poller_type']),
            uid: new PollerUid((int) $from['poller_uid']),
            globalMacros: new Collection([], GlobalMacro::class),
            pollerCommands: new Collection([], PollerCommand::class),
            centralAddress: $this->centralAddressFromDatabase($from['central_address'] ?? null),
            brokerInformation: new BrokerInformation(
                reloadCommand: $from['broker_reload_command'],
                configurationPath: $from['centreonbroker_cfg_path'],
                modulesPath: $from['centreonbroker_module_path'],
                logsPath: $from['centreonbroker_logs_path'],
            ),
            engineInformation: new EngineInformation(
                startCommand: $from['engine_start_command'],
                stopCommand: $from['engine_stop_command'],
                restartCommand: $from['engine_restart_command'],
                reloadCommand: $from['engine_reload_command'],
                binaryPath: $from['nagios_bin'],
                statisticsBinaryPath: $from['nagiostats_bin'],
                perfdataFilePath: $from['nagios_perfdata'],
            ),
            connectorConfiguration: new ConnectorConfiguration(
                connectorPath: $from['centreonconnector_path'],
            ),
            trapConfiguration: new TrapConfiguration(
                initScriptPath: $from['init_script_centreontrapd'],
                snmpTrapPathConf: $from['snmp_trapd_path_conf'],
            ),
            gorgoneConfiguration: new GorgoneConfiguration(
                communicationType: $this->communicationTypeFromDatabase($from['gorgone_communication_type']),
                gorgonePort: (int) ($from['gorgone_port'] ?? 5556),
                sshPort: (int) ($from['ssh_port'] ?? 22),
                useRemoteServerAsProxy: $from['remote_server_use_as_proxy'] === '1',
            ),
            cmaCertificates: null,
        );
    }

    /**
     * Rows written before the scheme rejection (MON-206245) may carry a full URL:
     * the modal used to send window.location.href on cloud platforms. Reduce them
     * to host[:port] so hydration keeps working — their base path cannot be told
     * apart from the page path of the stored URL. Unreadable values become null,
     * which downstream reports as "no central address configured".
     */
    private function centralAddressFromDatabase(mixed $value): ?CentralAddress
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);
            if (! is_string($host) || $host === '') {
                return null;
            }
            $port = parse_url($value, PHP_URL_PORT);
            $value = is_int($port) ? sprintf('%s:%d', $host, $port) : $host;
        }

        try {
            return new CentralAddress($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function communicationTypeFromDatabase(string $value): GorgoneCommunicationTypeEnum
    {
        return match ($value) {
            '1' => GorgoneCommunicationTypeEnum::ZMQ,
            '2' => GorgoneCommunicationTypeEnum::SSH,
            '3' => GorgoneCommunicationTypeEnum::Pull,
            '4' => GorgoneCommunicationTypeEnum::PullWss,
            default => throw InvalidGorgoneCommunicationTypeException::fromDatabaseValue($value),
        };
    }
}
