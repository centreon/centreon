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
require_once __DIR__ . '/centreon_configuration_objects.class.php';

/**
 * Class
 *
 * @class CentreonConfigurationContact
 */
class CentreonConfigurationContact extends CentreonConfigurationObjects
{
    /**
     * CentreonConfigurationContact constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
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

        $filterContact = ['contact_register' => '1'];
        if (isset($this->arguments['q'])) {
            $filterContact['contact_name'] = ['LIKE', '%' . $this->arguments['q'] . '%'];
            $filterContact['contact_alias'] = ['OR', 'LIKE', '%' . $this->arguments['q'] . '%'];
        }

        $acl = new CentreonACL($centreon->user->user_id);

        $contacts = $acl->getContactAclConf(
            ['fields' => ['contact_id', 'contact_name'], 'get_row' => 'contact_name', 'keys' => ['contact_id'], 'conditions' => $filterContact, 'order' => ['contact_name'], 'pages' => $range, 'total' => true]
        );

        $contactList = [];
        foreach ($contacts['items'] as $id => $contactName) {
            $contactList[] = ['id' => $id, 'text' => $contactName];
        }

        return ['items' => $contactList, 'total' => $contacts['total']];
    }
}
