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
/**
 * Copyright 2005-2017 Centreon
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
 */

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once __DIR__ . '/centreon_configuration_objects.class.php';
require_once __DIR__ . '/centreon_realtime_base.class.php';

/**
 * Class Centreon Realtime Host.
 */
class CentreonRealtimeHosts extends CentreonRealtimeBase
{
    /** @var CentreonDB */
    protected $aclObj;

    protected $admin;

    // parameters
    protected $limit;

    protected $number;

    protected $status;

    protected $hostgroup;

    protected $search;

    protected $searchHost;

    protected $viewType;

    protected $sortType;

    protected $order;

    protected $instance;

    protected $criticality;

    protected $fieldList;

    /**
     * CentreonConfigurationService constructor.
     */
    public function __construct()
    {
        global $centreon;

        parent::__construct();

        // Init ACL
        if (! $centreon->user->admin) {
            $this->admin = 0;
            $this->aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
        } else {
            $this->admin = 1;
        }
    }

    /**
     * @return array
     */
    public function getList()
    {
        $this->setHostFilters();
        $this->setHostFieldList();

        return $this->getHostState();
    }

    /**
     * @throws RestBadRequestException
     *
     * @return array
     */
    public function getHostState()
    {
        $queryValues = [];

        $tables = 'instances i, '
            . (! $this->admin ? 'centreon_acl, ' : '')
            . ($this->hostgroup ? 'hosts_hostgroups hhg, hostgroups hg, ' : '')
            . ($this->criticality ? 'customvariables cvs, ' : '')
            . '`hosts` h';

        $criticalityConditions = '';
        if ($this->criticality) {
            $criticalityConditions = <<<'SQL'
                AND h.host_id = cvs.host_id
                AND cvs.name = 'CRITICALITY_LEVEL'
                AND (cvs.service_id IS NULL OR cvs.service_id = 0)
                AND cvs.value = :criticality
                SQL;

            $queryValues['criticality'] = (string) $this->criticality;
        }

        $nonAdminConditions = '';
        if (! $this->admin) {
            $nonAdminConditions .= ' AND h.host_id = centreon_acl.host_id ';
            $nonAdminConditions .= $this->aclObj->queryBuilder(
                'AND',
                'centreon_acl.group_id',
                $this->aclObj->getAccessGroupsString()
            );
        }

        $viewTypeConditions = '';
        if ($this->viewType === 'unhandled') {
            $viewTypeConditions = <<<'SQL'
                AND h.state = 1
                AND h.state_type = '1'
                AND h.acknowledged = 0
                AND h.scheduled_downtime_depth = 0
                SQL;
        } elseif ($this->viewType === 'problems') {
            $viewTypeConditions = ' AND (h.state <> 0 AND h.state <> 4) ';
        }

        $statusConditions = '';
        if ($this->status === 'up') {
            $statusConditions = ' AND h.state = 0 ';
        } elseif ($this->status === 'down') {
            $statusConditions = ' AND h.state = 1 ';
        } elseif ($this->status === 'unreachable') {
            $statusConditions = ' AND h.state = 2 ';
        } elseif ($this->status === 'pending') {
            $statusConditions = ' AND h.state = 4 ';
        }

        $hostGroupConditions = '';
        if ($this->hostgroup) {
            $explodedValues = '';
            foreach (explode(',', $this->hostgroup) as $hgId => $hgValue) {
                if (! is_numeric($hgValue)) {
                    throw new \RestBadRequestException('Error, host group id must be numerical');
                }
                $explodedValues .= ':hostgroup' . $hgId . ',';
                $queryValues['hostgroup'][$hgId] = (int) $hgValue;
            }
            $explodedValues = rtrim($explodedValues, ',');

            $hostGroupConditions = <<<SQL
                AND h.host_id = hhg.host_id
                AND hg.hostgroup_id IN ({$explodedValues})
                AND hhg.hostgroup_id = hg.hostgroup_id
                SQL;
        }

        $instanceConditions = '';
        if ($this->instance !== -1 && ! empty($this->instance)) {
            if (! is_numeric($this->instance)) {
                throw new \RestBadRequestException('Error, instance id must be numerical');
            }
            $instanceConditions = ' AND h.instance_id = :instanceId ';
            $queryValues['instanceId'] = (int) $this->instance;
        }

        $order = '';
        if (
            ! isset($this->arguments['fields'])
            || is_null($this->arguments['fields'])
            || in_array($this->sortType, explode(',', $this->arguments['fields']), true)
        ) {
            $q = 'ASC';
            if (isset($this->order) && mb_strtoupper($this->order) === 'DESC') {
                $q = 'DESC';
            }

            switch ($this->sortType) {
                case 'id':
                    $order = " ORDER BY h.host_id {$q}, h.name";
                    break;
                case 'alias':
                    $order = " ORDER BY h.alias {$q}, h.name";
                    break;
                case 'address':
                    $order = " ORDER BY IFNULL(inet_aton(h.address), h.address) {$q}, h.name ";
                    break;
                case 'state':
                    $order = " ORDER BY h.state {$q}, h.name ";
                    break;
                case 'last_state_change':
                    $order = " ORDER BY h.last_state_change {$q}, h.name ";
                    break;
                case 'last_hard_state_change':
                    $order = " ORDER BY h.last_hard_state_change {$q}, h.name ";
                    break;
                case 'acknowledged':
                    $order = "ORDER BY h.acknowledged {$q}, h.name";
                    break;
                case 'last_check':
                    $order = " ORDER BY h.last_check {$q}, h.name ";
                    break;
                case 'check_attempt':
                    $order = " ORDER BY h.check_attempt {$q}, h.name ";
                    break;
                case 'max_check_attempts':
                    $order = " ORDER BY h.max_check_attempts {$q}, h.name";
                    break;
                case 'instance_name':
                    $order = " ORDER BY i.name {$q}, h.name";
                    break;
                case 'output':
                    $order = " ORDER BY h.output {$q}, h.name ";
                    break;
                case 'criticality':
                    $order = " ORDER BY criticality {$q}, h.name ";
                    break;
                case 'name':
                default:
                    $order = " ORDER BY h.name {$q}";
                    break;
            }
        }

        // Get Host status
        $query = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS DISTINCT {$this->fieldList}
            FROM {$tables}
            LEFT JOIN hosts_hosts_parents hph
                ON hph.parent_id = h.host_id
            LEFT JOIN `customvariables` cv
                ON (cv.host_id = h.host_id
                AND (cv.service_id IS NULL OR cv.service_id = 0)
                AND cv.name = 'CRITICALITY_LEVEL')
            WHERE h.name NOT LIKE '\_Module\_%'
                AND h.instance_id = i.instance_id
                {$criticalityConditions}
                {$nonAdminConditions}
                AND (h.name LIKE :searchName OR h.alias LIKE :searchAlias OR h.address LIKE :searchAddress)
                {$viewTypeConditions}
                {$statusConditions}
                {$hostGroupConditions}
                {$instanceConditions}
                AND h.enabled = 1
                {$order}
                LIMIT :offset,:limit
            SQL;

        $queryValues['searchName'] = '%' . (string) $this->search . '%';
        $queryValues['searchAlias'] = '%' . (string) $this->search . '%';
        $queryValues['searchAddress'] = '%' . (string) $this->search . '%';
        $queryValues['offset'] = (int) ($this->number * $this->limit);
        $queryValues['limit'] = (int) $this->limit;

        $stmt = $this->realTimeDb->prepare($query);

        if ($this->criticality) {
            $stmt->bindParam(':criticality', $queryValues['criticality'], PDO::PARAM_STR);
        }
        $stmt->bindParam(':searchName', $queryValues['searchName'], PDO::PARAM_STR);
        $stmt->bindParam(':searchAlias', $queryValues['searchAlias'], PDO::PARAM_STR);
        $stmt->bindParam(':searchAddress', $queryValues['searchAddress'], PDO::PARAM_STR);
        if (isset($queryValues['hostgroup'])) {
            foreach ($queryValues['hostgroup'] as $hgId => $hgValue) {
                $stmt->bindValue(':hostgroup' . $hgId, $hgValue, PDO::PARAM_INT);
            }
        }
        if (isset($queryValues['instanceId'])) {
            $stmt->bindParam(':instanceId', $queryValues['instanceId'], PDO::PARAM_INT);
        }

        $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
        $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        $stmt->execute();

        $dataList = [];
        while ($data = $stmt->fetch()) {
            $dataList[] = $data;
        }

        return $dataList;
    }

