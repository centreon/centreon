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
class OutputUnifiedSql
{
    /**
     * Get template configuration.
     *
     * @todo move it as yml
     *
     * @param string|null $dbUser the database user
     * @param string|null $dbPassword the database password
     *
     * @return array<string,mixed> the configuration template
     */
    public static function getConfiguration($dbUser, $dbPassword): array
    {
        return [
            'tag'       => 'output',
            'type_id'   => 34,
            'type_name' => 'unified_sql',
            'name'      => 'central-broker-master-unified-sql',
            'parameters' => [
                'db_type'                  => 'mysql',
                'db_host'                  => 'localhost',
                'db_port'                  => '3306',
                'db_user'                  => $dbUser,
                'db_password'              => $dbPassword,
                'db_name'                  => 'centreon_storage',
                'failover'                 => '',
                'retry_interval'           => '15',
                'buffering_timeout'        => '0',
                'queries_per_transaction'  => '',
                'read_timeout'             => '',
                'interval'                 => '60',
                'length'                   => '15552000',
                'rebuild_check_interval'   => '',
                'store_in_data_bin'        => 'yes',
                'insert_in_index_data'     => '1',
                'cleanup_check_interval'   => '',
                'instance_timeout'         => '',
            ],
        ];
    }
}
