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

namespace App\MonitoringConfiguration\Domain\Factory;

use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigFileName;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigKey;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigName;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerFlowGroupEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerInputOutput;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerLog;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerLoggerEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerLogLevelEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerParameter;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerStreamTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * Builds the default centreon-broker configuration for a newly created poller, replicating the
 * legacy "Add wizard" behaviour (PollerConnectionConfigurationService::insertConfigCentreonBroker()):
 * a single `{slug}-module` config (daemon = false) with one `central-module` output flow.
 *
 * The output stream type depends on the platform: an IPv4 output on-prem, a BBDO Client output
 * (gRPC, NAT/firewall-friendly) on cloud.
 */
final class BrokerConfigurationFactory
{
    public const DEFAULT_EVENT_QUEUE_MAX_SIZE = 100000;
    public const DEFAULT_MODULE_CACHE_DIRECTORY = '/var/lib/centreon-engine';
    public const DEFAULT_LOG_DIRECTORY = '/var/log/centreon-broker';
    public const CENTRAL_MODULE_OUTPUT_NAME = 'central-module-master-output';

    /** Vault sub-path under which broker credentials are stored (mirrors the legacy broker path). */
    public const BROKER_VAULT_CUSTOM_PATH = 'configuration/broker';

    public function createDefault(
        PollerId $pollerId,
        string $pollerName,
        bool $isCloudPlatform,
        CentralAddress $centralAddress,
        ?string $authorizationToken = null,
    ): BrokerConfiguration {
        $slug = $this->slugify($pollerName);

        return new BrokerConfiguration(
            brokerConfigurationId: null,
            pollerId: $pollerId,
            name: new BrokerConfigName($slug . '-module'),
            fileName: new BrokerConfigFileName($slug . '-module.json'),
            isActivated: true,
            daemon: false,
            configWriteTimestamp: false,
            configWriteThreadId: false,
            eventQueueMaxSize: self::DEFAULT_EVENT_QUEUE_MAX_SIZE,
            commandFile: '',
            cacheDirectory: self::DEFAULT_MODULE_CACHE_DIRECTORY,
            logDirectory: self::DEFAULT_LOG_DIRECTORY,
            statsActivate: true,
            flows: new Collection(
                [$this->centralModuleOutput($isCloudPlatform, $authorizationToken, $centralAddress)],
                BrokerInputOutput::class,
            ),
            logs: new Collection($this->defaultLogs(), BrokerLog::class),
        );
    }

    private function centralModuleOutput(
        bool $isCloudPlatform,
        ?string $authorizationToken,
        CentralAddress $centralAddress,
    ): BrokerInputOutput {
        return $isCloudPlatform
            ? $this->bbdoClientCentralModuleOutput($authorizationToken, $centralAddress)
            : $this->ipv4CentralModuleOutput($centralAddress);
    }

    /**
     * On-prem `central-module` output — the authoritative template for an IPv4 central-module
     * output flow (`OutputModuleMaster`). Parameter order mirrors the legacy template; the
     * stream kind is modeled as {@see BrokerStreamTypeEnum::Ipv4}, so the `type`/`blockId` meta
     * rows are derived at persistence rather than hand-written here.
     */
    private function ipv4CentralModuleOutput(CentralAddress $centralAddress): BrokerInputOutput
    {
        $values = [
            BrokerConfigKey::NAME => self::CENTRAL_MODULE_OUTPUT_NAME,
            'port' => '5669',
            // On-prem the Central is reachable directly at its IP/hostname
            // (mirrors legacy PollerConnectionConfigurationService::insertConfigCentreonBroker()).
            // Only the bare host: the central address is the *web* entry point, so it may carry a
            // web port and a base path, neither of which applies to the BBDO port above.
            'host' => $centralAddress->host,
            'failover' => '',
            'retry_interval' => '15',
            'buffering_timeout' => '0',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'private_key' => '',
            'public_cert' => '',
            'ca_certificate' => '',
            'negotiation' => 'yes',
            'one_peer_retention_mode' => 'no',
            'compression' => 'no',
            'compression_level' => '',
            'compression_buffer' => '',
        ];

        return $this->outputFlow($values, BrokerStreamTypeEnum::Ipv4);
    }

