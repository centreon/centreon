<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonRemote\Domain\Resources\RemoteConfig\BrokerInfo;

/**
 * Get broker configuration template.
 */
class OutputCentral
{
    /**
     * Get template configuration.
     *
     * @todo move it as yml
     *
     * @return array<int, string[]> the configuration template
     */
    public static function getConfiguration(): array
    {
        return [
            [
                'config_key' => 'name',
                'config_value' => 'Central-Output',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'port',
                'config_value' => '5669',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'host',
                'config_value' => 'localhost',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'failover',
                'config_value' => '',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'retry_interval',
                'config_value' => '',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'buffering_timeout',
                'config_value' => '',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'protocol',
                'config_value' => 'bbdo',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'tls',
                'config_value' => 'no',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'private_key',
                'config_value' => '',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'public_cert',
                'config_value' => '',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'ca_certificate',
                'config_value' => '',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'negociation',
                'config_value' => 'yes',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'one_peer_retention_mode',
                'config_value' => 'no',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'compression',
                'config_value' => 'no',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'compression_level',
                'config_value' => '',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'compression_buffer',
                'config_value' => '',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'type',
                'config_value' => 'ipv4',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
            [
                'config_key' => 'blockId',
                'config_value' => '1_3',
                'config_group' => 'output',
                'config_group_id' => '0',
                'grp_level' => '0',
            ],
        ];
    }
}
