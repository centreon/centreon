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

/**
 * Get the configuration path for Centreon Broker
 *
 * @param int $ns_id The nagios server id
 * @return string
 */
function getCentreonBrokerDirCfg($ns_id)
{
    global $pearDB;
    $query = 'SELECT centreonbroker_cfg_path
	    	FROM nagios_server
	    	WHERE id = ' . $ns_id;
    $res = $pearDB->query($query);
    $row = $res->fetch();
    if (trim($row['centreonbroker_cfg_path']) != '') {
        return trim($row['centreonbroker_cfg_path']);
    }

    return null;
}
