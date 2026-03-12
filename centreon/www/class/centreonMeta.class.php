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
     * Get the host ID for the special "_Module_Meta" host, creating that host if it does not exist.
     *
     * @throws PDOException If a database error occurs.
     * @return int The host_id for "_Module_Meta", or 0 if the host could not be retrieved after creation.
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
            $row = $res->fetchRow();
            if ($row !== false) {
                $hostId = $row['host_id'];
            } else {
                $query = 'INSERT INTO host (host_name, host_register) '
                    . 'VALUES ("_Module_Meta", "2") ';
                $this->db->query($query);
                $res = $this->db->query($queryHost);
                $row = $res->fetchRow();
                if ($row !== false) {
                    $hostId = $row['host_id'];
                } else {
                    $hostId = 0;
                }
            }
        }

        return $hostId;
    }

    /**
     * Get the service_id for the meta service identified by the given meta ID.
     *
     * @param int $metaId The meta identifier used to build the service_description `meta_<metaId>`.
     * @return int The service_id for that meta service, or 0 if none exists.
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
         * Retrieve the meta ID encoded in a service's description for a given display name.
         *
         * Looks up the service by display name and extracts a numeric ID from a description matching `meta_<digits>`.
         *
         * @param string $serviceDisplayName The service display name to look up.
         * @throws PDOException If a database error occurs.
         * @return int|null The extracted meta ID as an integer, or `null` if no matching meta description is found.
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
            $metaId = $matches[1];
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
     * Retrieve specified columns for a meta service identified by its meta_id.
     *
     * @param int $id The meta_service.meta_id to query.
     * @param array $parameters List of column names to return; if empty, the function returns an empty array.
     * @throws PDOException If a database error occurs.
     * @return array Associative array of column => value for the matching row, or an empty array if no row is found.
     */
    public function getParameters($id, $parameters = [])
    {
        $sElement = '*';
        $values = [];
        if (empty($id) || empty($parameters)) {
            return [];
        }

        if (count($parameters) > 0) {
            $sElement = implode(',', $parameters);
        }

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
     * Ensure a virtual service exists for the given meta and return its service ID.
     *
     * @param int $metaId The meta identifier used to compose the service description (`meta_<metaId>`).
     * @param string $metaName The display name to set for the virtual service.
     * @throws PDOException If a database error occurs.
     * @throws RuntimeException If a newly inserted service's ID cannot be retrieved.
     * @return int The `service_id` for the meta (existing or newly created).
     */
    public function insertVirtualService($metaId, $metaName)
    {
        $hostId = $this->getRealHostId();
        $serviceId = null;

        $composedName = 'meta_' . $metaId;

        $selectStmt = $this->db->prepare(
            'SELECT service_id, display_name FROM service
            WHERE service_register = :register AND service_description = :description'
        );
        $selectStmt->bindValue(':register', '2', PDO::PARAM_STR);
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
            $ownTransaction = ! $this->db->inTransaction();
            if ($ownTransaction) {
                $this->db->beginTransaction();
            }
            try {
                $insertStmt = $this->db->prepare(
                    'INSERT INTO service (service_description, display_name, service_register)
                    VALUES (:description, :display_name, :register)'
                );
                $insertStmt->bindValue(':description', $composedName, PDO::PARAM_STR);
                $insertStmt->bindValue(':display_name', $metaName, PDO::PARAM_STR);
                $insertStmt->bindValue(':register', '2', PDO::PARAM_STR);
                $insertStmt->execute();

                $serviceId = (int) $this->db->lastInsertId();
                if ($serviceId <= 0) {
                    throw new RuntimeException('Failed to retrieve inserted service_id');
                }

                $relStmt = $this->db->prepare(
                    'INSERT INTO host_service_relation (host_host_id, service_service_id)
                    VALUES (:host_id, :service_id)'
                );
                $relStmt->bindValue(':host_id', (int) $hostId, PDO::PARAM_INT);
                $relStmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
                $relStmt->execute();

                if ($ownTransaction) {
                    $this->db->commit();
                }
            } catch (Throwable $e) {
                if ($ownTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                throw $e;
            }
        }

        return $serviceId;
    }
}
