<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

/**
 * Class
 *
 * @class CentreonServicegroups
 */
class CentreonServicegroups
{
    /** @var CentreonDB */
    private $DB;
    /** @var */
    private $relationCache;
    /** @var */
    private $dataTree;

    /**
     * CentreonServicegroups constructor
     *
     * @param CentreonDB $pearDB
     */
    public function __construct($pearDB)
    {
        $this->DB = $pearDB;
    }

    /**
     * @param null $sgId
     *
     * @return array|void
     * @throws PDOException
     */
    public function getServiceGroupServices($sgId = null)
    {
        if (!$sgId) {
            return;
        }

        $services = [];
        $query = "SELECT host_host_id, service_service_id "
            . "FROM servicegroup_relation "
            . "WHERE servicegroup_sg_id = " . $sgId . " "
            . "AND host_host_id IS NOT NULL "
            . "UNION "
            . "SELECT hgr.host_host_id, hsr.service_service_id "
            . "FROM servicegroup_relation sgr, host_service_relation hsr, hostgroup_relation hgr "
            . "WHERE sgr.servicegroup_sg_id = " . $sgId . " "
            . "AND sgr.hostgroup_hg_id = hsr.hostgroup_hg_id "
            . "AND hsr.service_service_id = sgr.service_service_id "
            . "AND sgr.hostgroup_hg_id = hgr.hostgroup_hg_id ";

        $res = $this->DB->query($query);
        while ($row = $res->fetchRow()) {
            $services[] = [$row['host_host_id'], $row['service_service_id']];
        }
        $res->closeCursor();

        return $services;
    }

    /**
     * Returns a filtered array with only integer ids
     *
     * @param  int[] $ids
     * @return int[] filtered
     */
    private function filteredArrayId(array $ids): array
    {
        return array_filter($ids, function ($id) {
            return is_numeric($id);
        });
    }

    /**
     * Get service groups id and name from ids
     *
     * @param int[] $serviceGroupsIds
     * @return array $retArr [['id' => integer, 'name' => string],...]
     */
    public function getServicesGroups($serviceGroupsIds = [])
    {
        $servicesGroups = [];

        if (!empty($serviceGroupsIds)) {
            /* checking here that the array provided as parameter
             * is exclusively made of integers (servicegroup ids)
             */
            $filteredSgIds = $this->filteredArrayId($serviceGroupsIds);
            $sgParams = [];
            if ($filteredSgIds !== []) {
                /*
                 * Building the sgParams hash table in order to correctly
                 * bind ids as ints for the request.
                 */
                foreach ($filteredSgIds as $index => $filteredSgId) {
                    $sgParams[':sgId' . $index] = $filteredSgId;
                }

                $stmt = $this->DB->prepare(
                    'SELECT sg_id, sg_name FROM servicegroup ' .
                    'WHERE sg_id IN ( ' . implode(',', array_keys($sgParams)) . ' )'
                );

                foreach ($sgParams as $index => $value) {
                    $stmt->bindValue($index, $value, PDO::PARAM_INT);
                }

                $stmt->execute();

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $servicesGroups[] = [
                        'id' => $row['sg_id'],
                        'name' => $row['sg_name']
                    ];
                }
            }
        }

