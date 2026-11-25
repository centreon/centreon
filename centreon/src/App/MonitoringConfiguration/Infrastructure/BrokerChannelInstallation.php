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

namespace App\MonitoringConfiguration\Infrastructure;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Model\BrokerChannelEndpoint;
use App\MonitoringConfiguration\Domain\Repository\PollerBrokerChannelRepository;
use App\MonitoringConfiguration\Domain\Service\BrokerCaProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * MON-215450 phase 2g (contracts.md § 11.W, 11.W3): broker-channel Docker pollers, installed by install.sh
 * without Gorgone.
 *
 * - The platform is on the broker channel when the web reaches the central broker remote API
 *   (CENTREON_BROKER_API_URL), as for the configuration delivery.
 * - A Docker poller created on such a platform dials CENTREON_BROKER_POLLER_HOST (else the central address host)
 *   on CENTREON_BROKER_POLLER_PORT (default 5669): the address external pollers reach, which the central broker
 *   certificate SAN must contain.
 * - The command reads the dial address back from the poller's engine module output, and the TLS name follows the
 *   generator rule: the host itself, CENTREON_BROKER_TLS_HOSTNAME only for an IP literal.
 */
final readonly class BrokerChannelInstallation
{
    private const DEFAULT_PORT = 5669;

    public function __construct(
        private PollerBrokerChannelRepository $repository,
        private BrokerCaProvider $brokerCaProvider,
        #[Autowire(env: 'default::CENTREON_BROKER_API_URL')]
        private ?string $brokerApiUrl = null,
        #[Autowire(env: 'default::CENTREON_BROKER_POLLER_HOST')]
        private ?string $pollerHost = null,
        #[Autowire(env: 'default::CENTREON_BROKER_POLLER_PORT')]
        private ?string $pollerPort = null,
        #[Autowire(env: 'default::CENTREON_BROKER_TLS_HOSTNAME')]
        private ?string $tlsHostname = null,
    ) {
    }

    public function appliesTo(PollerTypeEnum $pollerType): bool
    {
        return $pollerType === PollerTypeEnum::Docker && trim((string) $this->brokerApiUrl) !== '';
    }

    public function endpointForNewPoller(CentralAddress $centralAddress): BrokerChannelEndpoint
    {
        $host = trim((string) $this->pollerHost);
        $port = trim((string) $this->pollerPort);

        return new BrokerChannelEndpoint(
            $host !== '' ? $host : $centralAddress->host,
            $port !== '' ? (int) $port : self::DEFAULT_PORT,
        );
    }

    public function useBrokerChannel(PollerId $pollerId, BrokerChannelEndpoint $endpoint): void
    {
        $this->repository->useBrokerChannel($pollerId, $endpoint);
    }

    /**
     * Null when the poller is not a Docker poller on the broker channel: its command stays the Gorgone one
     * (install.sh has no broker channel for VM pollers).
     */
    public function installOptions(PollerId $pollerId, PollerTypeEnum $pollerType): ?BrokerChannelInstallOptions
    {
        if ($pollerType !== PollerTypeEnum::Docker) {
            return null;
        }
        $endpoint = $this->repository->findBrokerChannelEndpoint($pollerId);
        if (! $endpoint instanceof BrokerChannelEndpoint) {
            return null;
        }

        ['ca' => $brokerCa, 'warning' => $warning] = $this->brokerCaProvider->read();
        $tlsHostname = trim((string) $this->tlsHostname);

        return new BrokerChannelInstallOptions(
            pollerId: $pollerId,
            endpoint: $endpoint,
            tlsHostname: $endpoint->isIpLiteral() && $tlsHostname !== '' ? $tlsHostname : null,
            caFingerprintSha256: $brokerCa === null ? null : $brokerCa['fingerprint_sha256'],
            caWarning: $warning,
        );
    }
}