    /**
     * Cloud `central-module` output — the authoritative template for a BBDO Client
     * central-module output flow that dials out to the Central over gRPC. The stream kind is
     * modeled as {@see BrokerStreamTypeEnum::BbdoClient}, so the `type`/`blockId` meta rows are
     * derived at persistence rather than hand-written here.
     */
    private function bbdoClientCentralModuleOutput(
        ?string $authorizationToken,
        CentralAddress $centralAddress,
    ): BrokerInputOutput {
        $values = [
            BrokerConfigKey::NAME => self::CENTRAL_MODULE_OUTPUT_NAME,
            'host' => $this->resolveCloudBrokerHost($centralAddress),
            'port' => '443',
            'retry_interval' => '',
            'transport_protocol' => 'gRPC',
            BrokerConfigKey::AUTHORIZATION => $authorizationToken ?? '',
            'encryption' => 'yes',
            'ca_certificate' => '',
            'ca_name' => '',
            'compression' => 'no',
        ];

        return $this->outputFlow($values, BrokerStreamTypeEnum::BbdoClient);
    }

    /**
     * Derive the cloud broker gateway host from the Central address.
     *
     * `<orga>.<region>.<domain>[:<port>]/<platform>` becomes `broker-<platform>-<orga>.<region>.<domain>`,
     * e.g. `staging.euwest1.centreon.click/funky-donkey`
     *   -> `broker-funky-donkey-staging.euwest1.centreon.click`.
     *
     * The web port plays no part: the gateway is always dialled on 443 (see the `port` parameter
     * above). A cloud platform is always served under a single-segment base path (the platform
     * slug), so anything else means the address is not a cloud Central: throwing rolls the whole
     * create-poller transaction back rather than persisting an output that dials nowhere.
     *
     * @throws \InvalidArgumentException when the address carries no base path, a base path made of
     *                                   several segments, or a single-label host (no organization +
     *                                   domain to split)
     */
    private function resolveCloudBrokerHost(CentralAddress $centralAddress): string
    {
        $platform = $centralAddress->basePath;

        Assert::stringNotEmpty(
            $platform,
            sprintf('Central address "%s" has no platform base path.', $centralAddress->value),
        );
        // The platform is a single label of the broker hostname: a nested path such as
        // "team/poller" would yield a hostname containing a slash.
        Assert::notContains(
            $platform,
            '/',
            sprintf('Central address "%s" must contain a single platform path segment.', $centralAddress->value),
        );

        $labels = explode('.', $centralAddress->host, 2);
        $organization = $labels[0];
        $regionAndDomain = $labels[1] ?? '';
        Assert::stringNotEmpty(
            $regionAndDomain,
            sprintf(
                'Central address host "%s" must contain an organization label and a domain.',
                $centralAddress->host,
            ),
        );

        return sprintf('broker-%s-%s.%s', $platform, $organization, $regionAndDomain);
    }

    /**
     * @param array<string, string> $values ordered config_key => config_value pairs
     */
    private function outputFlow(array $values, BrokerStreamTypeEnum $type): BrokerInputOutput
    {
        $parameters = [];
        foreach ($values as $key => $value) {
            $parameters[] = new BrokerParameter(
                configKey: $key,
                configValue: $value,
                groupLevel: 0,
            );
        }

        return new BrokerInputOutput(
            group: BrokerFlowGroupEnum::Output,
            groupId: 0,
            type: $type,
            parameters: new Collection($parameters, BrokerParameter::class),
        );
    }

    /**
     * The default logger set: `core` at `info`, every other category at `error`
     * (CfgCentreonBrokerLog template).
     *
     * @return BrokerLog[]
     */
    private function defaultLogs(): array
    {
        $logs = [];
        foreach (BrokerLoggerEnum::cases() as $logger) {
            $logs[] = new BrokerLog(
                $logger,
                $logger === BrokerLoggerEnum::Core ? BrokerLogLevelEnum::Info : BrokerLogLevelEnum::Error,
            );
        }

        return $logs;
    }

    private function slugify(string $name): string
    {
        return mb_strtolower(str_replace(' ', '-', $name));
    }
}