    /**
     * Set a list of filters send by the request.
     *
     * @throws RestBadRequestException
     */
    protected function setHostFilters(): void
    {
        // Pagination Elements
        $this->limit = $this->arguments['limit'] ?? 30;
        $this->number = $this->arguments['number'] ?? 0;
        if (! is_numeric($this->number) || ! is_numeric($this->limit)) {
            throw new \RestBadRequestException('Error, limit must be numerical');
        }

        // Filters
        if (isset($this->arguments['status'])) {
            $statusList = ['up', 'down', 'unreachable', 'pending', 'all'];
            if (in_array(mb_strtolower($this->arguments['status']), $statusList, true)) {
                $this->status = $this->arguments['status'];
            } else {
                throw new \RestBadRequestException('Bad status parameter');
            }
        } else {
            $this->status = null;
        }
        $this->hostgroup = $this->arguments['hostgroup'] ?? null;
        $this->search = $this->arguments['search'] ?? null;
        $this->instance = $this->arguments['instance'] ?? null;
        $this->criticality = $this->arguments['criticality'] ?? null;

        // view properties
        $this->viewType = $this->arguments['viewType'] ?? null;
        if (isset($this->arguments['order'])) {
            if (
                mb_strtolower($this->arguments['order']) === 'asc'
                || mb_strtolower($this->arguments['order']) === 'desc'
            ) {
                $this->order = $this->arguments['order'];
            } else {
                throw new \RestBadRequestException('Bad order parameter');
            }
        } else {
            $this->order = null;
        }
        $this->sortType = $this->arguments['sortType'] ?? null;
    }

