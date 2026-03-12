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

namespace CentreonRemote\Domain\Resources\RemoteConfig\BrokerInfo;

/**
 * Get broker configuration template.
 */
class OutputRrd
{
    /**
     * Get template configuration.
     *
     * @todo move it as yml
     *
     * @return array<string,mixed> the configuration template
     */
    public static function getConfiguration(): array
    {
        return [
            'tag'       => 'output',
            'type_id'   => 13,
            'type_name' => 'rrd',
            'name'      => 'central-rrd-master-output',
            'parameters' => [
                'metrics_path'       => '/var/lib/centreon/metrics/',
                'failover'           => '',
                'status_path'        => '/var/lib/centreon/status/',
                'retry_interval'     => '15',
                'buffering_timeout'  => '0',
                'path'               => '',
                'port'               => '',
                'write_metrics'      => 'yes',
                'write_status'       => 'yes',
                'store_in_data_bin'  => 'yes',
                'insert_in_index_data' => '1',
            ],
        ];
    }
}
