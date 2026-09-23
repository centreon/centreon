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
 * @return string|null
 */
function getCentreonBrokerDirCfg($ns_id)
{
    global $pearDB;
    $statement = $pearDB->prepare('SELECT centreonbroker_cfg_path
	    	FROM nagios_server
	    	WHERE id = :ns_id');
    $statement->bindValue(':ns_id', (int) $ns_id, PDO::PARAM_INT);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return null;
    }

    $path = trim((string) $row['centreonbroker_cfg_path']);
    if ($path !== '') {
        return $path;
    }

    return null;
}
