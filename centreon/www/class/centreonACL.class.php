<?php
/*
 * Copyright 2005-2024 Centreon
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

require_once realpath(__DIR__ . "/centreonDBInstance.class.php");
require_once _CENTREON_PATH_ . '/www/include/common/sqlCommonFunction.php';

/**
 * Class
 *
 * @class CentreonACL
 * @description Class for Access Control List management
 */
class CentreonACL
{
    public const ACL_ACCESS_NONE = 0;
    public const ACL_ACCESS_READ_WRITE = 1;
    public const ACL_ACCESS_READ_ONLY = 2;

    /** @var int */
    private $userID; /* ID of the user */
    /** @var array|null */
    private ?array $parentTemplates = null;
    /** @var bool|null */
    public $admin; /* Flag that tells us if the user is admin or not */
    /** @var array */
    private $accessGroups = []; /* Access groups the user belongs to */
    /** @var array */
    private $resourceGroups = []; /* Resource groups the user belongs to */
    /** @var array */
    public $hostGroups = []; /* Hostgroups the user can see */
    /** @var array */
    protected $pollers = []; /* Pollers the user can see */
    /** @var array */
    private $hostGroupsAlias = []; /* Hostgroups by alias the user can see */
    /** @var array */
    private $serviceGroups = []; /* Servicegroups the user can see */
    /** @var array */
    private $serviceGroupsAlias = []; /* Servicegroups by alias the user can see */
    /** @var array */
    private $serviceCategories = []; /* Service categories the user can see */
    /** @var array */
    private $hostCategories = [];
    /** @var array */
    private $actions = []; /* Actions the user can do */
    /** @var array */
    private $hostGroupsFilter = [];
    /** @var array */
    private $serviceGroupsFilter = [];
    /** @var array */
    private $serviceCategoriesFilter = [];
    /** @var array */
    public $topology = [];
    /** @var string */
    public $topologyStr = "";
    /** @var array */
    private $metaServices = [];
    /** @var string */
    private $metaServiceStr = "";
    /** @var array */
    private $tempTableArray = [];
    /** @var bool */
    public $hasAccessToAllHostGroups = false;
    /** @var bool */
    public $hasAccessToAllServiceGroups = false;

    /**
     * CentreonACL constructor
     *
     * @param int $userId
     * @param bool|null $isAdmin
     */
    public function __construct($userId, $isAdmin = null)
    {
        $this->userID = $userId;

        if (!isset($isAdmin)) {
            $db = CentreonDBInstance::getDbCentreonInstance();
            $query = "SELECT contact_admin "
                . "FROM `contact` "
                . "WHERE contact_id = '" . CentreonDB::escape($userId) . "' "
                . "LIMIT 1 ";
            $result = $db->query($query);
            $row = $result->fetch();
            $this->admin = $row['contact_admin'];
        } else {
            $this->admin = $isAdmin;
        }

        if (! $this->admin) {
            $this->setAccessGroups();
            $this->setResourceGroups();
            $this->hasAccessToAllHostGroups = $this->hasAccessToAllHostGroups();
            $this->hasAccessToAllServiceGroups = $this->hasAccessToAllServiceGroups();
            $this->setHostGroups();
            $this->setPollers();
            $this->setServiceGroups();
            $this->setServiceCategories();
            $this->setHostCategories();
            $this->setMetaServices();
            $this->setActions();
        }

        $this->setTopology();
        $this->getACLStr();
    }

    /**
     * Function that will reset ACL
     *
     * @return void
     */
    private function resetACL(): void
    {
        $this->parentTemplates = null;
        $this->resourceGroups = [];
        $this->serviceGroups = [];
        $this->serviceCategories = [];
        $this->actions = [];
        $this->topology = [];
        $this->pollers = [];
        $this->setAccessGroups();
        $this->setResourceGroups();
        $this->setHostGroups();
        $this->setPollers();
        $this->setServiceGroups();
        $this->setServiceCategories();
        $this->setHostCategories();
        $this->setMetaServices();
        $this->setTopology();
        $this->getACLStr();
        $this->setActions();
        $this->hasAccessToAllHostGroups = false;
        $this->hasAccessToAllServiceGroups = false;
    }

    /**
     * Function that will check whether or not the user needs to rebuild his ACL
     *
     * @return void
     */
    private function checkUpdateACL(): void
    {
        if (is_null($this->parentTemplates)) {
            $this->loadParentTemplates();
        }

        if (!$this->admin) {
            $db = CentreonDBInstance::getDbCentreonInstance();
            $query = "SELECT update_acl "
                . "FROM session "
                . "WHERE update_acl = '1' "
                . "AND user_id IN (" . join(', ', $this->parentTemplates) . ") ";
            $result = $db->query($query);
            if ($result->rowCount()) {
                $db->query(
                    "UPDATE session SET update_acl = '0' " .
                    "WHERE user_id IN (" . join(', ', $this->parentTemplates) . ")"
                );

                $this->resetACL();
            }
        }
    }

    /*
     * Setter functions
     */

