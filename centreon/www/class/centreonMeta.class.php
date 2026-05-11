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
 * Class
 *
 * @class CentreonMeta
 */
class CentreonMeta
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonMeta constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Return host id
     *
     * @throws PDOException
     * @return int
     */
    public function getRealHostId()
    {
        static $hostId = null;

        if (is_null($hostId)) {
            $queryHost = 'SELECT host_id '
                . 'FROM host '
                . 'WHERE host_name = "_Module_Meta" '
                . 'AND host_register = "2" '
                . 'LIMIT 1 ';
            $res = $this->db->query($queryHost);
            if ($res->rowCount()) {
                $row = $res->fetchRow();
                $hostId = $row['host_id'];
            } else {
                $query = 'INSERT INTO host (host_name, host_register) '
                    . 'VALUES ("_Module_Meta", "2") ';
                $this->db->query($query);
                $res = $this->db->query($queryHost);
                if ($res->rowCount()) {
                    $row = $res->fetchRow();
                    $hostId = $row['host_id'];
                } else {
                    $hostId = 0;
                }
            }
        }

        return $hostId;
    }

    /**
     * Return service id
     *
     * @param int $metaId
     *
     * @throws PDOException
     * @return int
     */
    public function getRealServiceId($metaId)
    {
        static $services = null;
        if (isset($services[$metaId])) {
            return $services[$metaId];
        }

        $stmt = $this->db->prepare(
            'SELECT s.service_id FROM service s WHERE s.service_description = :desc ORDER BY s.service_id DESC LIMIT 1'
        );
        $stmt->bindValue(':desc', 'meta_' . $metaId, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        $services[$metaId] = $row !== false ? (int) $row['service_id'] : 0;

        return $services[$metaId];
    }

    /**
     * Return metaservice id
     *
     * @param string $serviceDisplayName
     *
     * @throws PDOException
     * @return int
     */
    public function getMetaIdFromServiceDisplayName($serviceDisplayName)
    {
        $metaId = null;
        $stmt = $this->db->prepare(
            'SELECT service_description FROM service WHERE display_name = :displayName LIMIT 1'
        );
        $stmt->bindValue(':displayName', $serviceDisplayName, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row !== false && preg_match('/meta_(\d+)/', $row['service_description'], $matches)) {
            $metaId = (int) $matches[1];
        }

        return $metaId;
    }

    /**
     * @param int $field
     *
     * @return array
     */
    public static function getDefaultValuesParameters($field)
    {
        $parameters = [];
        $parameters['currentObject']['table'] = 'meta_service';
        $parameters['currentObject']['id'] = 'meta_id';
        $parameters['currentObject']['name'] = 'meta_name';
        $parameters['currentObject']['comparator'] = 'meta_id';

        switch ($field) {
            case 'check_period':
            case 'notification_period':
                $parameters['type'] = 'simple';
                $parameters['externalObject']['table'] = 'timeperiod';
                $parameters['externalObject']['id'] = 'tp_id';
                $parameters['externalObject']['name'] = 'tp_name';
                $parameters['externalObject']['comparator'] = 'tp_id';
                break;
            case 'ms_cs':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'contact';
                $parameters['externalObject']['id'] = 'contact_id';
                $parameters['externalObject']['name'] = 'contact_name';
                $parameters['externalObject']['comparator'] = 'contact_id';
                $parameters['relationObject']['table'] = 'meta_contact';
                $parameters['relationObject']['field'] = 'contact_id';
                $parameters['relationObject']['comparator'] = 'meta_id';
                break;
            case 'ms_cgs':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'contactgroup';
                $parameters['externalObject']['id'] = 'cg_id';
                $parameters['externalObject']['name'] = 'cg_name';
                $parameters['externalObject']['comparator'] = 'cg_id';
                $parameters['relationObject']['table'] = 'meta_contactgroup_relation';
                $parameters['relationObject']['field'] = 'cg_cg_id';
                $parameters['relationObject']['comparator'] = 'meta_id';
                break;
        }

        return $parameters;
    }

    /**
     * @param array $values
     * @param array $options
     *
     * @throws PDOException
     * @return array
     */
    public function getObjectForSelect2($values = [], $options = [])
    {
        $items = [];
        $listValues = '';
        $queryValues = [];
        if (! empty($values)) {
            foreach ($values as $k => $v) {
                $listValues .= ':meta' . $v . ',';
                $queryValues['meta' . $v] = (int) $v;
            }
            $listValues = rtrim($listValues, ',');
        } else {
            $listValues .= '""';
        }

        // get list of selected meta
        $query = 'SELECT meta_id, meta_name FROM meta_service '
            . 'WHERE meta_id IN (' . $listValues . ') ORDER BY meta_name ';
        $stmt = $this->db->prepare($query);
        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $items[] = ['id' => $row['meta_id'], 'text' => $row['meta_name']];
        }

        return $items;
    }

    /**
     * Get the list of all meta-service
     *
     * @return array
     */
    public function getList()
    {
        $queryList = 'SELECT `meta_id`, `meta_name`
 	    	FROM `meta_service`
 	    	ORDER BY `meta_name`';

        try {
            $res = $this->db->query($queryList);
        } catch (PDOException $e) {
            return [];
        }
        $listMeta = [];
        while ($row = $res->fetchRow()) {
            $listMeta[$row['meta_id']] = $row['meta_name'];
        }

        return $listMeta;
    }

    /**
     * Returns service details
     *
     * @param int $id
     * @param array $parameters
     *
     * @throws PDOException
     * @return array
     */
    public function getParameters($id, $parameters = [])
    {
        $sElement = '*';
        $values = [];
        if (empty($id) || empty($parameters)) {
            return [];
        }

        $sanitized = array_filter($parameters, fn ($col) => preg_match('/^[a-zA-Z0-9_]+$/', $col));
        if ($sanitized === []) {
            return [];
        }
        $sElement = implode(',', $sanitized);

        $stmt = $this->db->prepare(
            'SELECT ' . $sElement . ' FROM meta_service WHERE meta_id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row !== false) {
            $values = $row;
        }

        return $values;
    }

    /**
     * Returns service id
     *
     * @param int $metaId
     * @param string $metaName
     *
     * @throws PDOException
     * @return int
     */
    public function insertVirtualService($metaId, $metaName)
    {
        $hostId = $this->getRealHostId();
        $serviceId = null;

        $composedName = 'meta_' . $metaId;

        $selectStmt = $this->db->prepare(
            'SELECT service_id, display_name FROM service
            WHERE service_register = "2" AND service_description = :description'
        );
        $selectStmt->bindValue(':description', $composedName, PDO::PARAM_STR);
        $selectStmt->execute();
        $row = $selectStmt->fetch();
        if ($row !== false) {
            $serviceId = $row['service_id'];
            if ($row['display_name'] !== $metaName) {
                $updateStmt = $this->db->prepare(
                    'UPDATE service SET display_name = :display_name WHERE service_id = :service_id'
                );
                $updateStmt->bindValue(':display_name', $metaName, PDO::PARAM_STR);
                $updateStmt->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);
                $updateStmt->execute();
            }
        } else {
            $insertStmt = $this->db->prepare(
                'INSERT INTO service (service_description, display_name, service_register)
                VALUES (:description, :display_name, "2")'
            );
            $insertStmt->bindValue(':description', $composedName, PDO::PARAM_STR);
            $insertStmt->bindValue(':display_name', $metaName, PDO::PARAM_STR);
            $insertStmt->execute();
            $serviceId = (int) $this->db->lastInsertId();

            $relStmt = $this->db->prepare(
                'INSERT INTO host_service_relation (host_host_id, service_service_id)
                VALUES (:host_id, :service_id)'
            );
            $relStmt->bindValue(':host_id', (int) $hostId, PDO::PARAM_INT);
            $relStmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
            $relStmt->execute();
        }

        return $serviceId;
    }
}
