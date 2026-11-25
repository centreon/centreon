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

namespace App\MonitoringConfiguration\Domain\Repository;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Model\BrokerChannelEndpoint;

/**
 * MON-215450 phase 2g (contracts.md § 11.W/11.W3): pollers reached through the central broker
 * (nagios_server.remote_channel = 'broker') and the address their engine module dials.
 */
interface PollerBrokerChannelRepository
{
    /**
     * Puts the poller on the broker channel and points its engine module output at $endpoint.
     */
    public function useBrokerChannel(PollerId $pollerId, BrokerChannelEndpoint $endpoint): void;

    /**
     * The address the poller's engine module dials, or null when the poller is not on the broker channel
     * or has no usable output.
     */
    public function findBrokerChannelEndpoint(PollerId $pollerId): ?BrokerChannelEndpoint;

    public function findPollerIdByUid(PollerUid $uid): ?PollerId;
}
