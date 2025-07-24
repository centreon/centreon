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

// Update index of centreon_acl
if (isset($pearDBO)) {
    $query = 'SELECT count(*) AS number '
        . 'FROM INFORMATION_SCHEMA.STATISTICS '
        . "WHERE table_schema = '" . $conf_centreon['dbcstg'] . "' "
        . "AND table_name = 'centreon_acl' "
        . "AND index_name='index2'";
    $res = $pearDBO->query($query);
    $data = $res->fetchRow();
    if ($data['number'] == 0) {
        $pearDBO->query('ALTER TABLE centreon_acl ADD INDEX `index2` (`host_id`,`service_id`,`group_id`)');
    }
}
