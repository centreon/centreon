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

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonContactgroup.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonLDAP.class.php';
require_once __DIR__ . '/centreon_configuration_objects.class.php';

/**
 * Class
 *
 * @class CentreonConfigurationContactgroup
 */
class CentreonConfigurationContactgroup extends CentreonConfigurationObjects
{
    /**
     * CentreonConfigurationContactgroup constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        global $centreon;

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $limit = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $offset = $this->arguments['page_limit'];
            $range = $limit . ',' . $offset;
        } else {
            $range = '';
        }

        $filterContactgroup = [];
        $ldapFilter = '';
        if (isset($this->arguments['q'])) {
            $filterContactgroup['cg_name'] = ['LIKE', '%' . $this->arguments['q'] . '%'];
            $filterContactgroup['cg_alias'] = ['OR', 'LIKE', '%' . $this->arguments['q'] . '%'];
            $ldapFilter = $this->arguments['q'];
        }

        $cg = new CentreonContactgroup($this->pearDB);
        $acl = new CentreonACL($centreon->user->user_id);

        $aclCgs = $acl->getContactGroupAclConf(
            ['fields' => ['cg_id', 'cg_name', 'cg_type', 'ar_name'], 'get_row' => null, 'keys' => ['cg_id'], 'conditions' => $filterContactgroup, 'order' => ['cg_name'], 'pages' => $range, 'total' => true],
            false
        );

        $contactgroupList = [];
        foreach ($aclCgs['items'] as $id => $contactgroup) {
            // If we query local contactgroups and the contactgroup type is ldap, we skip it
            if (
                isset($this->arguments['type'])
                && ($this->arguments['type'] === 'local')
                && ($contactgroup['cg_type'] === 'ldap')
            ) {
                --$aclCgs['total'];
                continue;
            }
            $sText = $contactgroup['cg_name'];
            if ($contactgroup['cg_type'] == 'ldap') {
                $sText .= ' (LDAP : ' . $contactgroup['ar_name'] . ')';
            }
            $id = $contactgroup['cg_id'];
            $contactgroupList[] = ['id' => $id, 'text' => $sText];
        }

        // get Ldap contactgroups
        // If we don't query local contactgroups, we can return an array with ldap contactgroups
        if (! isset($this->arguments['type']) || $this->arguments['type'] !== 'local') {
            $ldapCgs = [];
            if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
                $maxItem = $this->arguments['page_limit'] * $this->arguments['page'];
                if ($aclCgs['total'] <= $maxItem) {
                    $ldapCgs = $cg->getLdapContactgroups($ldapFilter);
                }
            } else {
                $ldapCgs = $cg->getLdapContactgroups($ldapFilter);
            }

            foreach ($ldapCgs as $key => $value) {
                $sTemp = $value;
                if (! $this->uniqueKey($sTemp, $contactgroupList)) {
                    $contactgroupList[] = ['id' => $key, 'text' => $value];
                }
            }
        }

        return ['items' => $contactgroupList, 'total' => $aclCgs['total']];
    }

    /**
     * @param $val
     * @param array $array
     *
     * @return bool
     */
    protected function uniqueKey($val, &$array)
    {

        if (! empty($val) && count($array) > 0) {
            foreach ($array as $key => $value) {
                if ($value['text'] == $val) {
                    return true;
                }
            }
        }

        return false;
    }
}
