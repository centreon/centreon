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

require_once _CENTREON_PATH_ . '/www/class/centreonMeta.class.php';

if (isset($pearDB)) {
    $metaObj = new CentreonMeta($pearDB);
    $hostId = null;
    $virtualServices = [];

    // Check virtual host
    $queryHost = 'SELECT host_id '
        . 'FROM host '
        . 'WHERE host_register = "2" '
        . 'AND host_name = "_Module_Meta" ';
    $res = $pearDB->query($queryHost);
    if ($res->rowCount()) {
        $row = $res->fetchRow();
        $hostId = $row['host_id'];
    } else {
        $query = 'INSERT INTO host (host_name, host_register) '
            . 'VALUES ("_Module_Meta", "2") ';
        $pearDB->query($query);
        $res = $pearDB->query($queryHost);
        if ($res->rowCount()) {
            $row = $res->fetchRow();
            $hostId = $row['host_id'];
        }
    }

    // Check existing virtual services
    $query = 'SELECT service_id, service_description '
        . 'FROM service '
        . 'WHERE service_description LIKE "meta_%" '
        . 'AND service_register = "2" ';
    $res = $pearDB->query($query);
    while ($row = $res->fetchRow()) {
        if (preg_match('/meta_(\d+)/', $row['service_description'], $matches)) {
            $metaId = $matches[1];
            $virtualServices[$matches[1]]['service_id'] = $row['service_id'];
        }
    }

    // Check existing relations between virtual services and virtual host
    $query = 'SELECT s.service_id, s.service_description '
        . 'FROM service s, host_service_relation hsr '
        . 'WHERE hsr.host_host_id = :host_id '
        . 'AND s.service_register = "2" '
        . 'AND s.service_description LIKE "meta_%" ';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':host_id', (int) $hostId, PDO::PARAM_INT);
    $statement->execute();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        if (preg_match('/meta_(\d+)/', $row['service_description'], $matches)) {
            $metaId = $matches[1];
            $virtualServices[$matches[1]]['relation'] = true;
        }
    }

    $query = 'SELECT meta_id, meta_name '
        . 'FROM meta_service ';
    $res = $pearDB->query($query);
    while ($row = $res->fetchRow()) {
        if (! isset($virtualServices[$row['meta_id']]) || ! isset($virtualServices[$row['meta_id']]['service_id'])) {
            $serviceId = $metaObj->insertVirtualService($row['meta_id'], $row['meta_name']);
        } else {
            $serviceId = $virtualServices[$row['meta_id']]['service_id'];
        }
        if (! isset($virtualServices[$row['meta_id']]) || ! isset($virtualServices[$row['meta_id']]['relation'])) {
            $query = 'INSERT INTO host_service_relation (host_host_id, service_service_id) '
                . 'VALUES (:host_id, :service_id) ';
            $statement = $pearDB->prepare($query);
            $statement->bindValue(':host_id', (int) $hostId, PDO::PARAM_INT);
            $statement->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);
            $statement->execute();
        }
    }
}
