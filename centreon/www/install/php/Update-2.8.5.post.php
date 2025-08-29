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

if (isset($pearDB)) {
    $query = 'SELECT cb.config_id, COUNT(cbi.config_group) AS nb '
        . 'FROM cfg_centreonbroker cb '
        . 'LEFT JOIN cfg_centreonbroker_info cbi '
        . 'ON cbi.config_id = cb.config_id '
        . 'AND cbi.config_group = "input" '
        . 'GROUP BY cb.config_id ';
    $res = $pearDB->query($query);
    while ($row = $res->fetchRow()) {
        $daemon = 0;
        if ($row['nb'] > 0) {
            $daemon = 1;
        }
        $query = 'UPDATE cfg_centreonbroker '
            . 'SET daemon = :daemon '
            . 'WHERE config_id = :config_id ';
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':daemon', $daemon, PDO::PARAM_INT);
        $statement->bindValue(':config_id', (int) $row['config_id'], PDO::PARAM_INT);
        $statement->execute();
    }
}
