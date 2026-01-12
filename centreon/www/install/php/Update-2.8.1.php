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

// Update comments unique key
if (isset($pearDBO)) {
    $query = 'SHOW INDEX FROM comments '
        . 'WHERE column_name = "host_id" '
        . 'AND Key_name = "host_id" ';
    $res = $pearDBO->query($query);
    if (! $res->rowCount()) {
        $pearDBO->query('ALTER TABLE comments ADD KEY host_id(host_id)');
    }

    $query = 'ALTER TABLE `comments` '
        . 'DROP KEY `entry_time`, '
        . 'ADD UNIQUE KEY `entry_time` (`entry_time`,`host_id`,`service_id`, `instance_id`, `internal_id`) ';
    $pearDBO->query($query);
}