        return $servicesGroups;
    }


    /**
     *
     * @param type $field
     * @return string
     */
    public static function getDefaultValuesParameters($field)
    {
        $parameters = [];
        $parameters['currentObject']['table'] = 'servicegroup';
        $parameters['currentObject']['id'] = 'sg_id';
        $parameters['currentObject']['name'] = 'sg_name';
        $parameters['currentObject']['comparator'] = 'sg_id';

        switch ($field) {
            case 'sg_hServices':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonService';
                $parameters['relationObject']['table'] = 'servicegroup_relation';
                $parameters['relationObject']['field'] = 'host_host_id';
                $parameters['relationObject']['additionalField'] = 'service_service_id';
                $parameters['relationObject']['comparator'] = 'servicegroup_sg_id';
                break;
            case 'sg_tServices':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonServicetemplates';
                $parameters['externalObject']['objectOptions'] = ['withHosttemplate' => true];
                $parameters['relationObject']['table'] = 'servicegroup_relation';
                $parameters['relationObject']['field'] = 'host_host_id';
                $parameters['relationObject']['additionalField'] = 'service_service_id';
                $parameters['relationObject']['comparator'] = 'servicegroup_sg_id';
                break;
            case 'sg_hgServices':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonService';
                $parameters['externalObject']['objectOptions'] = ['hostgroup' => true];
                $parameters['relationObject']['table'] = 'servicegroup_relation';
                $parameters['relationObject']['field'] = 'hostgroup_hg_id';
                $parameters['relationObject']['additionalField'] = 'service_service_id';
                $parameters['relationObject']['comparator'] = 'servicegroup_sg_id';
                break;
        }

        return $parameters;
    }

    /**
     * @param array<int|string, int|string> $list
     * @param string $prefix
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function createMultipleBindQuery(array $list, string $prefix): array
    {
        $bindValues = [];
        foreach ($list as $index => $id) {
            $bindValues[$prefix . $index] = $id;
        }

        return [$bindValues, implode(', ', array_keys($bindValues))];
    }

    /**
     * @param array $values
     * @param array $options
     * @return array
     */
    public function getObjectForSelect2($values = [], $options = [])
    {
        global $centreon;
        $items = [];
        $sgAcl = [];

        # get list of authorized servicegroups
        if (
            ! $centreon->user->access->admin
            && $centreon->user->access->hasAccessToAllServiceGroups === false
        ) {
            $sgAcl = $centreon->user->access->getServiceGroupAclConf(
                null,
                'broker',
                ['distinct' => true, 'fields' => ['servicegroup.sg_id'], 'get_row' => 'sg_id', 'keys' => ['sg_id'], 'conditions' => ['servicegroup.sg_id' => ['IN', $values]]],
                true
            );
        }

        $queryValues = [];
        $whereCondition = '';
        if (! empty($values)) {
            foreach ($values as $key => $value) {
                $serviceGroupIds = explode(',', $value);
                foreach ($serviceGroupIds as $serviceGroupId) {
                    $queryValues[':sg_' . $serviceGroupId] = (int) $serviceGroupId;
                }
            }

            $whereCondition = ' WHERE sg_id IN (' . implode(',', array_keys($queryValues)) . ')';
        }

        $request = <<<SQL
            SELECT
                sg_id,
                sg_name
            FROM servicegroup
            $whereCondition
            ORDER BY sg_name
        SQL;

        $statement = $this->DB->prepare($request);

        foreach ($queryValues as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }
        $statement->execute();

        while ($record = $statement->fetch(PDO::FETCH_ASSOC)) {
            # hide unauthorized servicegroups
            $hide = false;
            if (
                ! $centreon->user->access->admin
                && $centreon->user->access->hasAccessToAllServiceGroups === false
                && ! in_array($record['sg_id'], $sgAcl)
            ) {
                $hide = true;
            }
            $items[] = [
                'id' => $record['sg_id'],
                'text' => $record['sg_name'],
                'hide' => $hide
            ];
        }
        return $items;
    }

    /**
     * @param string $sgName
     *
     * @return array<array{service:string,service_id:int,host:string,sg_name:string}>
     *@throws Throwable
     *
     */
    public function getServicesByServicegroupName(string $sgName): array
    {
        $serviceList = [];
        $query = <<<'SQL'
            SELECT service_description, service_id, host_name
            FROM servicegroup_relation sgr, service s, servicegroup sg, host h
            WHERE sgr.service_service_id = s.service_id
                AND sgr.servicegroup_sg_id = sg.sg_id
                AND s.service_activate = '1'
                AND s.service_register = '1'
                AND sgr.host_host_id = h.host_id
                AND sg.sg_name = :sgName
            SQL;
        $statement = $this->DB->prepare($query);
        $statement->bindValue(':sgName', $this->DB->escape($sgName), PDO::PARAM_STR);
        $statement->execute();
        while ($elem = $statement->fetch()) {
            /** @var array{service_description:string,service_id:int,host_name:string} $elem */
            $serviceList[] = [
                'service' => $elem['service_description'],
                'service_id' => $elem['service_id'],
                'host' => $elem['host_name'],
                'sg_name' => $sgName,
            ];
        }
        return $serviceList;
    }

    /**
     * @param string $sgName
     *
     * @return array<array{service:string,service_id:int,host:string,sg_name:string}>
     *@throws Throwable
     *
     */
    public function getServicesThroughtServiceTemplatesByServicegroupName(string $sgName): array
    {
        $serviceList = [];
        $query = <<<'SQL'
            SELECT s.service_description, s.service_id, h.host_name
            FROM `servicegroup_relation` sgr
            JOIN `servicegroup` sg
                ON sg.sg_id = sgr.servicegroup_sg_id
            JOIN `service` st
                ON st.service_id = sgr.service_service_id
                AND st.service_activate = '1'
                AND st.service_register = '0'
            JOIN `service` s
                ON s.service_template_model_stm_id = st.service_id
                AND s.service_activate = '1'
                AND s.service_register = '1'
            JOIN `host_service_relation` hsrel
                ON hsrel.service_service_id = s.service_id
            JOIN `host` h
                ON h.host_id = hsrel.host_host_id
            WHERE sg.sg_name = :sgName
            SQL;

        $statement = $this->DB->prepare($query);
        $statement->bindValue(':sgName', $this->DB->escape($sgName), PDO::PARAM_STR);
        $statement->execute();
        while ($elem = $statement->fetch()) {
            /** @var array{service_description:string,service_id:int,host_name:string} $elem */
            $serviceList[] = [
                'service' => $elem['service_description'],
                'service_id' => $elem['service_id'],
                'host' => $elem['host_name'],
                'sg_name' => $sgName,
            ];
        }
        return $serviceList;
    }

    /**
     * @param $sgName
     * @return int|mixed
     */
    public function getServicesGroupId($sgName)
    {
        static $ids = [];

        if (!isset($ids[$sgName])) {
            $query = "SELECT sg_id FROM servicegroup WHERE sg_name = '" . $this->DB->escape($sgName) . "'";
            $res = $this->DB->query($query);
            if ($res->numRows()) {
                $row = $res->fetchRow();
                $ids[$sgName] = $row['sg_id'];
            }
        }
        return $ids[$sgName] ?? 0;
    }
}
