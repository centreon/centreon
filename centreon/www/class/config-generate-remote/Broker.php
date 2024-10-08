<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
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

namespace ConfigGenerateRemote;

use Exception;
use PDO;
use ConfigGenerateRemote\Abstracts\AbstractObject;
use PDOStatement;

/**
 * Class
 *
 * @class Broker
 * @package ConfigGenerateRemote
 */
class Broker extends AbstractObject
{
    /** @var PDOStatement|null */
    public $stmtEngine;
    /** @var string */
    protected $table = 'cfg_centreonbroker';
    /** @var string */
    protected $generateFilename = 'cfg_centreonbroker.infile';

    /** @var string */
    protected $attributesSelect = '
        config_id,
        config_name,
        config_filename,
        config_write_timestamp,
        config_write_thread_id,
        config_activate,
        ns_nagios_server,
        event_queue_max_size,
        event_queues_total_size,
        command_file,
        cache_directory,
        stats_activate,
        daemon,
        pool_size
    ';
    /** @var string[] */
    protected $attributesWrite = [
        'config_id',
        'config_name',
        'config_filename',
        'config_write_timestamp',
        'config_write_thread_id',
        'config_activate',
        'ns_nagios_server',
        'event_queue_max_size',
        'event_queues_total_size',
        'command_file',
        'cache_directory',
        'stats_activate',
        'daemon',
        'pool_size',
    ];
    /** @var PDOStatement|null */
    protected $stmtBroker = null;

    /**
     * Generate broker configuration from poller id
     *
     * @param int $pollerId
     *
     * @return void
     * @throws Exception
     */
    private function generate(int $pollerId): void
    {
        if (is_null($this->stmtEngine)) {
            $this->stmtBroker = $this->backendInstance->db->prepare(
                "SELECT $this->attributesSelect FROM cfg_centreonbroker " .
                "WHERE ns_nagios_server = :poller_id AND config_activate = '1'"
            );
        }
        $this->stmtBroker->bindParam(':poller_id', $pollerId, PDO::PARAM_INT);
        $this->stmtBroker->execute();

        $results = $this->stmtBroker->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            Relations\BrokerInfo::getInstance($this->dependencyInjector)->getBrokerInfoByConfigId($row['config_id']);
            $this->generateObjectInFile(
                $row,
                $row['config_id']
            );
        }
    }

    /**
     * Generate engine configuration from poller
     *
     * @param array $poller
     *
     * @return void
     * @throws Exception
     */
    public function generateFromPoller(array $poller): void
    {
        Resource::getInstance($this->dependencyInjector)->generateFromPollerId($poller['id']);
        $this->generate($poller['id']);
    }
}
