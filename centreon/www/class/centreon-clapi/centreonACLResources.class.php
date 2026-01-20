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

namespace CentreonClapi;

use CentreonDB;
use PDOException;
use Pimple\Container;

/**
 * Class
 *
 * @class CentreonACLResources
 * @package CentreonClapi
 */
class CentreonACLResources
{
    /** @var CentreonDB */
    public $_DB;

    /**
     * CentreonACLResources constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        $this->_DB = $dependencyInjector['configuration_db'];
    }

    /**
     * @param string $name
     *
     * @throws PDOException
     * @return int
     */
    public function getACLResourceID($name)
    {
        $request = "SELECT acl_group_id FROM acl_groups WHERE acl_group_name LIKE '"
            . htmlentities($name, ENT_QUOTES) . "'";
        $DBRESULT = $this->_DB->query($request);
        $data = $DBRESULT->fetchRow();
        if ($data['acl_group_id']) {
            return $data['acl_group_id'];
        }

        return 0;
    }

    /**
     * @param $contact_id
     * @param $aclid
     *
     * @throws PDOException
     * @return int
     */
    public function addContact($contact_id, $aclid)
    {
        $request = 'DELETE FROM acl_group_contacts_relations '
            . "WHERE acl_group_id = '{$aclid}' AND contact_contact_id = '{$contact_id}'";
        $this->_DB->query($request);

        $request = 'INSERT INTO acl_group_contacts_relations '
            . '(acl_group_id, contact_contact_id) '
            . "VALUES ('" . $aclid . "', '" . $contact_id . "')";
        $this->_DB->query($request);

        return 0;
    }

    /**
     * @param $contact_id
     * @param $aclid
     *
     * @throws PDOException
     * @return int
     */
    public function delContact($contact_id, $aclid)
    {
        $request = 'DELETE FROM acl_group_contacts_relations '
            . "WHERE acl_group_id = '{$aclid}' AND contact_contact_id = '{$contact_id}'";
        $this->_DB->query($request);

        return 0;
    }

    /**
     * @throws PDOException
     * @return int
     */
    public function updateACL()
    {
        $request = "UPDATE `acl_resources` SET `changed` = '1'";
        $this->_DB->query($request);

        return 0;
    }
}