    /**
     * Get selected fields by the request.
     *
     * @return array
     */
    protected function getFieldContent()
    {
        $tab = explode(',', $this->arguments['fields']);

        $fieldList = [];
        foreach ($tab as $key) {
            $fieldList[trim($key)] = 1;
        }

        return $fieldList;
    }

    /**
     * Set Filters.
     */
    protected function setHostFieldList(): void
    {
        $fields = [];
        if (! isset($this->arguments['fields'])) {
            $fields['h.host_id as id'] = 'host_id';
            $fields['h.name'] = 'name';
            $fields['h.alias'] = 'alias';
            $fields['h.address'] = 'address';
            $fields['h.state'] = 'state';
            $fields['h.state_type'] = 'state_type';
            $fields['h.output'] = 'output';
            $fields['h.max_check_attempts'] = 'max_check_attempts';
            $fields['h.check_attempt'] = 'check_attempt';
            $fields['h.last_check'] = 'last_check';
            $fields['h.last_state_change'] = 'last_state_change';
            $fields['h.last_hard_state_change'] = 'last_hard_state_change';
            $fields['h.acknowledged'] = 'acknowledged';
            $fields['i.name as instance_name'] = 'instance';
            $fields['cv.value as criticality'] = 'criticality';
        } else {
            $fieldList = $this->getFieldContent();

            if (isset($fieldList['id'])) {
                $fields['h.host_id as id'] = 'host_id';
            }
            if (isset($fieldList['name'])) {
                $fields['h.name'] = 'name';
            }
            if (isset($fieldList['alias'])) {
                $fields['h.alias'] = 'alias';
            }
            if (isset($fieldList['address'])) {
                $fields['h.address'] = 'address';
            }
            if (isset($fieldList['state'])) {
                $fields['h.state'] = 'state';
            }
            if (isset($fieldList['state_type'])) {
                $fields['h.state_type'] = 'state_type';
            }
            if (isset($fieldList['output'])) {
                $fields['h.output'] = 'output';
            }
            if (isset($fieldList['max_check_attempts'])) {
                $fields['h.max_check_attempts'] = 'max_check_attempts';
            }
            if (isset($fieldList['check_attempt'])) {
                $fields['h.check_attempt'] = 'check_attempt';
            }
            if (isset($fieldList['last_check'])) {
                $fields['h.last_check'] = 'last_check';
            }
            if (isset($fieldList['next_check'])) {
                $fields['h.next_check'] = 'next_check';
            }
            if (isset($fieldList['last_state_change'])) {
                $fields['h.last_state_change'] = 'last_state_change';
            }
            if (isset($fieldList['last_hard_state_change'])) {
                $fields['h.last_hard_state_change'] = 'last_hard_state_change';
            }
            if (isset($fieldList['acknowledged'])) {
                $fields['h.acknowledged'] = 'acknowledged';
            }
            if (isset($fieldList['instance'])) {
                $fields['i.name as instance_name'] = 'instance';
            }
            if (isset($fieldList['instance_id'])) {
                $fields['i.instance_id as instance_id'] = 'instance_id';
            }
            if (isset($fieldList['criticality'])) {
                $fields['cv.value as criticality'] = 'criticality';
            }
            if (isset($fieldList['passive_checks'])) {
                $fields['h.passive_checks'] = 'passive_checks';
            }
            if (isset($fieldList['active_checks'])) {
                $fields['h.active_checks'] = 'active_checks';
            }
            if (isset($fieldList['notify'])) {
                $fields['h.notify'] = 'notify';
            }
            if (isset($fieldList['action_url'])) {
                $fields['h.action_url'] = 'action_url';
            }
            if (isset($fieldList['notes_url'])) {
                $fields['h.notes_url'] = 'notes_url';
            }
            if (isset($fieldList['notes'])) {
                $fields['h.notes'] = 'notes';
            }
            if (isset($fieldList['icon_image'])) {
                $fields['h.icon_image'] = 'icon_image';
            }
            if (isset($fieldList['icon_image_alt'])) {
                $fields['h.icon_image_alt'] = 'icon_image_alt';
            }
            if (isset($fieldList['scheduled_downtime_depth'])) {
                $fields['h.scheduled_downtime_depth'] = 'scheduled_downtime_depth';
            }
            if (isset($fieldList['flapping'])) {
                $fields['h.flapping'] = 'flapping';
            }
        }

        // Build Field List
        $this->fieldList = '';
        foreach ($fields as $key => $value) {
            if ($this->fieldList !== '') {
                $this->fieldList .= ', ';
            }
            $this->fieldList .= $key;
        }
    }
}
