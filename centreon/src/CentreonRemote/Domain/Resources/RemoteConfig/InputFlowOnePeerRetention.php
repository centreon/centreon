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

/**
 * Configuration if input flow of central broker to get data from the remote poller.
 */
class InputFlowOnePeerRetention
{
    /**
     * Get template configuration.
     *
     * @todo move it as yml
     *
     * @param string $pollerName the poller name
     * @param string $pollerIP the poller ip address
     *
     * @return array<string,mixed> the configuration template
     */
    public static function getConfiguration(string $pollerName, string $pollerIP): array
    {
        return [
            'tag'       => 'input',
            'type_id'   => 3,
            'type_name' => 'ipv4',
            'name'      => "connection-to-{$pollerName}",
            'parameters' => [
                'port'                    => '5669',
                'host'                    => $pollerIP,
                'retry_interval'          => '15',
                'buffering_timeout'       => '0',
                'failover'                => '',
                'protocol'                => 'bbdo',
                'tls'                     => 'no',
                'private_key'             => '',
                'public_cert'             => '',
                'ca_certificate'          => '',
                'negotiation'             => 'yes',
                'one_peer_retention_mode' => 'no',
                'compression'             => 'no',
                'compression_level'       => '',
                'compression_buffer'      => '',
            ],
        ];
    }
}
