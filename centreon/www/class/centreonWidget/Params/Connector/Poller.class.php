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

require_once __DIR__ . '/../List.class.php';

/**
 * Class
 *
 * @class CentreonWidgetParamsConnectorPoller
 */
class CentreonWidgetParamsConnectorPoller extends CentreonWidgetParamsList
{
    /** @var int */
    public $userId;

    /**
     * CentreonWidgetParamsConnectorPoller constructor
     *
     * @param $db
     * @param $quickform
     * @param int $userId
     *
     * @throws PDOException
     */
    public function __construct($db, $quickform, $userId)
    {
        parent::__construct($db, $quickform, $userId);
    }

    /**
     * @param $paramId
     *
     * @throws PDOException
     * @return mixed|null[]
     */
    public function getListValues($paramId)
    {
        static $tab;

        if (! isset($tab)) {
            $tab = [null => null];
            $userACL = new CentreonACL($this->userId);
            $isContactAdmin = $userACL->admin;
            $request = 'SELECT SQL_CALC_FOUND_ROWS id, name FROM nagios_server ns';

            if (! $isContactAdmin) {
                $request .= ' INNER JOIN acl_resources_poller_relations arpr
                ON ns.id = arpr.poller_id
                INNER JOIN acl_resources res
                    ON arpr.acl_res_id = res.acl_res_id
                INNER JOIN acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                LEFT JOIN acl_group_contacts_relations agcr
                    ON ag.acl_group_id = agcr.acl_group_id
                LEFT JOIN acl_group_contactgroups_relations agcgr
                    ON ag.acl_group_id = agcgr.acl_group_id
                LEFT JOIN contactgroup_contact_relation cgcr
                    ON cgcr.contactgroup_cg_id = agcgr.cg_cg_id
                WHERE (agcr.contact_contact_id = :userId OR cgcr.contact_contact_id = :userId)';
            }

            $request .= ! $isContactAdmin ? ' AND' : ' WHERE';
            $request .= " ns_activate = '1' ORDER BY name";
            $statement = $this->db->prepare($request);

            if (! $isContactAdmin) {
                $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
            }
            $statement->execute();
            $entriesCount = $this->db->query('SELECT FOUND_ROWS()');

            if ($entriesCount !== false && ($total = $entriesCount->fetchColumn()) !== false) {
                // it means here that there is poller relations with this user
                if ((int) $total === 0) {
                    // if no relations found for this user it means that he can see all poller available
                    $statement = $this->db->query(
                        "SELECT id, name FROM nagios_server WHERE ns_activate = '1'"
                    );
                }

                while (($record = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $tab[$record['id']] = $record['name'];
                }
            }
        }

        return $tab;
    }
}