    /**
     * Access groups Setter
     *
     * @return void
     */
    private function setAccessGroups(): void
    {
        if (is_null($this->parentTemplates)) {
            $this->loadParentTemplates();
        }
        if ($this->parentTemplates !== []) {
            [$binValues, $subQuery] = createMultipleBindQuery($this->parentTemplates, ':id_');

            $this->accessGroups = [];
            $query = <<<SQL
                SELECT acl.acl_group_id, acl.acl_group_name
                FROM acl_groups acl
                INNER JOIN acl_group_contacts_relations agcr
                    ON acl.acl_group_id = agcr.acl_group_id
                WHERE acl.acl_group_activate = '1'
                    AND agcr.contact_contact_id IN ({$subQuery})
                UNION
                SELECT acl.acl_group_id, acl.acl_group_name
                FROM acl_groups acl
                INNER JOIN acl_group_contactgroups_relations agcgr
                    ON acl.acl_group_id = agcgr.acl_group_id
                INNER JOIN contactgroup_contact_relation cgcr
                    ON cgcr.contactgroup_cg_id = agcgr.cg_cg_id
                WHERE acl.acl_group_activate = '1'
                    AND cgcr.contact_contact_id IN ({$subQuery})
                SQL;

            $statement = CentreonDBInstance::getDbCentreonInstance()->prepare($query);
            foreach ($binValues as $key => $value) {
                $statement->bindValue($key, $value, PDO::PARAM_INT);
            }
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);

            foreach($statement as $result) {
                $this->accessGroups[$result['acl_group_id']] = $result['acl_group_name'];
            }
        }
    }

    /**
     * Check is all_hostgroups is activated at least of one ACL Group which this user is linked
     *
     * @return bool
     */
    private function hasAccessToAllHostGroups(): bool
    {
        [$bindValues, $bindQuery] = createMultipleBindQuery(
            list: explode(',', $this->getAccessGroupsString()),
            prefix: ':access_group_id_'
        );

        $request = <<<SQL
            SELECT res.all_hostgroups
            FROM acl_resources res
            INNER JOIN acl_res_group_relations argr
                ON argr.acl_res_id = res.acl_res_id
            INNER JOIN acl_groups ag
                ON ag.acl_group_id = argr.acl_group_id
            WHERE res.acl_res_activate = '1' AND ag.acl_group_id IN ({$bindQuery})
            SQL;

        $statement = CentreonDBInstance::getDbCentreonInstance()->prepare($request);

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }

        $statement->execute();

        while (false !== ($hasAccessToAll = $statement->fetchColumn())) {
            if (true === (bool) $hasAccessToAll) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check is all_servicegroups is activated at least of one ACL Group which this user is linked
     *
     * @return bool
     */
    private function hasAccessToAllServiceGroups(): bool
    {
        [$bindValues, $bindQuery] = createMultipleBindQuery(
            list: explode(',', $this->getAccessGroupsString()),
            prefix: ':access_group_id_'
        );

        $request = <<<SQL
            SELECT res.all_servicegroups
            FROM acl_resources res
            INNER JOIN acl_res_group_relations argr
                ON argr.acl_res_id = res.acl_res_id
            INNER JOIN acl_groups ag
                ON ag.acl_group_id = argr.acl_group_id
            WHERE res.acl_res_activate = '1' AND ag.acl_group_id IN ({$bindQuery})
            SQL;

        $statement = CentreonDBInstance::getDbCentreonInstance()->prepare($request);

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }

        $statement->execute();

        while (false !== ($hasAccessToAll = $statement->fetchColumn())) {
            if (true === (bool) $hasAccessToAll) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resource groups Setter
     *
     * @return void
     */
    private function setResourceGroups(): void
    {
        $query = "SELECT acl.acl_res_id, acl.acl_res_name "
            . "FROM acl_resources acl, acl_res_group_relations argr "
            . "WHERE acl.acl_res_id = argr.acl_res_id "
            . "AND acl.acl_res_activate = '1' "
            . "AND argr.acl_group_id IN (" . $this->getAccessGroupsString() . ") "
            . "ORDER BY acl.acl_res_name ASC";
        $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query($query);
        while ($row = $DBRESULT->fetchRow()) {
            $this->resourceGroups[$row['acl_res_id']] = $row['acl_res_name'];
        }
        $DBRESULT->closeCursor();
    }

    /**
     * Access groups Setter
     *
     * @return void
     */
    private function setHostGroups(): void
    {
        $this->hostGroups = [];
        $this->hostGroupsAlias = [];
        $this->hostGroupsFilter = [];
        $aclSubRequest = '';
        $bindValues = [];

        if ($this->hasAccessToAllHostGroups === false) {
            $accessGroups = $this->getAccessGroups();
            if ($accessGroups === []) {
                return;
            }

            [$bindValues, $bindQuery] = createMultipleBindQuery(
                list: array_keys($accessGroups),
                prefix: ':access_group_id_'
            );

            $aclSubRequest .= ' AND argr.acl_group_id IN (' . $bindQuery . ')';
        }

        $request = <<<SQL
            SELECT
                hg.hg_id,
                hg.hg_name,
                hg.hg_alias
            FROM hostgroup hg
            INNER JOIN acl_resources_hg_relations arhr
                ON hg.hg_id = arhr.hg_hg_id
            INNER JOIN acl_resources res
                ON res.acl_res_id = arhr.acl_res_id
            INNER JOIN acl_res_group_relations argr
                ON argr.acl_res_id = res.acl_res_id
            WHERE hg.hg_activate = '1'
            $aclSubRequest
            GROUP BY hg.hg_id, hg.hg_name
            ORDER BY hg.hg_name ASC
        SQL;

        $statement = CentreonDBInstance::getDbCentreonInstance()->prepare($request);

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }

        $statement->execute();

        while($record = $statement->fetch(PDO::FETCH_ASSOC)) {
            $this->hostGroups[$record['hg_id']] = $record['hg_name'];
            $this->hostGroupsAlias[$record['hg_id']] = $record['hg_alias'];

            // INNER JOIN might not give anything is the user is not linked to ACL resources...
            if (isset($record['acl_res_id'])) {
                $this->hostGroupsFilter[$record['acl_res_id']][$record['hg_id']] = $record['hg_id'];
            }
        }
    }

    /**
     * Poller Setter
     *
     * @return void
     */
    private function setPollers(): void
    {
        $pearDB = CentreonDBInstance::getDbCentreonInstance();
        $query = "SELECT ns.id, ns.name, arpr.acl_res_id "
            . "FROM nagios_server ns, acl_resources_poller_relations arpr "
            . "WHERE ns.id = arpr.poller_id "
            . "AND ns.ns_activate = '1' "
            . "AND arpr.acl_res_id IN (" . $this->getResourceGroupsString() . ") "
            . "ORDER BY ns.name ASC ";
        $DBRESULT = $pearDB->query($query);
        if ($DBRESULT->rowCount()) {
            while ($row = $DBRESULT->fetchRow()) {
                $this->pollers[$row['id']] = $row['name'];
            }
        } else {
            $query = "SELECT ns.id, ns.name "
                . "FROM nagios_server ns "
                . "WHERE ns.ns_activate = '1' "
                . "ORDER BY ns.name ASC ";
            $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query($query);
            while ($row = $DBRESULT->fetchRow()) {
                $this->pollers[$row['id']] = $row['name'];
            }
        }
        $DBRESULT->closeCursor();
    }

    /**
     * Service groups Setter
     *
     * @return void
     */
    private function setServiceGroups(): void
    {
        $aclSubRequest = '';
        $bindValues = [];

        if ($this->hasAccessToAllServiceGroups === false) {
            [$bindValues, $bindQuery] = createMultipleBindQuery(
                list: explode(',', $this->getAccessGroupsString()),
                prefix: ':access_group_id_'
            );

            $aclSubRequest .= ' AND arsr.acl_res_id IN (' . $bindQuery . ')';
        }

        $request = <<<SQL
            SELECT
                sg.sg_id,
                sg.sg_name,
                sg.sg_alias
            FROM servicegroup sg
            INNER JOIN acl_resources_sg_relations arsr
                ON sg.sg_id = arsr.sg_id
            WHERE sg.sg_activate = '1'
            $aclSubRequest
            ORDER BY sg.sg_name ASC
        SQL;

        $statement = CentreonDBInstance::getDbCentreonInstance()->prepare($request);

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }

        $statement->execute();

        while ($record = $statement->fetch(PDO::FETCH_ASSOC)) {
            $this->serviceGroups[$record['sg_id']] = $record['sg_name'];
            $this->serviceGroupsAlias[$record['sg_id']] = $record['sg_alias'];

            // INNER JOIN might not give anything is the user is not linked to ACL resources...
            if (isset($record['acl_res_id'])) {
                $this->serviceGroupsFilter[$record['acl_res_id']][$record['sg_id']] = $record['sg_id'];
            }
        }
    }

    /**
     * Service categories Setter
     *
     * @return void
     */
    private function setServiceCategories(): void
    {
        $query = "SELECT sc.sc_id, sc.sc_name, arsr.acl_res_id "
            . "FROM service_categories sc, acl_resources_sc_relations arsr "
            . "WHERE sc.sc_id = arsr.sc_id "
            . "AND sc.sc_activate = '1' "
            . "AND arsr.acl_res_id IN (" . $this->getResourceGroupsString() . ") "
            . "ORDER BY sc.sc_name ASC ";

        $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query($query);
        while ($row = $DBRESULT->fetchRow()) {
            $this->serviceCategories[$row['sc_id']] = $row['sc_name'];
            $this->serviceCategoriesFilter[$row['acl_res_id']][$row['sc_id']] = $row['sc_id'];
        }
        $DBRESULT->closeCursor();
    }

    /**
     * Host categories setter
     *
     * @return void
     */
    private function setHostCategories(): void
    {
        $query = "SELECT hc.hc_id, hc.hc_name, arhr.acl_res_id "
            . "FROM hostcategories hc, acl_resources_hc_relations arhr "
            . "WHERE hc.hc_id = arhr.hc_id "
            . "AND hc.hc_activate = '1' "
            . "AND arhr.acl_res_id IN (" . $this->getResourceGroupsString() . ") "
            . "ORDER BY hc.hc_name ASC ";

        $res = CentreonDBInstance::getDbCentreonInstance()->query($query);
        while ($row = $res->fetchRow()) {
            $this->hostCategories[$row['hc_id']] = $row['hc_name'];
        }
    }

    /**
     * Access meta Setter
     *
     * @return void
     */
    private function setMetaServices(): void
    {
        $query = "SELECT ms.meta_id, ms.meta_name, arsr.acl_res_id " .
            "FROM meta_service ms, acl_resources_meta_relations arsr " .
            "WHERE ms.meta_id = arsr.meta_id " .
            "AND arsr.acl_res_id IN (" . $this->getResourceGroupsString() . ") " .
            "ORDER BY ms.meta_name ASC";
        $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query($query);
        $this->metaServiceStr = "";
        while ($row = $DBRESULT->fetchRow()) {
            $this->metaServices[$row['meta_id']] = $row['meta_name'];
            if ($this->metaServiceStr != "") {
                $this->metaServiceStr .= ",";
            }
            $this->metaServiceStr .= "'" . $row['meta_id'] . "'";
        }
        if (!$this->metaServiceStr) {
            $this->metaServiceStr = "''";
        }
        $DBRESULT->closeCursor();
    }

    /**
     * Actions Setter
     *
     * @return void
     */
    private function setActions(): void
    {
        $query = "SELECT ar.acl_action_name "
            . "FROM acl_group_actions_relations agar, acl_actions a, acl_actions_rules ar "
            . "WHERE a.acl_action_id = agar.acl_action_id "
            . "AND agar.acl_action_id = ar.acl_action_rule_id "
            . "AND a.acl_action_activate = '1' "
            . "AND agar.acl_group_id IN (" . $this->getAccessGroupsString() . ") "
            . "ORDER BY ar.acl_action_name ASC ";
        $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query($query);
        while ($row = $DBRESULT->fetchRow()) {
            $this->actions[$row['acl_action_name']] = $row['acl_action_name'];
        }
        $DBRESULT->closeCursor();
    }

    /**
     *  Topology setter
     *
     * @return void
     */
    private function setTopology(): void
    {
        $this->topology = [];
        $centreonDb = CentreonDBInstance::getDbCentreonInstance();
        if ($this->admin) {
            $query = "SELECT topology_page "
                . "FROM topology "
                . "WHERE topology_page IS NOT NULL ";
            $DBRES = $centreonDb->query($query);
            while ($row = $DBRES->fetchRow()) {
                $this->topology[$row['topology_page']] = self::ACL_ACCESS_READ_WRITE;
            }
            $DBRES->closeCursor();
        } elseif (count($this->accessGroups) > 0) {
            // If user is in an access group
            $query = "SELECT DISTINCT acl_group_topology_relations.acl_topology_id "
                . "FROM acl_group_topology_relations, acl_topology, acl_topology_relations "
                . "WHERE acl_topology_relations.acl_topo_id = acl_topology.acl_topo_id "
                . "AND acl_topology.acl_topo_activate = '1' "
                . "AND acl_group_topology_relations.acl_group_id IN ("
                . $this->getAccessGroupsString() . ") ";

            $DBRESULT = $centreonDb->query($query);

            if ($DBRESULT->rowCount()) {
                $topology = [];
                $tmp_topo_page = [];
                $statement = $centreonDb
                    ->prepare("SELECT topology_topology_id, acl_topology_relations.access_right "
                        . "FROM acl_topology_relations, acl_topology "
                        . "WHERE acl_topology.acl_topo_activate = '1' "
                        . "AND acl_topology.acl_topo_id = acl_topology_relations.acl_topo_id "
                        . "AND acl_topology_relations.acl_topo_id = :acl_topology_id "
                        . "AND acl_topology_relations.access_right != 0");
                while ($topo_group = $DBRESULT->fetchRow()) {
                    $statement->bindValue(':acl_topology_id', (int) $topo_group["acl_topology_id"], PDO::PARAM_INT);
                    $statement->execute();
                    while ($topo_page = $statement->fetchRow()) {
                        $topology[] = (int) $topo_page["topology_topology_id"];
                        if (!isset($tmp_topo_page[$topo_page['topology_topology_id']])) {
                            $tmp_topo_page[$topo_page["topology_topology_id"]] = $topo_page["access_right"];
                        } elseif ($topo_page["access_right"] == self::ACL_ACCESS_READ_WRITE) {
                            $tmp_topo_page[$topo_page["topology_topology_id"]] = $topo_page["access_right"];
                        } elseif ($topo_page["access_right"] == self::ACL_ACCESS_READ_ONLY
                            && $tmp_topo_page[$topo_page["topology_topology_id"]] == self::ACL_ACCESS_NONE
                        ) {
                            $tmp_topo_page[$topo_page["topology_topology_id"]] =
                                self::ACL_ACCESS_READ_ONLY;
                        }
                    }
                    $statement->closeCursor();
                }
                $DBRESULT->closeCursor();

                if ($topology !== []) {
                    $query3 = "SELECT topology_page, topology_id "
                        . "FROM topology FORCE INDEX (`PRIMARY`) "
                        . "WHERE topology_page IS NOT NULL "
                        . "AND topology_id IN (" . implode(', ', $topology) . ") ";
                    $DBRESULT3 = $centreonDb->query($query3);
                    while ($topo_page = $DBRESULT3->fetchRow()) {
                        $this->topology[$topo_page["topology_page"]] =
                            $tmp_topo_page[$topo_page["topology_id"]];
                    }
                    $DBRESULT3->closeCursor();
                }
            }
        }
        $this->checkTopology();
    }

    /**
     * Use to check and fix if in the topology, a parent has access rights that
     * can be higher than children when they have the same endpoint.
     *
     * @return void
     */
    private function checkTopology(): void
    {
        if (!empty($this->topology)) {
            /**
             * Filter to keep the first child available per level.
             */
            $getFirstChildPerLvl = function (array $topologies): array {
                ksort($topologies, SORT_ASC);
                $parentsLvl = [];

                // Classify topologies by parents
                foreach (array_keys($topologies) as $page) {
                    if (strlen($page) == 1) {
                        // MENU level 1
                        if (!array_key_exists($page, $parentsLvl)) {
                            $parentsLvl[$page] = [];
                        }
                    } elseif (strlen($page) == 3) {
                        // MENU level 2
                        $parentLvl1 = substr($page, 0, 1);
                        if (!array_key_exists($parentLvl1, $parentsLvl)) {
                            $parentsLvl[$parentLvl1] = [];
                        }
                        if (!array_key_exists($page, $parentsLvl[$parentLvl1])) {
                            $parentsLvl[$parentLvl1][$page] = [];
                        }
                    } elseif (strlen($page) == 5) {
                        // MENU level 3
                        $parentLvl1 = substr($page, 0, 1);
                        $parentLvl2 = substr($page, 0, 3);
                        if (!array_key_exists($parentLvl1, $parentsLvl)) {
                            $parentsLvl[$parentLvl1] = [];
                        }
                        if (!array_key_exists($parentLvl2, $parentsLvl[$parentLvl1])) {
                            $parentsLvl[$parentLvl1][$parentLvl2] = [];
                        }
                        if (!in_array($page, $parentsLvl[$parentLvl1][$parentLvl2])) {
                            $parentsLvl[$parentLvl1][$parentLvl2][] = $page;
                        }
                    }
                }

                /*
                 * We keep the first lvl3 child by lvl1.
                 * In this way, we keep the first child available for each parent
                 */
                foreach ($parentsLvl as $parentLvl1 => $childrenLvl2) {
                    // First reading, we don't delete the first child
                    $canDeleteOtherChild = false;
                    foreach ($childrenLvl2 as $parentLvl2 => $childrenLvl3) {
                        if ($canDeleteOtherChild) {
                            // Not the first reading, we can delete this child
                            unset($parentsLvl[$parentLvl1][$parentLvl2]);
                            continue;
                        }
                        if ($childrenLvl3 === []) {
                            continue;
                        }
                        // First reading
                        ksort($childrenLvl3);
                        // We keep the tree of the first child

                        $parentsLvl[$parentLvl1][$parentLvl2] = array_slice($childrenLvl3, 0, 1, true)[0];
                        /*
                         * The first child has been processed so we set TRUE
                         * to delete all the following children
                         */
                        $canDeleteOtherChild = true;
                    }
                }
                return $parentsLvl;
            };

            $parentsLvl = $getFirstChildPerLvl($this->topology);

            // We fix topologies according to filter
            foreach ($parentsLvl as $parentLvl1 => $childrenLvl2) {
                foreach ($childrenLvl2 as $parentLvl2 => $childrenLvl3) {
                    if (
                        !empty($childrenLvl3)
                        && isset($this->topology[$childrenLvl3])
                        && isset($this->topology[$parentLvl2])
                        && $this->topology[$childrenLvl3] > $this->topology[$parentLvl2]
                    ) {
                        /*
                         * The parent has more privileges than his child.
                         * We define the access rights of parent with that of
                         * his child.
                         */
                        $this->topology[$parentLvl2] = $this->topology[$childrenLvl3];
                    }
                    if (
                        isset($this->topology[$parentLvl2])
                        && isset($this->topology[$parentLvl1])
                        && $this->topology[$parentLvl2] > $this->topology[$parentLvl1]
                    ) {
                        /*
                         * The parent has more privileges than his child.
                         * We define the access rights of parent with that of
                         * his child.
                         */
                        $this->topology[$parentLvl1] = $this->topology[$parentLvl2];
                    }
                }
            }
        }
    }

    /*
     * Getter functions
     */

    /**
     * Get ACL by string
     *
     * @return void
     */
    public function getACLStr(): void
    {
        $this->topologyStr = empty($this->topology)
            ? "''"
            : implode(',', array_keys($this->topology));
    }

    /**
     * Access groups Getter
     *
     * @return array
     */
    public function getAccessGroups()
    {
        $this->setAccessGroups();
        return ($this->accessGroups);
    }

    /**
     *  Access groups string Getter
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     * @param $escape
     *
     * @return string
     */
    public function getAccessGroupsString($flag = null, $escape = true)
    {
        if ($flag !== null) {
            $flag = strtoupper($flag);
        }

        // before returning access groups as string, make sure that those are up to date
        $this->setAccessGroups();

        $accessGroups = "";
        foreach ($this->accessGroups as $key => $value) {
            switch ($flag) {
                case "NAME":
                    if ($escape === true) {
                        $accessGroups .= "'" . CentreonDB::escape($value) . "',";
                    } else {
                        $accessGroups .= "'" . $value . "',";
                    }
                    break;
                case "ID":
                    $accessGroups .= $key . ",";
                    break;
                default:
                    $accessGroups .= "'" . $key . "',";
                    break;
            }
        }

        $result = "'0'";
        if (strlen($accessGroups)) {
            $result = trim($accessGroups, ',');
        }

        return $result;
    }

    /**
     * Resource groups Getter
     *
     * @return array
     */
    public function getResourceGroups()
    {
        return $this->resourceGroups;
    }

    /**
     * Resource groups string Getter
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     * @param $escape
     *
     * @return string
     */
    public function getResourceGroupsString($flag = null, $escape = true)
    {
        if ($flag !== null) {
            $flag = strtoupper($flag);
        }

        $resourceGroups = "";
        foreach ($this->resourceGroups as $key => $value) {
            switch ($flag) {
                case "NAME":
                    if ($escape === true) {
                        $resourceGroups .= "'" . CentreonDB::escape($value) . "',";
                    } else {
                        $resourceGroups .= "'" . $value . "',";
                    }
                    break;
                case "ID":
                    $resourceGroups .= $key . ",";
                    break;
                default:
                    $resourceGroups .= "'" . $key . "',";
                    break;
            }
        }

        $result = "''";
        if (strlen($resourceGroups)) {
            $result = trim($resourceGroups, ',');
        }

        return $result;
    }

    /**
     * Hostgroups Getter
     *
     * @param $flag
     *
     * @return array
     */
    public function getHostGroups($flag = null)
    {
        $this->checkUpdateACL();

        if ($flag !== null && strtoupper($flag) == "ALIAS") {
            return $this->hostGroupsAlias;
        }
        return $this->hostGroups;
    }

    /**
     * Poller Getter
     *
     * @return array
     */
    public function getPollers()
    {
        return $this->pollers;
    }

    /**
     * Hostgroups string Getter
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     *
     * @return string
     */
    public function getHostGroupsString($flag = null)
    {
        if ($flag !== null) {
            $flag = strtoupper($flag);
        }

        $hostgroups = "";
        foreach ($this->hostGroups as $key => $value) {
            switch ($flag) {
                case "NAME":
                    $hostgroups .= "'" . $value . "',";
                    break;
                case "ALIAS":
                    $hostgroups .= "'" . addslashes($this->hostGroupsAlias[$key]) . "',";
                    break;
                case "ID":
                    $hostgroups .= $key . ",";
                    break;
                default:
                    $hostgroups .= "'" . $key . "',";
                    break;
            }
        }

        $result = "''";
        if (strlen($hostgroups)) {
            $result = trim($hostgroups, ',');
        }

        return $result;
    }

    /**
     * Poller string Getter
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     * @param $escape
     *
     * @return string
     */
    public function getPollerString($flag = null, $escape = true)
    {
        if ($flag !== null) {
            $flag = strtoupper($flag);
        }

        $pollers = "";
        $flagFirst = true;
        foreach ($this->pollers as $key => $value) {
            switch ($flag) {
                case "NAME":
                    if (!$flagFirst) {
                        $pollers .= ",";
                    }
                    $flagFirst = false;
                    if ($escape === true) {
                        $pollers .= "'" . CentreonDB::escape($value) . "'";
                    } else {
                        $pollers .= "'" . $value . "'";
                    }
                    break;
                case "ID":
                    if (!$flagFirst) {
                        $pollers .= ",";
                    }
                    $flagFirst = false;
                    $pollers .= $key;
                    break;
                default:
                    if (!$flagFirst) {
                        $pollers .= ",";
                    }
                    $flagFirst = false;
                    $pollers .= "'" . $key . "'";
                    break;
            }
        }
        return $pollers;
    }

    /**
     * Service groups Getter
     *
     * @return array
     */
    public function getServiceGroups()
    {
        return $this->serviceGroups;
    }

    /**
     * Service groups string Getter
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     * @param $escape
     *
     * @return string
     */
    public function getServiceGroupsString($flag = null, $escape = true)
    {
        if ($flag !== null) {
            $flag = strtoupper($flag);
        }

        $servicegroups = "";
        foreach ($this->serviceGroups as $key => $value) {
            switch ($flag) {
                case "NAME":
                    if ($escape === true) {
                        $servicegroups .= "'" . CentreonDB::escape($value) . "',";
                    } else {
                        $servicegroups .= "'" . $value . "',";
                    }
                    break;
                case "ALIAS":
                    $servicegroups .= "'" . $this->serviceGroupsAlias[$key] . "',";
                    break;
                case "ID":
                    $servicegroups .= $key . ",";
                    break;
                default:
                    $servicegroups .= "'" . $key . "',";
                    break;
            }
        }

        $result = "''";
        if (strlen($servicegroups)) {
            $result = trim($servicegroups, ',');
        }

        return $result;
    }

    /**
     * Service categories Getter
     *
     * @return array
     */
    public function getServiceCategories()
    {
        return $this->serviceCategories;
    }

    /**
     * Get HostCategories
     *
     * @return array
     */
    public function getHostCategories()
    {
        return $this->hostCategories;
    }

    /**
     * Service categories string Getter
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     * @param $escape
     *
     * @return string
     */
    public function getServiceCategoriesString($flag = null, $escape = true)
    {
        if ($flag !== null) {
            $flag = strtoupper($flag);
        }

        $serviceCategories = "";
        foreach ($this->serviceCategories as $key => $value) {
            switch ($flag) {
                case "NAME":
                    if ($escape === true) {
                        $serviceCategories .= "'" . CentreonDB::escape($value) . "',";
                    } else {
                        $serviceCategories .= "'" . $value . "',";
                    }
                    break;
                case "ID":
                    $serviceCategories .= $key . ",";
                    break;
                default:
                    $serviceCategories .= "'" . $key . "',";
                    break;
            }
        }

        $result = "''";
        if (strlen($serviceCategories)) {
            $result = trim($serviceCategories, ',');
        }

        return $result;
    }

    /**
     * Get HostCategories String
     *
     * @param mixed $flag
     * @param mixed $escape
     * @return string
     */
    public function getHostCategoriesString($flag = null, $escape = true)
    {
        if ($flag !== null) {
            $flag = strtoupper($flag);
        }

        $hostCategories = "";
        foreach ($this->hostCategories as $key => $value) {
            switch ($flag) {
                case "NAME":
                    if ($escape === true) {
                        $hostCategories .= "'" . CentreonDB::escape($value) . "',";
                    } else {
                        $hostCategories .= "'" . $value . "',";
                    }
                    break;
                case "ID":
                    $hostCategories .= $key . ",";
                    break;
                default:
                    $hostCategories .= "'" . $key . "',";
                    break;
            }
        }

        $result = "''";
        if (strlen($hostCategories)) {
            $result = trim($hostCategories, ',');
        }

        return $result;
    }


    /**
     * @param $hostId
     *
     * @return bool
     */
    public function checkHost($hostId)
    {
        $pearDBO = CentreonDBInstance::getDbCentreonStorageInstance();
        $hostArray = $this->getHostsArray("ID", $pearDBO);
        if (in_array($hostId, $hostArray)) {
            return true;
        }
        return false;
    }

    /**
     * @param $serviceId
     *
     * @return bool
     */
    public function checkService($serviceId)
    {
        $pearDBO = CentreonDBInstance::getDbCentreonStorageInstance();
        $serviceArray = $this->getServicesArray("ID", $pearDBO);
        if (in_array($serviceId, $serviceArray)) {
            return true;
        }
        return false;
    }


    /**
     * Hosts array Getter / same as getHostsString function
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     * @param $pearDBndo
     * @param $escape
     *
     * @return array|string
     */
    public function getHostsArray($flag = null, $pearDBndo = null, $escape = true)
    {
        $this->checkUpdateACL();

        $groupIds = array_keys($this->accessGroups);
        if ($groupIds === []) {
            return "''";
        }

        if ($flag !== null) {
            $flag = strtoupper($flag);
        }
        switch ($flag) {
            case "NAME":
                $query = "SELECT DISTINCT h.host_id, h.name "
                    . "FROM centreon_acl ca, hosts h "
                    . "WHERE ca.host_id = h.host_id "
                    . "AND group_id IN (" . implode(',', $groupIds) . ") "
                    . "GROUP BY h.name, h.host_id "
                    . "ORDER BY h.name ASC ";
                $fieldName = 'name';
                break;
            default:
                $query = "SELECT DISTINCT host_id "
                    . "FROM centreon_acl "
                    . "WHERE group_id IN (" . implode(',', $groupIds) . ") ";
                $fieldName = 'host_id';
                break;
        }

        $hosts = [];
        $DBRES = CentreonDBInstance::getDbCentreonStorageInstance()->query($query);
        while ($row = $DBRES->fetchRow()) {
            $hosts[] = $escape === true ? CentreonDB::escape($row[$fieldName]) : $row[$fieldName];
        }

        return $hosts;
    }

    /**
     * @param int $length
     *
     * @return string
     */
    private static function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @param $tmpName
     * @param $db
     * @param $rows
     * @param $fields
     *
     * @return void
     */
    private function fillTemporaryTable($tmpName, $db, $rows, $fields): void
    {
        $queryInsert = "INSERT INTO " . $tmpName . ' (';
        $queryValues = "";
        foreach ($fields as $field) {
            $queryInsert .= $field['key'] . ',';
            $queryValues .= '?,';
        }
        $queryInsert = trim($queryInsert, ',');
        $queryValues = trim($queryValues, ',');
        $queryInsert .= ') VALUES (' . $queryValues . ');';

        $db->autoCommit(false);
        $stmt = $db->prepare($queryInsert);
        $arrayValues = [];
        foreach ($rows as $row) {
            $arrayValue = [];
            foreach ($fields as $field) {
                $arrayValue[] = $row[$field['key']];
            }
            $arrayValues[] = $arrayValue;
        }
        $db->executeMultiple($stmt, $arrayValues);
        $db->commit();
        $db->autoCommit(true);
    }

    /**
     * @param $db
     * @param $rows
     * @param $originTable
     *
     * @return array
     */
    private function getRowFields($db, $rows, $originTable = 'centreon_acl')
    {
        if (empty($rows)) {
            return [];
        }

        $row = $rows[0];
        $fieldsArray = [];

        foreach ($row as $fieldKey => $field) {
            $fieldDef = $this->getField($originTable, $fieldKey, $db);
            $options = ($fieldDef['Null'] == 'NO' ? ' Not Null ' : ' Null ')
                . ($fieldDef['Key'] == 'PRI' ? ' PRIMARY KEY ' : ' ');
            $fieldsArray[] = ['key' => $fieldKey, 'type' => $fieldDef['Type'], 'options' => $options];
        }
        return $fieldsArray;
    }


    /**
     * @param $name
     * @param $db
     * @param $rows
     * @param $originTable
     * @param $fields
     *
     * @return string
     */
    private function createTemporaryTable($name, $db, $rows, $originTable = 'centreon_acl', $fields = [])
    {
        $tempTableName = 'tmp_' . $name . '_' . self::generateRandomString(5);
        if (empty($fields)) {
            $fields = $this->getRowFields($db, $rows, $originTable);
        }
        $query = "CREATE TEMPORARY TABLE IF NOT EXISTS  " . $tempTableName . " (";
        foreach ($fields as $field) {
            $query .= $field['key'] . ' ' . $field['type'] . ' ' . $field['options'] . ',';
        }
        $query = trim($query, ',') . ');';
        $db->query($query);
        $this->tempTableArray[$name] = $tempTableName;
        $this->fillTemporaryTable($tempTableName, $db, $rows, $fields);
        return $tempTableName;
    }

    /**
     * @param $table
     * @param $field
     * @param $db
     *
     * @return mixed
     */
    private function getField($table, $field, $db)
    {
        $query = "SHOW COLUMNS FROM `$table` WHERE Field = '$field'";
        $DBRES = $db->query($query);
        $row = $DBRES->fetchRow();
        return $row;
    }

    /**
     * @param $tmpTableName
     * @param $db
     * @param $rows
     * @param $originTable
     * @param $force
     * @param $fields
     *
     * @return mixed
     */
    public function getACLTemporaryTable(
        $tmpTableName,
        $db,
        $rows,
        $originTable = 'centreon_acl',
        $force = false,
        $fields = []
    ) {
        if (!empty($this->tempTableArray[$tmpTableName]) && !$force) {
            return $this->tempTableArray[$tmpTableName];
        }
        if ($force) {
            $this->destroyTemporaryTable($tmpTableName);
        }
        $this->createTemporaryTable($tmpTableName, $db, $rows, $originTable, $fields);
        return $this->tempTableArray[$tmpTableName];
    }

    /**
     * @param $db
     * @param $name
     *
     * @return void
     */
    public function destroyTemporaryTable($db, $name = false): void
    {
        if (!$name) {
            foreach ($this->tempTableArray as $tmpTable) {
                $query = 'DROP TEMPORARY TABLE IF EXISTS ' . $tmpTable;
                $db->query($query);
            }
        } else {
            $query = 'DROP TEMPORARY TABLE IF EXISTS ' . $this->tempTableArray[$name];
            $db->query($query);
        }
    }

    /**
     * @param $db
     * @param $fieldToJoin
     * @param $force
     *
     * @return string
     */
    public function getACLHostsTemporaryTableJoin($db, $fieldToJoin, $force = false)
    {
        $this->checkUpdateACL();
        $groupIds = array_keys($this->accessGroups);
        if ($groupIds === []) {
            return "''";
        }
        $query = "SELECT DISTINCT host_id "
            . "FROM centreon_acl "
            . "WHERE group_id IN (" . implode(',', $groupIds) . ") ";
        $DBRES = $db->query($query);
        $rows = [];
        while ($row = $DBRES->fetchRow()) {
            $rows[] = $row;
        }
        $tableName = $this->getACLTemporaryTable('hosts', $db, $rows, 'centreon_acl', $force);
        $join = ' INNER JOIN ' . $tableName . ' ON ' . $tableName . '.host_id = ' . $fieldToJoin . ' ';
        return $join;
    }

    /**
     * @param $db
     * @param $fieldToJoin
     * @param $force
     *
     * @return false|string
     */
    public function getACLServicesTemporaryTableJoin($db, $fieldToJoin, $force = false)
    {
        $this->checkUpdateACL();
        $groupIds = array_keys($this->accessGroups);
        if ($groupIds === []) {
            return false;
        }
        $query = "SELECT DISTINCT service_id "
            . "FROM centreon_acl "
            . "WHERE group_id IN (" . implode(',', $groupIds) . ") ";
        $DBRES = $db->query($query);
        $rows = [];
        while ($row = $DBRES->fetchRow()) {
            $rows[] = $row;
        }
        $tableName = $this->getACLTemporaryTable('services', $db, $rows, 'centreon_acl', $force);
        $join = ' INNER JOIN ' . $tableName . ' ON ' . $tableName . '.service_id = ' . $fieldToJoin . ' ';
        return $join;
    }

    /**
     * @param $db
     * @param $fieldToJoin
     * @param $force
     *
     * @return string
     */
    public function getACLHostsTableJoin($db, $fieldToJoin, $force = false)
    {
        $this->checkUpdateACL();
        $groupIds = array_keys($this->accessGroups);
        if ($groupIds === []) {
            return "";
        }
        $tempTableName = 'centreon_acl_' . self::generateRandomString(5);
        $join = ' INNER JOIN centreon_acl ' . $tempTableName . ' ON ' . $tempTableName . '.host_id = ' . $fieldToJoin
            . ' AND ' . $tempTableName . '.group_id IN (' . implode(",", $groupIds) . ') ';
        return $join;
    }

    /**
     * @param $db
     * @param $fieldToJoin
     * @param $force
     *
     * @return string
     */
    public function getACLServicesTableJoin($db, $fieldToJoin, $force = false)
    {
        $this->checkUpdateACL();
        $groupIds = array_keys($this->accessGroups);
        if ($groupIds === []) {
            return "";
        }
        $tempTableName = 'centreon_acl_' . self::generateRandomString(5);
        $join = ' INNER JOIN centreon_acl ' . $tempTableName . ' ON ' . $tempTableName . '.service_id = ' . $fieldToJoin
            . ' AND ' . $tempTableName . '.group_id IN (' . implode(",", $groupIds) . ') ';
        return $join;
    }


    /**
     * Hosts string Getter
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     * @param $pearDBndo
     * @param $escape
     *
     * @return string
     */
    public function getHostsString($flag = null, $pearDBndo = null, $escape = true)
    {
        $this->checkUpdateACL();

        $groupIds = array_keys($this->accessGroups);
        if ($groupIds === []) {
            return "''";
        }

        if ($flag !== null) {
            $flag = strtoupper($flag);
        }
        switch ($flag) {
            case "NAME":
                $query = "SELECT DISTINCT h.host_id, h.name "
                    . "FROM centreon_acl ca, hosts h "
                    . "WHERE ca.host_id = h.host_id "
                    . "AND group_id IN (" . implode(',', $groupIds) . ") "
                    . "GROUP BY h.name, h.host_id "
                    . "ORDER BY h.name ASC ";
                $fieldName = 'name';
                break;
            default:
                $query = "SELECT DISTINCT host_id "
                    . "FROM centreon_acl "
                    . "WHERE group_id IN (" . implode(',', $groupIds) . ") ";
                $fieldName = 'host_id';
                break;
        }

        $hosts = "";
        $DBRES = $pearDBndo->query($query);
        while ($row = $DBRES->fetchRow()) {
            if ($escape === true) {
                $hosts .= "'" . CentreonDB::escape($row[$fieldName]) . "',";
            } elseif ($flag == "ID") {
                $hosts .= $row[$fieldName] . ",";
            } else {
                $hosts .= "'" . $row[$fieldName] . "',";
            }
        }

        $result = "''";
        if (strlen($hosts)) {
            $result = trim($hosts, ',');
        }

        return $result;
    }


    /**
     * Services array Getter
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     * @param $pearDBndo
     * @param $escape
     *
     * @return array|string
     */
    public function getServicesArray($flag = null, $pearDBndo = null, $escape = true)
    {
        $this->checkUpdateACL();

        $groupIds = array_keys($this->accessGroups);
        if ($groupIds === []) {
            return "''";
        }

        if ($flag !== null) {
            $flag = strtoupper($flag);
        }
        switch ($flag) {
            case "NAME":
                $query = "SELECT DISTINCT s.service_id, s.description "
                    . "FROM centreon_acl ca, services s "
                    . "WHERE ca.service_id = s.service_id "
                    . "AND group_id IN (" . implode(',', $groupIds) . ") ";
                $fieldName = 'description';
                break;
            default:
                $query = "SELECT DISTINCT service_id "
                    . "FROM centreon_acl "
                    . "WHERE group_id IN (" . implode(',', $groupIds) . ") ";
                $fieldName = 'service_id';
                break;
        }

        $services = [];

        $DBRES = $pearDBndo->query($query);
        $items = [];
        while ($row = $DBRES->fetchRow()) {
            if (isset($items[$row[$fieldName]])) {
                continue;
            }
            $items[$row[$fieldName]] = true;
            $services[] = $escape === true ? CentreonDB::escape($row[$fieldName]) : $row[$fieldName];
        }

        return $services;
    }


    /**
     * Services string Getter
     *
     *  Possible flags :
     *  - ID => will return the id's of the element
     *  - NAME => will return the names of the element
     *
     * @param $flag
     * @param $pearDBndo
     * @param $escape
     *
     * @return string
     */
    public function getServicesString($flag = null, $pearDBndo = null, $escape = true)
    {
        $this->checkUpdateACL();

        $groupIds = array_keys($this->accessGroups);
        if ($groupIds === []) {
            return "''";
        }

        if ($flag !== null) {
            $flag = strtoupper($flag);
        }
        switch ($flag) {
            case "NAME":
                $query = "SELECT DISTINCT s.service_id, s.description "
                    . "FROM centreon_acl ca, services s "
                    . "WHERE ca.service_id = s.service_id "
                    . "AND group_id IN (" . implode(',', $groupIds) . ") ";
                $fieldName = 'description';
                break;
            default:
                $query = "SELECT DISTINCT service_id "
                    . "FROM centreon_acl "
                    . "WHERE group_id IN (" . implode(',', $groupIds) . ") ";
                $fieldName = 'service_id';
                break;
        }

        $services = "";

        $DBRES = $pearDBndo->query($query);
        $items = [];
        while ($row = $DBRES->fetchRow()) {
            if (isset($items[$row[$fieldName]])) {
                continue;
            }
            $items[$row[$fieldName]] = true;
            if ($escape === true) {
                $services .= "'" . CentreonDB::escape($row[$fieldName]) . "',";
            } elseif ($flag == "ID") {
                $services .= $row[$fieldName] . ",";
            } else {
                $services .= "'" . $row[$fieldName] . "',";
            }
        }

        $result = "''";
        if (strlen($services)) {
            $result = trim($services, ',');
        }

        return $result;
    }

    /**
     * Get authorized host service ids
     *
     * @param $db CentreonDB
     *
     * @return string return id combinations like '14_26' (hostId_serviceId)
     */
    public function getHostServiceIds($db)
    {
        $this->checkUpdateACL();

        $groupIds = array_keys($this->accessGroups);
        if ($groupIds === []) {
            return "''";
        }

        $hostsServices = "";

        $query = "SELECT DISTINCT host_id, service_id "
            . "FROM centreon_acl "
            . "WHERE group_id IN (" . implode(',', $groupIds) . ") ";
        $res = $db->query($query);
        while ($row = $res->fetchRow()) {
            $hostsServices .= "'" . $row['host_id'] . "_" . $row['service_id'] . "',";
        }

        $result = "''";
        if (strlen($hostsServices)) {
            $result = trim($hostsServices, ',');
        }

        return $result;
    }

    /*
     * Actions Getter
     */

    /**
     * @return array
     */
    public function getActions()
    {
        $this->checkUpdateACL();
        return $this->actions;
    }

    /**
     * @return array
     */
    public function getTopology()
    {
        $this->checkUpdateACL();
        return $this->topology;
    }

    /**
     * Update topologystr value
     */
    public function updateTopologyStr(): void
    {
        $this->setTopology();
        $this->topologyStr = $this->getTopologyString();
    }

    /**
     * @return string
     */
    public function getTopologyString()
    {
        $this->checkUpdateACL();

        $topology = array_keys($this->topology);

        $result = "''";
        if ($topology !== []) {
            $result = implode(', ', $topology);
        }

        return $result;
    }

    /**
     *  This functions returns a string that forms a condition of a query
     *  i.e : " WHERE host_id IN ('1', '2', '3') "
     *  or : " AND host_id IN ('1', '2', '3') "
     *
     * @param $condition
     * @param $field
     * @param $stringlist
     *
     * @return string
     */
    public function queryBuilder($condition, $field, $stringlist)
    {
        $str = "";
        if ($this->admin) {
            return $str;
        }
        if ($stringlist == "") {
            $stringlist = "''";
        }
        $str .= " " . $condition . " " . $field . " IN (" . $stringlist . ") ";
        return $str;
    }

    /**
     * Function that returns
     *
     * @param mixed $p
     * @param bool $checkAction
     *
     * @return int | 1 : if user is allowed to access the page
     *               0 : if user is NOT allowed to access the page
     */
    public function page(mixed $p, bool $checkAction = false): int
    {
        $this->checkUpdateACL();
        if ($this->admin) {
            return self::ACL_ACCESS_READ_WRITE;
        } elseif (isset($this->topology[$p])) {
            if (
                $checkAction
                && $this->topology[$p] == self::ACL_ACCESS_READ_ONLY
                && isset($_REQUEST['o']) && $_REQUEST['o'] == 'a'
            ) {
                return self::ACL_ACCESS_NONE;
            }
            return (int) $this->topology[$p];
        }
        return self::ACL_ACCESS_NONE;
    }

    /**
     * Function that checks if the user can execute the action
     *
     *  1 : user can execute it
     *  0 : user CANNOT execute it
     *
     * @param $action
     *
     * @return int
     */
    public function checkAction($action)
    {
        $this->checkUpdateACL();
        if ($this->admin || isset($this->actions[$action])) {
            return 1;
        }
        return 0;
    }

    /**
     * Function that returns the pair host/service by ID if $host_id is NULL
     *  Otherwise, it returns all the services of a specific host
     *
     * @param CentreonDB $pearDBMonitoring access to centreon_storage database
     * @param Bool $withServiceDescription to retrieve description of services
     *
     * @return array
     */
    public function getHostsServices($pearDBMonitoring, $withServiceDescription = false)
    {
        $tab = [];
        if ($this->admin) {
            $req = $withServiceDescription ? ", s.service_description " : "";
            $query = "SELECT h.host_id, s.service_id " . $req
                . "FROM host h "
                . "LEFT JOIN host_service_relation hsr on hsr.host_host_id = h.host_id "
                . "LEFT JOIN service s on hsr.service_service_id = s.service_id "
                . "WHERE h.host_register = '1' ";
            $result = CentreonDBInstance::getDbCentreonInstance()->query($query);
            while ($row = $result->fetchRow()) {
                $tab[$row['host_id']][$row['service_id']] = $withServiceDescription ? $row['service_description'] : 1;
            }
            $result->closeCursor();
            // Used By EventLogs page Only
            if ($withServiceDescription) {
                // Get Services attached to hostgroups
                $query = "SELECT hgr.host_host_id, s.service_id, s.service_description "
                    . "FROM hostgroup_relation hgr, service s, host_service_relation hsr "
                    . "WHERE hsr.hostgroup_hg_id = hgr.hostgroup_hg_id "
                    . "AND s.service_id = hsr.service_service_id ";
                $result = CentreonDBInstance::getDbCentreonInstance()->query($query);
                while ($elem = $result->fetchRow()) {
                    $tab[$elem['host_host_id']][$elem["service_id"]] = $elem["service_description"];
                }
                $result->closeCursor();
            }
        } else {
            if ($withServiceDescription) {
                $query = "SELECT acl.host_id, acl.service_id, s.description "
                    . "FROM centreon_acl acl "
                    . "LEFT JOIN services s on acl.service_id = s.service_id "
                    . "WHERE group_id IN (" . $this->getAccessGroupsString() . ") "
                    . "GROUP BY acl.host_id, acl.service_id ";
            } else {
                $query = "SELECT host_id, service_id "
                    . "FROM centreon_acl "
                    . "WHERE group_id IN (" . $this->getAccessGroupsString() . ") "
                    . "GROUP BY host_id, service_id ";
            }

            $result = $pearDBMonitoring->query($query);
            while ($row = $result->fetch()) {
                $tab[$row['host_id']][$row['service_id']] = $withServiceDescription ? $row['description'] : 1;
            }
            $result->closeCursor();
        }

        return $tab;
    }

    /**
     * @param $pearDBMonitoring
     * @param $host_id
     *
     * @return array
     */
    public function getHostServices($pearDBMonitoring, $host_id)
    {
        $tab = [];
        if ($this->admin) {
            $query = "SELECT DISTINCT h.host_id, s.service_id, s.service_description "
                . "FROM host_service_relation hsr, host h, service s "
                . "WHERE h.host_activate = '1' "
                . "AND hsr.host_host_id = h.host_id "
                . "AND h.host_id = '" . CentreonDB::escape($host_id) . "'"
                . "AND hsr.service_service_id = s.service_id "
                . "AND s.service_activate = '1' ";
            $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query($query);
            while ($row = $DBRESULT->fetchRow()) {
                $tab[$row['service_id']] = $row['service_description'];
            }
            $DBRESULT->closeCursor();

            # Get Services attached to hostgroups
            $query = "SELECT DISTINCT service_id, service_description "
                . "FROM hostgroup_relation hgr, service, host_service_relation hsr "
                . "WHERE hgr.host_host_id = '" . CentreonDB::escape($host_id) . "' "
                . "AND hsr.hostgroup_hg_id = hgr.hostgroup_hg_id "
                . "AND service_id = hsr.service_service_id ";
            $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query($query);
            while ($elem = $DBRESULT->fetchRow()) {
                $tab[$elem["service_id"]] = html_entity_decode($elem["service_description"], ENT_QUOTES, "UTF-8");
            }
            $DBRESULT->closeCursor();
        } else {
            $query = "SELECT DISTINCT s.service_id, s.description "
                . "FROM services s "
                . "JOIN centreon_acl ca "
                . "ON s.service_id = ca.service_id "
                . "AND ca.host_id = '" . CentreonDB::escape($host_id) . "' "
                . "AND ca.group_id IN (" . $this->getAccessGroupsString() . ") ";
            $DBRESULT = $pearDBMonitoring->query($query);
            while ($row = $DBRESULT->fetchRow()) {
                $tab[$row['service_id']] = $row['description'];
            }
            $DBRESULT->closeCursor();
        }

        return $tab;
    }

    /**
     * Function that returns the pair host/service by NAME if $host_name is NULL
     *  Otherwise, it returns all the services of a specific host
     *
     * @param $pearDBndo
     *
     * @return array
     */
    public function getHostsServicesName($pearDBndo)
    {
        $joinAcl = "";
        if (!$this->admin) {
            $joinAcl = "JOIN centreon_acl ca "
                . "ON h.host_id = ca.host_id "
                . "AND ca.group_id IN (" . $this->getAccessGroupsString() . ") ";
        }

        $tab = [];
        $query = "SELECT DISTINCT h.name, s.description "
            . "FROM hosts h "
            . "LEFT JOIN services s "
            . "ON h.host_id = s.host_id "
            . $joinAcl
            . "ORDER BY h.name, s.description ";
        $DBRESULT = $pearDBndo->query($query);
        while ($row = $DBRESULT->fetchRow()) {
            $tab[$row['name']][$row['description']] = 1;
        }
        $DBRESULT->closeCursor();

        return $tab;
    }

    /**
     * Function that returns the pair host/service by NAME if $host_name is NULL
     *  Otherwise, it returns all the services of a specific host
     *
     * @param $pearDBndo
     * @param $host_name
     *
     * @return array
     */
    public function getHostServicesName($pearDBndo, $host_name)
    {
        $joinAcl = "";
        if (!$this->admin) {
            $joinAcl = "JOIN centreon_acl ca "
                . "ON h.host_id = ca.host_id "
                . "AND ca.group_id IN (" . $this->getAccessGroupsString() . ") ";
        }

        $tab = [];
        $query = "SELECT DISTINCT s.service_id, s.description, h.name "
            . "FROM hosts h "
            . "LEFT JOIN services s "
            . "ON h.host_id = s.host_id "
            . $joinAcl
            . "WHERE h.name = :hostName "
            . "AND s.service_id IS NOT NULL "
            . "ORDER BY h.name, s.description ";
        $statement = $pearDBndo->prepare($query);
        $statement->bindValue(':hostName', $host_name, PDO::PARAM_STR);
        $statement->execute();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $tab[$row['service_id']] = $row['description'];
        }
        $statement->closeCursor();
        return $tab;
    }

    /**
     * Function  that returns the hosts of a specific hostgroup
     *
     * @param $hg_id
     * @param $pearDBndo
     *
     * @return array
     */
    public function getHostgroupHosts($hg_id, $pearDBndo)
    {
        $tab = [];
        $query = "SELECT DISTINCT h.host_id, h.host_name "
            . "FROM hostgroup_relation hgr, host h "
            . "WHERE hgr.hostgroup_hg_id = '" . CentreonDB::escape($hg_id) . "' "
            . "AND hgr.host_host_id = h.host_id "
            . $this->queryBuilder("AND", "h.host_id", $this->getHostsString("ID", $pearDBndo))
            . " ORDER BY h.host_name ";

        $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query($query);
        while ($row = $DBRESULT->fetchRow()) {
            $tab[$row['host_id']] = $row['host_name'];
        }
        return ($tab);
    }

    /**
     * Function that sets the changed flag to 1 for the cron centAcl.php
     *
     * @param $data
     *
     * @return void
     */
    public function updateACL($data = null): void
    {
        if (!$this->admin) {
            $groupIds = array_keys($this->accessGroups);
            if (is_array($groupIds) && count($groupIds)) {
                $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query(
                    "UPDATE acl_groups SET acl_group_changed = '1' " .
                    "WHERE acl_group_id IN (" . implode(",", $groupIds) . ")"
                );

                // Manage changes
                if (isset($data['type']) && $data["type"] == 'HOST'
                    && ($data['action'] == 'ADD' || $data['action'] == 'DUP')
                ) {
                    $host_name = getMyHostName($data["id"]);

                    if ($data['action'] == 'ADD') {
                        // Put new entries in the table with group_id
                        foreach ($groupIds as $group_id) {
                            $request2 = "INSERT INTO centreon_acl (host_id, service_id, group_id) "
                                . "VALUES ('" . $data["id"] . "', NULL, " . $group_id . ")";
                            CentreonDBInstance::getDbCentreonStorageInstance()->query($request2);
                        }

                        // Insert services
                        $svc = getMyHostServices($data['id']);
                        foreach ($svc as $svc_id => $svc_name) {
                            $request2 = "INSERT INTO centreon_acl (host_id, service_id, group_id) "
                                . "VALUES ('" . $data["id"] . "', '" . $svc_id . "', " . $group_id . ") "
                                . "ON DUPLICATE KEY UPDATE group_id = " . $group_id;
                            CentreonDBInstance::getDbCentreonStorageInstance()->query($request2);
                        }
                    } elseif ($data['action'] == 'DUP' && isset($data['duplicate_host'])) {
                        // Get current ACL configuration from centreon_storage.centreon_acl table
                        $request = <<<SQL
                            SELECT
                                group_id
                            FROM centreon_acl
                            WHERE host_id = :duplicate_host_id 
                                AND service_id IS NULL
                        SQL;

                        $aclStatement = CentreonDBInstance::getDbCentreonStorageInstance()->prepare($request);
                        $aclStatement->bindValue(':duplicate_host_id', $data['duplicate_host'], PDO::PARAM_INT);
                        $aclStatement->execute();

                        $hostInsertACLQuery = <<<'SQL'
                            INSERT INTO centreon_acl (host_id, service_id, group_id)
                            VALUES (:data_id, NULL, :group_id)
                        SQL;

                        $hostACLStatement = CentreonDBInstance::getDbCentreonStorageInstance()->prepare($hostInsertACLQuery);

                        $serviceACLInsertQuery = <<<'SQL'
                            INSERT INTO centreon_acl (host_id, service_id, group_id)
                            VALUES (:data_id, :service_id, :group_id)
                            ON DUPLICATE KEY UPDATE group_id = :group_id
                        SQL;

                        $serviceACLStatement = CentreonDBInstance::getDbCentreonStorageInstance()->prepare($serviceACLInsertQuery);

                        while ($record = $aclStatement->fetchRow()) {
                            // Insert New Host
                            $hostACLStatement->bindValue(':data_id', (int) $data['id'], PDO::PARAM_INT);
                            $hostACLStatement->bindValue(':group_id', (int) $record['group_id'], PDO::PARAM_INT);
                            $hostACLStatement->execute();

                            // Find service IDs linked to the new host (result of the duplication)
                            $request = <<<SQL
                                SELECT
                                    service_service_id
                                FROM
                                    host_service_relation
                                WHERE
                                    host_host_id = :host_host_id 
                            SQL;

                            $servicesStatement = CentreonDBInstance::getDbCentreonInstance()->prepare($request);
                            $servicesStatement->bindValue(':host_host_id', $data['id'], PDO::PARAM_INT);
                            $servicesStatement->execute();

                            while ($serviceIds = $servicesStatement->fetch(PDO::FETCH_ASSOC)) {
                                $serviceACLStatement->bindValue(':data_id', (int) $data['id'], PDO::PARAM_INT);
                                $serviceACLStatement->bindValue(
                                    ':service_id',
                                    (int) $serviceIds['service_service_id'],
                                    PDO::PARAM_INT
                                );
                                $serviceACLStatement->bindValue(
                                    ':group_id',
                                    (int) $record['group_id'],
                                    PDO::PARAM_INT
                                );
                                $serviceACLStatement->execute();
                            }
                        }
                    }
                } elseif (isset($data['type']) && $data["type"] == 'SERVICE'
                    && ($data['action'] == 'ADD' || $data['action'] == 'DUP')
                ) {
                    $hosts = getMyServiceHosts($data["id"]);
                    $svc_name = getMyServiceName($data["id"]);
                    foreach ($hosts as $host_id) {
                        $host_name = getMyHostName($host_id);

                        if ($data['action'] == 'ADD') {
                            // Put new entries in the table with group_id
                            foreach ($groupIds as $group_id) {
                                $request2 = "INSERT INTO centreon_acl (host_id, service_id, group_id) "
                                    . "VALUES ('" . $host_id . "', '" . $data["id"] . "', " . $group_id . ")";
                                CentreonDBInstance::getDbCentreonStorageInstance()->query($request2);
                            }
                        } elseif ($data['action'] == 'DUP' && isset($data['duplicate_service'])) {
                            // Get current configuration into Centreon_acl table
                            $request = "SELECT group_id FROM centreon_acl "
                                . "WHERE host_id = $host_id AND service_id = " . $data['duplicate_service'];
                            $DBRESULT = CentreonDBInstance::getDbCentreonStorageInstance()->query($request);
                            $statement = CentreonDBInstance::getDbCentreonStorageInstance()
                                ->prepare("INSERT INTO centreon_acl (host_id, service_id, group_id) "
                                    . "VALUES (:host_id, :data_id, :group_id)");
                            while ($record = $DBRESULT->fetchRow()) {
                                $statement->bindValue(':host_id', (int) $host_id, PDO::PARAM_INT);
                                $statement->bindValue(':data_id', (int) $data["id"], PDO::PARAM_INT);
                                $statement->bindValue(':group_id', (int) $record['group_id'], PDO::PARAM_INT);
                                $statement->execute();
                            }
                        }
                    }
                }
            }
        } else {
            CentreonDBInstance::getDbCentreonInstance()->query("UPDATE `acl_resources` SET `changed` = '1'");
        }
    }

    /**
     * Funtion that return only metaservice table
     *
     * @return array
     */
    public function getMetaServices()
    {
        return $this->metaServices;
    }

    /**
     * Function that return Metaservice list ('', '', '')
     *
     * @return string
     */
    public function getMetaServiceString()
    {
        return $this->metaServiceStr;
    }

    /**
     * Load the list of parent template
     *
     * @return void
     */
    private function loadParentTemplates(): void
    {
        /* Get parents template */
        $this->parentTemplates = [];
        $currentContact = $this->userID;
        while ($currentContact != 0) {
            $this->parentTemplates[] = $currentContact;
            $query = 'SELECT contact_template_id
                FROM contact
                WHERE contact_id = ' . $currentContact;
            try {
                $res = CentreonDBInstance::getDbCentreonInstance()->query($query);
                $currentContact = ($row = $res->fetchRow()) ? $row['contact_template_id'] : 0;
            } catch (PDOException $e) {
                $currentContact = 0;
            }
        }
    }

    /**
     * Get DB Name
     *
     * @param string $broker
     * @return string
     */
    public function getNameDBAcl($broker = null)
    {
        global $conf_centreon;

        return $conf_centreon["dbcstg"];
    }

    /**
     * build request
     *
     * @param array $options (fields, conditions, order, pages, total)
     * @param bool $hasWhereClause | whether the request already has a where clause
     *
     * @return array
     */
    private function constructRequest($options, $hasWhereClause = false)
    {
        $requests = [];

        // Manage select clause
        $requests['select'] = 'SELECT ';
        if (isset($options['total']) && $options['total'] == true) {
            $requests['select'] .= 'SQL_CALC_FOUND_ROWS DISTINCT ';
        } elseif (isset($options['distinct']) && $options['distinct'] == true) {
            $requests['select'] .= 'DISTINCT ';
        }

        // Manage fields
        if (isset($options['fields']) && is_array($options['fields'])) {
            $requests['fields'] = implode(', ', $options['fields']);
            $tmpFields = preg_replace('/\w+\.(\w+)/', '$1', $options['fields']);
            $requests['simpleFields'] = implode(', ', $tmpFields);
        } elseif (isset($options['fields'])) {
            $requests['fields'] = $options['fields'];
            $requests['simpleFields'] = preg_replace('/\w+\.(\w+)/', '$1', $options['fields']);
        } else {
            $requests['fields'] = '* ';
            $requests['simpleFields'] = '* ';
        }

        // Manage conditions
        $requests['conditions'] = '';
        if (isset($options['conditions']) && is_array($options['conditions'])) {
            $first = true;
            foreach ($options['conditions'] as $key => $opvalue) {
                if ($first) {
                    $clause = $hasWhereClause ? ' AND (' : ' WHERE (';
                    if (is_array($opvalue) && count($opvalue) == 2) {
                        [$op, $value] = $opvalue;
                    } else {
                        $op = " = ";
                        $value = $opvalue;
                    }
                    $first = false;
                } elseif (is_array($opvalue) && count($opvalue) == 3) {
                    [$clause, $op, $value] = $opvalue;
                } elseif (is_array($opvalue) && count($opvalue) == 2) {
                    $clause = ' AND ';
                    [$op, $value] = $opvalue;
                } else {
                    $clause = ' AND ';
                    $op = " = ";
                    $value = $opvalue;
                }

                if ($op == 'IN') {
                    $inValues = "";
                    if (is_array($value) && count($value)) {
                        $inValues = implode("','", $value);
                    }
                    $requests['conditions'] .= $clause . " " . $key . " " . $op . " ('" . $inValues . "') ";
                } else {
                    $requests['conditions'] .= $clause . " " . $key . " " . $op .
                        " '" . CentreonDBInstance::getDbCentreonInstance()->escape($value) . "' ";
                }
            }
            if (!$first) {
                $requests['conditions'] .= ') ';
            }
        }

        // Manage join
        $requests['join'] = '';
        if (isset($options['join']) && is_array($options['join'])) {
            foreach ($options['join'] as $joinValues) {
                $requests['join'] .= 'INNER JOIN ' . $joinValues['table'] . ' ON ' . $joinValues['condition'] . ' ';
            }
        }

        // Manage order by
        $requests['order'] = '';
        if (isset($options['order'])) {
            if (is_array($options['order'])) {
                $requests['order'] = implode(', ', $options['order']);
            } elseif (!empty($options['order'])) {
                $requests['order'] = $options['order'];
            }
        }
        if ($requests['order'] != '') {
            $requests['order'] = ' ORDER BY ' . $requests['order'];
        }

        // Manage limit and select clause
        $requests['pages'] = '';
        if (isset($options['pages']) && trim($options['pages']) != '') {
            $requests['pages'] = ' LIMIT ' . $options['pages'];
        }

        return $requests;
    }

    /**
     * @param $res
     * @param $options
     *
     * @return string
     */
    private function constructKey($res, $options)
    {
        $key = '';
        $separator = '';
        foreach ($options['keys'] as $value) {
            if ($res[$value] == '') {
                return '';
            }
            $key .= $separator . $res[$value];
            $separator = $options['keys_separator'] ?? '_';
        }

        return $key;
    }

    /**
     * Construct result
     *
     * @param $sql
     * @param mixed $options
     *
     * @return array
     * @access private
     */
    private function constructResult($sql, $options)
    {
        $result = [];

        try {
            $res = CentreonDBInstance::getDbCentreonInstance()->query($sql);
        } catch (PDOException $e) {
            return $result;
        }

        while ($elem = $res->fetchRow()) {
            $key = $this->constructKey($elem, $options);

            if ($key != '' && !isset($result[$key])) {
                $result[$key] = isset($options['get_row']) ? $elem[$options['get_row']] : $elem;
            }
        }

        if (isset($options['total']) && $options['total'] == true) {
            return ['items' => $result, 'total' => CentreonDBInstance::getDbCentreonInstance()->numberRows()];
        } else {
            return $result;
        }
    }

    /**
     * Get ServiceGroup from ACL and configuration DB
     *
     * @param $search
     * @param $broker
     * @param $options
     * @param $sg_empty
     *
     * @return array
     */
    public function getServiceGroupAclConf($search = null, $broker = null, $options = null, $sg_empty = null)
    {
        $sg = [];

        if (is_null($options)) {
            $options = ['order' => ['LOWER(sg_name)'], 'fields' => ['servicegroup.sg_id', 'servicegroup.sg_name'], 'keys' => ['sg_id'], 'keys_separator' => '', 'get_row' => 'sg_name'];
        }

        $request = $this->constructRequest($options);

        $searchCondition = "";
        if ($search != "") {
            $searchCondition = "AND sg_name LIKE '%" . CentreonDB::escape($search) . "%' ";
        }
        if ($this->admin) {
            $empty_exists = "";
            if (!is_null($sg_empty)) {
                $empty_exists = 'AND EXISTS (
                    SELECT * FROM servicegroup_relation
                        WHERE (servicegroup_relation.servicegroup_sg_id = servicegroup.sg_id
                            AND servicegroup_relation.service_service_id IS NOT NULL)) ';
            }
            $query = $request['select'] . $request['fields'] . " "
                . "FROM servicegroup "
                . "WHERE sg_activate = '1' "
                . $searchCondition
                . $empty_exists;
        } else {
            $groupIds = array_keys($this->accessGroups);
            $query = $request['select'] . $request['simpleFields'] . " "
                . "FROM ( "
                . "SELECT " . $request['fields'] . " "
                . "FROM servicegroup "
                . "JOIN servicegroup_relation ON servicegroup.sg_id = servicegroup_relation.servicegroup_sg_id "
                . "JOIN hostgroup_relation ON hostgroup_relation.hostgroup_hg_id = servicegroup_relation.hostgroup_hg_id "
                . "JOIN acl_resources_hg_relations ON hostgroup_relation.hostgroup_hg_id = acl_resources_hg_relations.hg_hg_id "
                . "JOIN acl_res_group_relations ON acl_res_group_relations.acl_res_id = acl_resources_hg_relations.acl_res_id "
                . "WHERE acl_res_group_relations.acl_group_id IN (" . implode(',', $groupIds) . ") "
                . $searchCondition
                . "UNION "
                . "SELECT " . $request['fields'] . " "
                . "FROM servicegroup "
                . "JOIN acl_resources_sg_relations ON servicegroup.sg_id = acl_resources_sg_relations.sg_id "
                . "JOIN acl_res_group_relations ON acl_resources_sg_relations.acl_res_id = acl_res_group_relations.acl_res_id "
                . "WHERE acl_res_group_relations.acl_group_id IN (" . implode(',', $groupIds) . ") "
                . $searchCondition
                . ") as t ";
        }

        $query .= $request['order'] . $request['pages'];

        return $this->constructResult($query, $options);
    }

    /**
     * Get all services linked to a servicegroup regarding ACL
     *
     * @param int $sgId servicegroup id
     * @param mixed $broker
     * @param mixed $options
     * @return array
     */
    public function getServiceServiceGroupAclConf($sgId, $broker = null, $options = null)
    {
        $services = [];

        $db_name_acl = $this->getNameDBAcl($broker);
        if (is_null($db_name_acl) || $db_name_acl == "") {
            return $services;
        }

        if (is_null($options)) {
            $options = ['order' => ['LOWER(host_name)', 'LOWER(service_description)'], 'fields' => ['service.service_description', 'service.service_id', 'host.host_id', 'host.host_name'], 'keys' => ['host_id', 'service_id'], 'keys_separator' => '_'];
        }

        $request = $this->constructRequest($options);

        $from_acl = "";
        $where_acl = "";
        if (!$this->admin) {
            $groupIds = array_keys($this->accessGroups);
            $from_acl = ", `$db_name_acl`.centreon_acl ";
            $where_acl = " AND `$db_name_acl`.centreon_acl.group_id IN (" . implode(',', $groupIds) . ") "
                . "AND `$db_name_acl`.centreon_acl.host_id = host.host_id "
                . "AND `$db_name_acl`.centreon_acl.service_id = service.service_id ";
        }

        // Making sure that the id provided is a real int
        $option = ['default' => 0];
        $sgId = filter_var($sgId, FILTER_VALIDATE_INT, $option);

        /*
         * Using the centreon_storage database to get the information
         * where the services_servicegroups table provides "resolved" dependencies
         * for possible components of the servicegroup which can be:
         *     - simple services
         *     - service templates
         *     - hostgroup services
         */
        $query = $request['select'] . $request['simpleFields'] . " "
            . "FROM ( "
            . "SELECT " . $request['fields'] . " "
            . "FROM `$db_name_acl`.services_servicegroups, service, host" . $from_acl . " "
            . "WHERE servicegroup_id = " . $sgId . " "
            . "AND host.host_id = services_servicegroups.host_id "
            . "AND service.service_id = services_servicegroups.service_id "
            . "AND service.service_activate = '1' AND host.host_activate = '1'"
            . $where_acl . " "
            . ") as t ";

        $query .= $request['order'] . $request['pages'];

        $services = $this->constructResult($query, $options);

        return $services;
    }

    /**
     * Get host acl configuration
     *
     * @param mixed $search
     * @param mixed $broker
     * @param mixed $options
     * @param bool $host_empty | if host_empty is true,
     *                           hosts with no authorized
     *                           services will be returned
     *
     * @access public
     * @return array
     */
    public function getHostAclConf($search = null, $broker = null, $options = null, $host_empty = false)
    {
        $hosts = [];

        $db_name_acl = $this->getNameDBAcl($broker);
        if (is_null($db_name_acl) || $db_name_acl == "") {
            return $hosts;
        }

        if (is_null($options)) {
            $options = ['order' => ['LOWER(host.host_name)'], 'fields' => ['host.host_id', 'host.host_name'], 'keys' => ['host_id'], 'keys_separator' => '', 'get_row' => 'host_name'];
        }

        $request = $this->constructRequest($options, true);

        $searchCondition = "";
        if ($search != "") {
            $searchCondition = "AND (host.host_name LIKE '%" . CentreonDB::escape($search) . "%'
                OR host.host_alias LIKE '%" . CentreonDB::escape($search) . "%') ";
        }

        $emptyJoin = "";
        if ($host_empty) {
            $emptyJoin = "LEFT JOIN host_service_relation on host_service_relation.host_host_id = host.host_id "
                . "AND host_service_relation.service_service_id IS NOT NULL "
                . "LEFT JOIN hostgroup_relation on host.host_id = hostgroup_relation.host_host_id "
                . "AND hostgroup_relation.hostgroup_hg_id = host_service_relation.hostgroup_hg_id "
                . "AND (host_service_relation.hsr_id IS NOT NULL OR hostgroup_relation.hgr_id IS NOT NULL) ";
        }

        if ($this->admin) {
            $query = $request['select'] . $request['fields'] . " "
                . "FROM host "
                . $emptyJoin
                . "WHERE host_register = '1' "
                //. "AND host_activate = '1' "
                . $request['conditions']
                . $searchCondition;
        } else {
            $groupIds = array_keys($this->accessGroups);
            if ($host_empty) {
                $emptyJoin .= "AND `$db_name_acl`.centreon_acl.service_id IS NOT NULL ";
            }
            $query = $request['select'] . $request['fields'] . " "
                . "FROM host "
                . "JOIN `$db_name_acl`.centreon_acl "
                . "ON `$db_name_acl`.centreon_acl.host_id = host.host_id "
                . "AND `$db_name_acl`.centreon_acl.group_id IN (" . implode(',', $groupIds) . ") "
                . $emptyJoin
                . "WHERE host.host_register = '1' "
                //. "AND host.host_activate = '1' "
                . $request['conditions']
                . $searchCondition;
        }

        $query .= $request['order'] . $request['pages'];

        $hosts = $this->constructResult($query, $options);

        return $hosts;
    }

    /**
     * @param $host_id
     * @param $broker
     * @param $options
     *
     * @return array|null
     */
    public function getHostServiceAclConf($host_id, $broker = null, $options = null)
    {
        $services = [];

        $db_name_acl = $this->getNameDBAcl($broker);
        if (is_null($db_name_acl) || $db_name_acl == "") {
            return $services;
        }

        if (is_null($options)) {
            $options = ['order' => ['LOWER(service_description)'], 'fields' => ['s.service_id', 'service_description'], 'keys' => ['service_id'], 'keys_separator' => '', 'get_row' => 'service_description'];
        }

        $request = $this->constructRequest($options);

        if ($this->admin) {
            $query = $request['select'] . $request['simpleFields'] . " "
                . "FROM ( "
                . "SELECT " . $request['fields'] . " "
                . "FROM host_service_relation hsr, host h, service s "
                . "WHERE h.host_id = '" . CentreonDB::escape($host_id) . "' "
                . "AND h.host_activate = '1' "
                . "AND h.host_register = '1' "
                . "AND h.host_id = hsr.host_host_id "
                . "AND hsr.service_service_id = s.service_id "
                . "AND s.service_activate = '1' "
                . "UNION "
                . "SELECT " . $request['fields'] . " "
                . "FROM host h, hostgroup_relation hgr, service s, host_service_relation hsr "
                . "WHERE h.host_id = '" . CentreonDB::escape($host_id) . "' "
                . "AND h.host_activate = '1' "
                . "AND h.host_register = '1' "
                . "AND h.host_id = hgr.host_host_id "
                . "AND hgr.hostgroup_hg_id = hsr.hostgroup_hg_id "
                . "AND hsr.service_service_id = s.service_id "
                . ") as t ";
        } else {
            $query = "SELECT " . $request['fields'] . " "
                . "FROM host_service_relation hsr, host h, service s, `$db_name_acl`.centreon_acl "
                . "WHERE h.host_id = '" . CentreonDB::escape($host_id) . "' "
                . "AND h.host_activate = '1' "
                . "AND h.host_register = '1' "
                . "AND h.host_id = hsr.host_host_id "
                . "AND hsr.service_service_id = s.service_id "
                . "AND s.service_activate = '1' "
                . "AND `$db_name_acl`.centreon_acl.host_id = h.host_id "
                . "AND `$db_name_acl`.centreon_acl.service_id IS NOT NULL "
                . "AND `$db_name_acl`.centreon_acl.service_id = s.service_id "
                . "AND `$db_name_acl`.centreon_acl.group_id IN (" . $this->getAccessGroupsString() . ") "
                . "UNION "
                . "SELECT " . $request['fields'] . " "
                . "FROM host h, hostgroup_relation hgr, "
                . "service s, host_service_relation hsr, `$db_name_acl`.centreon_acl "
                . "WHERE h.host_id = '" . CentreonDB::escape($host_id) . "' "
                . "AND h.host_activate = '1' "
                . "AND h.host_register = '1' "
                . "AND h.host_id = hgr.host_host_id "
                . "AND hgr.hostgroup_hg_id = hsr.hostgroup_hg_id "
                . "AND hsr.service_service_id = s.service_id "
                . "AND `$db_name_acl`.centreon_acl.host_id = h.host_id "
                . "AND `$db_name_acl`.centreon_acl.service_id IS NOT NULL "
                . "AND `$db_name_acl`.centreon_acl.service_id = s.service_id "
                . "AND `$db_name_acl`.centreon_acl.group_id IN (" . $this->getAccessGroupsString() . ") ";
        }

        $query .= $request['order'] . $request['pages'];

        $services = $this->constructResult($query, $options);

        return $services;
    }

    /**
     * Get HostGroup from ACL and configuration DB (enabled only)
     *
     * @param $search
     * @param $broker
     * @param $options
     * @param $hg_empty
     *
     * @return array
     */
    public function getHostGroupAclConf($search = null, $broker = null, $options = null, $hg_empty = false)
    {
        $hg = [];

        if (is_null($options)) {
            $options = ['order' => ['LOWER(hg_name)'], 'fields' => ['hg_id', 'hg_name'], 'keys' => ['hg_id'], 'keys_separator' => '', 'get_row' => 'hg_name'];
        }

        $request = $this->constructRequest($options, true);

        $searchCondition = "";
        if ($search != "") {
            $searchCondition = "AND hg_name LIKE '%" . CentreonDB::escape($search) . "%' ";
        }
        if ($this->admin || $this->hasAccessToAllHostGroups === true) {
            $empty_exists = "";
            if ($hg_empty) {
                $empty_exists = 'AND EXISTS (SELECT * FROM hostgroup_relation WHERE
                    (hostgroup_relation.hostgroup_hg_id = hostgroup.hg_id
                        AND hostgroup_relation.host_host_id IS NOT NULL)) ';
            }
            // We should check if host is activate (maybe)
            $query = $request['select'] . $request['fields'] . " "
                . "FROM hostgroup "
                . "WHERE hg_activate = '1' "
                . $request['conditions']
                . $searchCondition
                . $empty_exists;
        } else {
            // Cant manage empty hostgroup with ACLs. We'll have a problem with acl for conf...
            $groupIds = array_keys($this->getAccessGroups());
            $query = $request['select'] . $request['fields'] . " "
                . "FROM hostgroup, acl_res_group_relations, acl_resources_hg_relations "
                . "WHERE hg_activate = '1' "
                . "AND acl_res_group_relations.acl_group_id  IN (" . implode(',', $groupIds) . ") "
                . "AND acl_res_group_relations.acl_res_id = acl_resources_hg_relations.acl_res_id "
                . "AND acl_resources_hg_relations.hg_hg_id = hostgroup.hg_id "
                . $request['conditions']
                . $searchCondition;
        }

        $query .= $request['order'] . $request['pages'];

        $hg = $this->constructResult($query, $options);

        return $hg;
    }

    /**
     * Get HostGroup from ACL and configuration DB (enabled and disabled)
     *
     * @param string $search
     * @param array<string,mixed> $options
     * @param bool $hg_empty
     * @return string[]
     */
    public function getAllHostGroupAclConf($search = null, $options = null, $hg_empty = false)
    {
        $hg = [];

        if (is_null($options)) {
            $options = [
                'order' => ['LOWER(hg_name)'],
                'fields' => ['hg_id', 'hg_name'],
                'keys' => ['hg_id'],
                'keys_separator' => '',
                'get_row' => 'hg_name'
            ];
        }

        $request = $this->constructRequest($options, true);

        $searchCondition = "";
        if ($search != "") {
            $searchCondition = "AND hg_name LIKE '%" . CentreonDB::escape($search) . "%' ";
        }
        if ($this->admin) {
            $empty_exists = "";
            if ($hg_empty) {
                $empty_exists = 'AND EXISTS (SELECT * FROM hostgroup_relation WHERE
                    (hostgroup_relation.hostgroup_hg_id = hostgroup.hg_id
                        AND hostgroup_relation.host_host_id IS NOT NULL)) ';
            }
            $request = $this->constructRequest($options, false);

            $query = $request['select'] . $request['fields'] . " "
                . "FROM hostgroup "
                . $request['conditions']
                . $searchCondition
                . $empty_exists;
        } else {
            $groupIds = array_keys($this->accessGroups);
            $request = $this->constructRequest($options, true);

            $query = $request['select'] . $request['fields'] . " "
                . "FROM hostgroup, acl_res_group_relations, acl_resources_hg_relations "
                . "WHERE acl_res_group_relations.acl_group_id  IN (" . implode(',', $groupIds) . ") "
                . "AND acl_res_group_relations.acl_res_id = acl_resources_hg_relations.acl_res_id "
                . "AND acl_resources_hg_relations.hg_hg_id = hostgroup.hg_id "
                . $request['conditions']
                . $searchCondition;
        }

        $query .= $request['order'] . $request['pages'];

        $hg = $this->constructResult($query, $options);

        return $hg;
    }

    /**
     * @param $hgId
     * @param null $broker
     * @param null $options
     *
     * @return array
     */
    public function getHostHostGroupAclConf($hgId, $broker = null, $options = null)
    {
        $hg = [];

        if (is_null($options)) {
            $options = ['distinct' => true, 'order' => ['LOWER(host_name)'], 'fields' => ['host_id', 'host_name'], 'keys' => ['host_id'], 'keys_separator' => '', 'get_row' => 'host_name'];
        }

        $request = $this->constructRequest($options);

        if ($this->admin) {
            $query = $request['select'] . $request['fields'] . " "
                . "FROM hostgroup, hostgroup_relation, host "
                . "WHERE hg_id = '" . CentreonDB::escape($hgId) . "' "
                . "AND hg_activate = '1' "
                . "AND host_activate='1' "
                . "AND hostgroup_relation.hostgroup_hg_id = hostgroup.hg_id "
                . "AND hostgroup_relation.host_host_id = host.host_id ";
        } else {
            // Cant manage empty hostgroup with ACLs. We'll have a problem with acl for conf...
            $groupIds = array_keys($this->accessGroups);
            $query = $request['select'] . $request['fields'] . " "
                . "FROM hostgroup, hostgroup_relation, host, acl_res_group_relations, acl_resources_hg_relations "
                . "WHERE hg_id = '" . CentreonDB::escape($hgId) . "' "
                . "AND hg_activate = '1' "
                . "AND host_activate='1' "
                . "AND hostgroup_relation.hostgroup_hg_id = hostgroup.hg_id "
                . "AND hostgroup_relation.host_host_id = host.host_id "
                . "AND acl_res_group_relations.acl_group_id  IN (" . implode(',', $groupIds) . ") "
                . "AND acl_res_group_relations.acl_res_id = acl_resources_hg_relations.acl_res_id "
                . "AND acl_resources_hg_relations.hg_hg_id = hostgroup.hg_id "
                . "AND host.host_id NOT IN ( SELECT DISTINCT host_host_id "
                . "FROM acl_resources_hostex_relations WHERE acl_res_id IN (" . implode(',', $groupIds) . "))";
        }

        $query .= $request['order'] . $request['pages'];

        $hg = $this->constructResult($query, $options);

        return $hg;
    }

    /**
     * Get poller acl configuration
     *
     * @param array $options
     *
     * @return array
     */
    public function getPollerAclConf($options = [])
    {
        if (!count($options)) {
            $options = ['fields' => ['id', 'name'], 'order' => ['name'], 'keys' => ['id']];
        }

        $request = $this->constructRequest($options);

        $pollerstring = $this->getPollerString();
        $pollerfilter = "";
        if (!$this->admin && $pollerstring != "''") {
            $pollerfilter = $this->queryBuilder($request['conditions'] ? 'AND' : 'WHERE', 'id', $pollerstring);
        }
        $sql = $request['select'] . $request['fields'] . " "
            . "FROM nagios_server "
            . $request['conditions']
            . $pollerfilter;

        $sql .= $request['order'] . $request['pages'];

        $result = $this->constructResult($sql, $options);

        return $result;
    }

    /**
     * Get contact acl configuration
     *
     * @param array $options
     *
     * @return array
     */
    public function getContactAclConf($options = [])
    {
        $request = $this->constructRequest($options, true);
        if ($this->admin) {
            $sql = $request['select'] . $request['fields'] . " "
                . "FROM contact "
                . $request['join']
                . "WHERE contact_register = '1' "
                . $request['conditions'];
        } else {
            $sql = $request['select'] . $request['fields'] . " "
                . "FROM ( "
                . "SELECT " . $request['fields'] . " "
                . "FROM acl_group_contacts_relations agcr, contact c "
                . $request['join']
                . "WHERE c.contact_id = agcr.contact_contact_id "
                . "AND c.contact_register = '1'"
                . "AND agcr.acl_group_id IN (" . $this->getAccessGroupsString() . ") "
                . $request['conditions']
                . " UNION "
                . "SELECT " . $request['fields'] . " "
                . "FROM acl_group_contactgroups_relations agccgr, contactgroup_contact_relation ccr, contact c "
                . "WHERE c.contact_id = ccr.contact_contact_id "
                . "AND c.contact_register = '1' "
                . "AND ccr.contactgroup_cg_id = agccgr.cg_cg_id "
                . "AND agccgr.acl_group_id IN (" . $this->getAccessGroupsString() . ") "
                . $request['conditions']
                . ") as t ";
        }

        $sql .= $request['order'] . $request['pages'];
        $result = $this->constructResult($sql, $options);

        return $result;
    }

    /**
     * Get contact group acl configuration
     *
     * @param array $options
     * @param bool $localOnly Indicates if only local contactgroups should be searched
     * @return array
     */
    public function getContactGroupAclConf(array $options = [], bool $localOnly = true)
    {
        $request = $this->constructRequest($options, true);

        $ldapCondition = "";
        $sJointure = "";
        $sCondition = "";

        if (!$localOnly) {
            $ldapCondition = "OR cg.cg_type = 'ldap' ";
            $sJointure = " LEFT JOIN  auth_ressource auth ON cg.ar_id =  auth.ar_id  ";
        }


        if ($this->admin) {
            $sql = $request['select'] . $request['fields'] . " "
                . "FROM contactgroup cg " . $sJointure
                . "WHERE (cg.cg_type = 'local' " . $ldapCondition . ") "
                . $sCondition
                . $request['conditions'];
        } else {
            $sql = $request['select'] . $request['fields'] . " "
                . "FROM acl_group_contactgroups_relations agccgr, contactgroup cg " . $sJointure
                . "WHERE cg.cg_id = agccgr.cg_cg_id "
                . "AND (cg.cg_type = 'local' " . $ldapCondition . ") "
                . "AND agccgr.acl_group_id IN (" . $this->getAccessGroupsString() . ") "
                . $request['conditions'];
        }

        $sql .= $request['order'] . $request['pages'];
        $result = $this->constructResult($sql, $options);

        return $result;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    public function getAclGroupAclConf($options = [])
    {
        $request = $this->constructRequest($options);

        $sql = $request['select'] . $request['fields'] . " "
            . "FROM acl_groups "
            . $request['conditions'];

        $sql .= $request['order'] . $request['pages'];

        $result = $this->constructResult($sql, $options);

        return $result;
    }

    /**
     * Duplicate Host ACL
     *
     * @param array $hosts | hosts to duplicate
     * @return void
     */
    public static function duplicateHostAcl($hosts = []): void
    {
        $sql = "INSERT INTO %s (host_host_id, acl_res_id)
            (SELECT %d, acl_res_id FROM %s WHERE host_host_id = %d)";
        $tbHost = "acl_resources_host_relations";
        $tbHostEx = "acl_resources_hostex_relations";
        foreach ($hosts as $copyId => $originalId) {
            CentreonDBInstance::getDbCentreonInstance()->query(sprintf($sql, $tbHost, $copyId, $tbHost, $originalId));
            CentreonDBInstance::getDbCentreonInstance()->query(sprintf($sql, $tbHostEx, $copyId, $tbHostEx, $originalId));
        }
    }

    /**
     * Duplicate Host Group ACL
     *
     * @param array $hgs | host groups to duplicate
     *
     * @return void
     */
    public static function duplicateHgAcl($hgs = []): void
    {
        $sql = "INSERT INTO %s
                    (hg_hg_id, acl_res_id)
                    (SELECT %d, acl_res_id
                    FROM %s
                    WHERE hg_hg_id = %d)";
        $tb = "acl_resources_hg_relations";
        foreach ($hgs as $copyId => $originalId) {
            CentreonDBInstance::getDbCentreonInstance()->query(sprintf($sql, $tb, $copyId, $tb, $originalId));
        }
    }

    /**
     * Duplicate Service Group ACL
     *
     * @param array $sgs | service groups to duplicate
     *
     * @return void
     */
    public static function duplicateSgAcl($sgs = []): void
    {
        $sql = "INSERT INTO %s
                    (sg_id, acl_res_id)
                    (SELECT %d, acl_res_id
                    FROM %s
                    WHERE sg_id = %d)";
        $tb = "acl_resources_sg_relations";
        foreach ($sgs as $copyId => $originalId) {
            CentreonDBInstance::getDbCentreonInstance()->query(sprintf($sql, $tb, $copyId, $tb, $originalId));
        }
    }

    /**
     * Duplicate Host Category ACL
     *
     * @param array $hcs | host categories to duplicate
     *
     * @return void
     */
    public static function duplicateHcAcl($hcs = []): void
    {
        $sql = "INSERT INTO %s
                    (hc_id, acl_res_id)
                    (SELECT %d, acl_res_id
                    FROM %s
                    WHERE hc_id = %d)";
        $tb = "acl_resources_hc_relations";
        foreach ($hcs as $copyId => $originalId) {
            CentreonDBInstance::getDbCentreonInstance()->query(sprintf($sql, $tb, $copyId, $tb, $originalId));
        }
    }

    /**
     * Duplicate Service Category ACL
     *
     * @param array $scs | service categories to duplicate
     *
     * @return void
     */
    public static function duplicateScAcl($scs = []): void
    {
        $sql = "INSERT INTO %s
                    (sc_id, acl_res_id)
                    (SELECT %d, acl_res_id
                    FROM %s
                    WHERE sc_id = %d)";
        $tb = "acl_resources_sc_relations";
        foreach ($scs as $copyId => $originalId) {
            CentreonDBInstance::getDbCentreonInstance()->query(sprintf($sql, $tb, $copyId, $tb, $originalId));
        }
    }
}
