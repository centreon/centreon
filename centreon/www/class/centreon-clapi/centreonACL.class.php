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

namespace CentreonClapi;

use Pimple\Container;

require_once 'centreonUtils.class.php';

/**
 * Class
 *
 * @class CentreonACL
 * @package CentreonClapi
 */
class CentreonACL
{
    /** @var CentreonDB */
    protected $db;

    // hack to get rid of warning messages
    /** @var array */
    public $topology = [];

    /** @var string */
    public $topologyStr = '';

    /**
     * CentreonACL constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        $this->db = $dependencyInjector['configuration_db'];
    }

    /**
     * Reload
     *
     * @param mixed $flagOnly
     * @return void
     */
    public function reload($flagOnly = false): void
    {
        $this->db->query('UPDATE acl_groups SET acl_group_changed = 1');
        $this->db->query('UPDATE acl_resources SET changed = 1');
        if ($flagOnly == false) {
            $centreonDir = realpath(__DIR__ . '/../../../');
            passthru($centreonDir . '/cron/centAcl.php');
        }
    }

    /**
     * Print timestamp at when ACL was last reloaded
     *
     * @param null|mixed $timeformat
     * @return void
     */
    public function lastreload($timeformat = null): void
    {
        $res = $this->db->query("SELECT time_launch FROM cron_operation WHERE name LIKE 'centAcl%'");
        $row = $res->fetch();
        $time = $row['time_launch'];
        if (isset($timeformat) && $timeformat) {
            $time = date($timeformat, $time);
        }
        echo $time . "\n";
    }
}
