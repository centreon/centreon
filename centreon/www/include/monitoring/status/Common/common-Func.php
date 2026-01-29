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

function getMyIndexGraph4Service($host_id = null, $service_id = null, $pearDBO)
{
    if ((! isset($service_id) || ! $service_id) || (! isset($host_id) || ! $host_id)) {
        return null;
    }

    $DBRESULT = $pearDBO->query("SELECT id FROM index_data i, metrics m WHERE i.host_id = '" . $host_id . "' "
        . "AND m.hidden = '0' "
        . "AND i.service_id = '" . $service_id . "' "
        . 'AND i.id = m.index_id');
    if ($DBRESULT->rowCount()) {
        $row = $DBRESULT->fetchRow();

        return $row['id'];
    }

    return 0;
}
