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

function getServiceGroupCount($search = null)
{
    global $pearDB;

    if ($search != '') {
        $DBRESULT = $pearDB->query(
            "SELECT count(sg_id) FROM `servicegroup` WHERE sg_name LIKE '%{$search}%'"
        );
    } else {
        $DBRESULT = $pearDB->query('SELECT count(sg_id) FROM `servicegroup`');
    }
    $num_row = $DBRESULT->fetchRow();
    $DBRESULT->closeCursor();

    return $num_row['count(sg_id)'];
}

function getMyHostGraphs($host_id = null)
{
    global $pearDBO;
    if (! isset($host_id)) {
        return null;
    }
    $tab_svc = [];

    $DBRESULT = $pearDBO->query(
        'SELECT `service_id`, `service_description` '
        . 'FROM `index_data`, `metrics` '
        . 'WHERE metrics.index_id = index_data.id '
        . "AND `host_id` = '" . CentreonDB::escape($host_id) . "' "
        . "AND index_data.`hidden` = '0' "
        . "AND index_data.`trashed` = '0' "
        . 'ORDER BY `service_description`'
    );
    while ($row = $DBRESULT->fetchRow()) {
        $tab_svc[$row['service_id']] = $row['service_description'];
    }

    return $tab_svc;
}

function getHostGraphedList()
{
    global $pearDBO;

    $tab = [];
    $DBRESULT = $pearDBO->query(
        'SELECT `host_id` FROM `index_data`, `metrics` '
        . 'WHERE metrics.index_id = index_data.id '
        . "AND index_data.`hidden` = '0' "
        . "AND index_data.`trashed` = '0' "
        . 'ORDER BY `host_name`'
    );
    while ($row = $DBRESULT->fetchRow()) {
        $tab[$row['host_id']] = 1;
    }

    return $tab;
}

function checkIfServiceSgIsEn($host_id = null, $service_id = null)
{
    global $pearDBO;
    if (! isset($host_id) || ! isset($service_id)) {
        return null;
    }
    $tab_svc = [];

    $DBRESULT = $pearDBO->query(
        'SELECT `service_id` FROM `index_data` '
        . "WHERE `host_id` = '" . CentreonDB::escape($host_id) . "' "
        . "AND `service_id` = '" . CentreonDB::escape($service_id) . "' "
        . "AND index_data.`hidden` = '0' "
        . "AND `trashed` = '0'"
    );
    $num_row = $DBRESULT->rowCount();
    $DBRESULT->closeCursor();

    return $num_row;
}
