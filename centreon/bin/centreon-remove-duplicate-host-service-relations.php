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

declare(strict_types=1);

require_once __DIR__ . '/../config/centreon.config.php';
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';

$centreonDb = new CentreonDB('centreon');

$number = $centreonDb->exec(
    <<<'SQL'
        DELETE FROM host_service_relation WHERE hsr_id IN (
           SELECT hsr_id
           FROM host_service_relation
           INNER JOIN (
              SELECT min(hsr_id) as hsr_id_to_keep, host_host_id, service_service_id
              FROM host_service_relation
              WHERE hostgroup_hg_id IS NULL
                AND servicegroup_sg_id IS NULL
              GROUP BY host_host_id, service_service_id
              HAVING count(*) > 1
           ) duplicates USING (host_host_id, service_service_id)
           WHERE hsr_id != hsr_id_to_keep
        )
        SQL
);

if (is_int($number)) {
    $message = match ($number) {
        1 => $number . ' relation was deleted',
        default => $number . ' relations were deleted',
    };
    echo $message . PHP_EOL;
}
