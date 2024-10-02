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

namespace Core\Dashboard\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Role\DashboardContactGroupRole;
use Core\Dashboard\Domain\Model\Role\DashboardContactRole;
use Core\Dashboard\Domain\Model\Role\DashboardGlobalRole;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardContactGroupShare;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;
use Core\Dashboard\Infrastructure\Model\DashboardGlobalRoleConverter;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;
use Core\Infrastructure\Common\Repository\RepositoryException;
use Utility\SqlConcatenator;

class DbReadDashboardShareRepository extends AbstractRepositoryDRB implements ReadDashboardShareRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findDashboardContactSharesByRequestParameter(
        Dashboard $dashboard,
        RequestParametersInterface $requestParameters
    ): array {
        $requestParameters->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'c.contact_id',
            'name' => 'c.contact_name',
            'email' => 'c.contact_email',
        ]);

        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                    SELECT
                        c.`contact_id`,
                        c.`contact_name`,
                        c.`contact_email`,
                        dcr.`role`
                    SQL
            )
            ->defineFrom(
                <<<'SQL'
                    FROM `:db`.`dashboard_contact_relation` dcr
                    SQL
            )
            ->defineJoins(
                <<<'SQL'
                    INNER JOIN `:db`.`contact` c ON c.`contact_id`=dcr.`contact_id`
                    SQL
            )
            ->defineWhere(
                <<<'SQL'
                    WHERE dcr.`dashboard_id` = :dashboard_id
                    SQL
            )
            ->storeBindValue(':dashboard_id', $dashboard->getId(), \PDO::PARAM_INT)
            ->defineOrderBy(
                <<<'SQL'
                    ORDER BY c.`contact_name` ASC
                    SQL
            );

        $sqlTranslator->translateForConcatenator($concatenator);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $sqlTranslator->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        // Retrieve data
        $shares = [];
        foreach ($statement as $result) {
            /** @var array{
             *     contact_id: int,
             *     contact_name: string,
             *     contact_email: string,
             *     role: string
             * } $result
             */
            $shares[] = new DashboardContactShare(
                $dashboard,
                $result['contact_id'],
                $result['contact_name'],
                $result['contact_email'],
                $this->stringToRole($result['role'])
            );
        }

        return $shares;
    }

    public function findDashboardContactGroupSharesByRequestParameter(
        Dashboard $dashboard,
        RequestParametersInterface $requestParameters
    ): array {
        $requestParameters->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'cg.cg_id',
            'name' => 'cg.cg_name',
        ]);

        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                    SELECT DISTINCT
                        cg.`cg_id`,
                        cg.`cg_name`,
                        dcgr.`role`
                    SQL
            )
            ->defineFrom(
                <<<'SQL'
                    FROM `:db`.`dashboard_contactgroup_relation` dcgr
                    SQL
            )
            ->defineJoins(
                <<<'SQL'
                    INNER JOIN `:db`.`contactgroup` cg ON cg.`cg_id`=dcgr.`contactgroup_id`
                    INNER JOIN `:db`.`contactgroup_contact_relation` cgcr ON cg.`cg_id`=cgcr.`contactgroup_cg_id`
                    SQL
            )
            ->defineWhere(
                <<<'SQL'
                    WHERE dcgr.`dashboard_id` = :dashboard_id
                    SQL
            )
            ->storeBindValue(':dashboard_id', $dashboard->getId(), \PDO::PARAM_INT)
            ->defineOrderBy(
                <<<'SQL'
                    ORDER BY cg.`cg_name` ASC
                    SQL
            );

        $sqlTranslator->translateForConcatenator($concatenator);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $sqlTranslator->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        // Retrieve data
        $shares = [];
        foreach ($statement as $result) {
            /** @var array{
             *     cg_id: int,
             *     cg_name: string,
             *     role: string
             * } $result
             */
            $shares[] = new DashboardContactGroupShare(
                $dashboard,
                $result['cg_id'],
                $result['cg_name'],
                $this->stringToRole($result['role'])
            );
        }

        return $shares;
    }

    public function getOneSharingRoles(ContactInterface $contact, Dashboard $dashboard): DashboardSharingRoles
    {
        $contactShares = $this->getContactShares($contact, $dashboard);
        $contactGroupShares = $this->getContactGroupShares($contact, $dashboard);
        $dashboardId = $dashboard->getId();

        return new DashboardSharingRoles(
            $dashboard,
            $contactShares[$dashboardId] ?? null,
            $contactGroupShares[$dashboardId] ?? [],
        );
    }

    public function getMultipleSharingRoles(ContactInterface $contact, Dashboard ...$dashboards): array
    {
        $contactShares = $this->getContactShares($contact, ...$dashboards);
        $contactGroupShares = $this->getContactGroupShares($contact, ...$dashboards);

        $objects = [];
        foreach ($dashboards as $dashboard) {
            $dashboardId = $dashboard->getId();

            $objects[$dashboardId] = new DashboardSharingRoles(
                $dashboard,
                $contactShares[$dashboardId] ?? null,
                $contactGroupShares[$dashboardId] ?? [],
            );
        }

        return $objects;
    }

    /**
     * @inheritDoc
     */
    public function findDashboardsContactShares(Dashboard ...$dashboards): array
    {
        if ([] === $dashboards) {
            return [];
        }

        $dashboardsById = [];
        foreach ($dashboards as $dashboard) {
            $dashboardsById[$dashboard->getId()] = $dashboard;
        }

        $select = <<<'SQL'
            SELECT
                dcr.`dashboard_id`,
                dcr.`role`,
                c.`contact_id`,
                c.`contact_email`,
                c.`contact_name`
            FROM `:db`.`dashboard_contact_relation` dcr
            INNER JOIN contact c
                ON dcr.contact_id = c.contact_id
            WHERE dcr.`dashboard_id` IN (:dashboard_ids)
            SQL;

        $concatenator = (new SqlConcatenator())
            ->defineSelect($select)
            ->storeBindValueMultiple(':dashboard_ids', array_keys($dashboardsById), \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $shares = [];
        foreach ($statement as $result) {
            /** @var array{
             *     dashboard_id: int,
             *     role: string,
             *     contact_id: int,
             *     contact_email: string,
             *     contact_name: string
             * } $result
             */
            $dashboardId = $result['dashboard_id'];

            $shares[$dashboardId][] = new DashboardContactShare(
                $dashboardsById[$dashboardId],
                $result['contact_id'],
                $result['contact_name'],
                $result['contact_email'],
                $this->stringToRole($result['role'])
            );
        }

        return $shares;
    }

    /**
     * @inheritDoc
     */
    public function findDashboardsContactSharesByContactIds(array $contactIds, Dashboard ...$dashboards): array
    {
        if ([] === $dashboards) {
            return [];
        }

        $dashboardsById = [];
        foreach ($dashboards as $dashboard) {
            $dashboardsById[$dashboard->getId()] = $dashboard;
        }

        $select = <<<'SQL'
            SELECT
                dcr.`dashboard_id`,
                dcr.`role`,
                c.`contact_id`,
                c.`contact_email`,
                c.`contact_name`
            FROM `:db`.`dashboard_contact_relation` dcr
            INNER JOIN contact c
                ON dcr.contact_id = c.contact_id
            WHERE dcr.`dashboard_id` IN (:dashboard_ids)
            AND c.contact_id IN(:contact_ids)
            SQL;

        $concatenator = (new SqlConcatenator())
            ->defineSelect($select)
            ->storeBindValueMultiple(':dashboard_ids', array_keys($dashboardsById), \PDO::PARAM_INT)
            ->storeBindValueMultiple(':contact_ids', $contactIds, \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $shares = [];
        foreach ($statement as $result) {
            /** @var array{
             *     dashboard_id: int,
             *     role: string,
             *     contact_id: int,
             *     contact_email: string,
             *     contact_name: string
             * } $result
             */
            $dashboardId = $result['dashboard_id'];

            $shares[$dashboardId][] = new DashboardContactShare(
                $dashboardsById[$dashboardId],
                $result['contact_id'],
                $result['contact_name'],
                $result['contact_email'],
                $this->stringToRole($result['role'])
            );
        }

        return $shares;
    }

    /**
     * @inheritDoc
     */
    public function findDashboardsContactGroupShares(Dashboard ...$dashboards): array
    {
        if ([] === $dashboards) {
            return [];
        }

        $dashboardsById = [];
        foreach ($dashboards as $dashboard) {
            $dashboardsById[$dashboard->getId()] = $dashboard;
        }

        $select = <<<'SQL'
            SELECT
                cg.`cg_id`,
                cg.`cg_name`,
                dcgr.`dashboard_id`,
                dcgr.`role`
            FROM `:db`.`dashboard_contactgroup_relation` dcgr
                     INNER JOIN `:db`.`contactgroup` cg ON cg.`cg_id`= dcgr.`contactgroup_id`
            WHERE dcgr.`dashboard_id` IN (:dashboard_ids)
            SQL;

        $concatenator = (new SqlConcatenator())
            ->defineSelect($select)
            ->storeBindValueMultiple(':dashboard_ids', array_keys($dashboardsById), \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $shares = [];
        foreach ($statement as $result) {
            /** @var array{
             *     cg_id: int,
             *     cg_name: string,
             *     dashboard_id: int,
             *     role: string
             * } $result
             */
            $dashboardId = $result['dashboard_id'];
            if (! isset($dashboardsById[$dashboardId])) {
                continue;
            }

            $shares[$dashboardId][] = new DashboardContactGroupShare(
                $dashboardsById[$dashboardId],
                $result['cg_id'],
                $result['cg_name'],
                $this->stringToRole($result['role'])
            );
        }

        return $shares;
    }

    /**
     * @inheritDoc
     */
    public function findDashboardsContactGroupSharesByContact(ContactInterface $contact, Dashboard ...$dashboards): array
    {
        return $this->getContactGroupShares($contact, ...$dashboards);
    }

    /**
     * @inheritDoc
     */
    public function findContactsWithAccessRightByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator->setConcordanceArray([
            'name' => 'c.contact_name',
        ]);

        $query = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS GROUP_CONCAT(topology.topology_name) as topologies, c.contact_name, c.contact_id, c.contact_email
                FROM `:db`.contact c
                    LEFT JOIN `:db`.contactgroup_contact_relation cgcr
                        ON cgcr.contact_contact_id = c.contact_id
                    LEFT JOIN `:db`.acl_group_contactgroups_relations gcgr
                        ON gcgr.cg_cg_id = cgcr.contactgroup_cg_id
                    LEFT JOIN `:db`.acl_group_contacts_relations gcr
                        ON gcr.contact_contact_id = c.contact_id
                    LEFT JOIN `:db`.acl_group_topology_relations agtr
                        ON agtr.acl_group_id = gcr.acl_group_id
                            OR agtr.acl_group_id = gcgr.acl_group_id
                    LEFT JOIN `:db`.acl_topology_relations acltr
                        ON acltr.acl_topo_id = agtr.acl_topology_id
                    INNER JOIN `:db`.topology
                        ON topology.topology_id = acltr.topology_topology_id
                    INNER JOIN `:db`.topology parent
                        ON topology.topology_parent = parent.topology_page
            SQL;

        $searchRequest = $sqlTranslator->translateSearchParameterToSql();
        $query .= $searchRequest !== null
            ? $searchRequest . ' AND '
            : ' WHERE ';

        $query .= <<<'SQL'
            parent.topology_name = 'Dashboards'
            AND topology.topology_name IN ('Viewer','Administrator','Creator')
            AND acltr.access_right IS NOT NULL
                AND c.contact_oreon = '1'
            GROUP BY c.contact_id
            SQL;

        $query .= $sqlTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            /**
             * @var int
             */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }
        $statement->execute();

        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $dashboardContactRoles = [];
        foreach ($statement as $contactRole) {
            /** @var array{
             *     topologies: string,
             *     contact_name: string,
             *     contact_id: int,
             *     contact_email: string
             * } $contactRole
             */
            $dashboardContactRoles[] = $this->createDashboardContactRole($contactRole);
        }

        return $dashboardContactRoles;
    }

    /**
     * @inheritDoc
     */
    public function findContactsWithAccessRightByContactIds(array $contactIds): array
    {
        $bind = [];
        foreach ($contactIds as $key => $contactId) {
            $bind[':contact_id' . $key] = $contactId;
        }
        if ([] === $bind) {
            return [];
        }

        $bindTokenAsString = implode(', ', array_keys($bind));

        $query = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS GROUP_CONCAT(topology.topology_name) as topologies, c.contact_name, c.contact_id, c.contact_email
                FROM `:db`.contact c
                    LEFT JOIN `:db`.contactgroup_contact_relation cgcr
                        ON cgcr.contact_contact_id = c.contact_id
                    LEFT JOIN `:db`.acl_group_contactgroups_relations gcgr
                        ON gcgr.cg_cg_id = cgcr.contactgroup_cg_id
                    LEFT JOIN `:db`.acl_group_contacts_relations gcr
                        ON gcr.contact_contact_id = c.contact_id
                    LEFT JOIN `:db`.acl_group_topology_relations agtr
                        ON agtr.acl_group_id = gcr.acl_group_id
                            OR agtr.acl_group_id = gcgr.acl_group_id
                    LEFT JOIN `:db`.acl_topology_relations acltr
                        ON acltr.acl_topo_id = agtr.acl_topology_id
                    INNER JOIN `:db`.topology
                        ON topology.topology_id = acltr.topology_topology_id
                    INNER JOIN `:db`.topology parent
                        ON topology.topology_parent = parent.topology_page
                    WHERE parent.topology_name = 'Dashboards'
                        AND topology.topology_name IN ('Viewer','Administrator','Creator')
                        AND acltr.access_right IS NOT NULL
                        AND c.contact_oreon = '1'
                        AND c.contact_id IN ({$bindTokenAsString})
                        GROUP BY c.contact_id
            SQL;
        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($bind as $token => $contactId) {
            $statement->bindValue($token, $contactId, \PDO::PARAM_INT);
        }
        $statement->execute();

        $dashboardContactRoles = [];
        foreach ($statement as $contactRole) {
            /** @var array{
             *     topologies: string,
             *     contact_name: string,
             *     contact_id: int,
             *     contact_email: string
             * } $contactRole
             */
            $dashboardContactRoles[] = $this->createDashboardContactRole($contactRole);
        }

        return $dashboardContactRoles;
    }

    /**
     * @inheritDoc
     */
    public function findContactGroupsWithAccessRightByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator->setConcordanceArray([
            'name' => 'cg.cg_name',
        ]);

        $query = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS GROUP_CONCAT(topology.topology_name) as topologies, cg.cg_name, cg.cg_id
                FROM `:db`.contactgroup cg
                    LEFT JOIN `:db`.acl_group_contactgroups_relations gcgr
                        ON gcgr.cg_cg_id = cg.cg_id
                    LEFT JOIN `:db`.acl_group_topology_relations agtr
                        ON agtr.acl_group_id = gcgr.acl_group_id
                    LEFT JOIN `:db`.acl_topology_relations acltr
                        ON acltr.acl_topo_id = agtr.acl_topology_id
                    INNER JOIN `:db`.topology
                        ON topology.topology_id = acltr.topology_topology_id
                    INNER JOIN `:db`.topology parent
                        ON topology.topology_parent = parent.topology_page
            SQL;

        $searchRequest = $sqlTranslator->translateSearchParameterToSql();
        $query .= $searchRequest !== null
            ? $searchRequest . ' AND '
            : ' WHERE ';

        $query .= <<<'SQL'
            parent.topology_name = 'Dashboards'
            AND topology.topology_name IN ('Viewer','Administrator','Creator')
            AND acltr.access_right IS NOT NULL
            GROUP BY cg.cg_id
            SQL;

        $query .= $sqlTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            /**
             * @var int
             */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }
        $statement->execute();

        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $dashboardContactGroupRoles = [];
        foreach ($statement as $contactGroupRole) {
            /** @var array{
             *     topologies: string,
             *     cg_name: string,
             *     cg_id: int,
             * } $contactGroupRole
             */
            $dashboardContactGroupRoles[] = $this->createDashboardContactGroupRole($contactGroupRole);
        }

        return $dashboardContactGroupRoles;
    }

    public function findContactGroupsWithAccessRightByContactGroupIds(array $contactGroupIds): array
    {
        $bind = [];
        foreach ($contactGroupIds as $key => $contactGroupId) {
            $bind[':contact_group' . $key] = $contactGroupId;
        }

        if ([] === $bind) {
            return [];
        }

        $bindTokenAsString = implode(', ', array_keys($bind));

        $query = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS GROUP_CONCAT(topology.topology_name) as topologies, cg.cg_name, cg.cg_id
                FROM `:db`.contactgroup cg
                    LEFT JOIN `:db`.acl_group_contactgroups_relations gcgr
                        ON gcgr.cg_cg_id = cg.cg_id
                    LEFT JOIN `:db`.acl_group_topology_relations agtr
                        ON agtr.acl_group_id = gcgr.acl_group_id
                    LEFT JOIN `:db`.acl_topology_relations acltr
                        ON acltr.acl_topo_id = agtr.acl_topology_id
                    INNER JOIN `:db`.topology
                        ON topology.topology_id = acltr.topology_topology_id
                    INNER JOIN `:db`.topology parent
                        ON topology.topology_parent = parent.topology_page
                    WHERE parent.topology_name = 'Dashboards'
                        AND topology.topology_name IN ('Viewer','Administrator','Creator')
                        AND acltr.access_right IS NOT NULL
                        AND cg.cg_id IN ({$bindTokenAsString})
                    GROUP BY cg.cg_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($bind as $token => $contactGroupId) {
            $statement->bindValue($token, $contactGroupId, \PDO::PARAM_INT);
        }
        $statement->execute();

        $dashboardContactGroupRoles = [];
        foreach ($statement as $contactGroupRole) {
            /** @var array{
             *     topologies: string,
             *     cg_name: string,
             *     cg_id: int,
             * } $contactGroupRole
             */
            $dashboardContactGroupRoles[] = $this->createDashboardContactGroupRole($contactGroupRole);
        }

        return $dashboardContactGroupRoles;
    }

    /**
     * @inheritDoc
     */
    public function findContactsWithAccessRightByACLGroupsAndRequestParameters(
        RequestParametersInterface $requestParameters,
        array $aclGroupIds
    ): array {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(
            RequestParameters::CONCORDANCE_MODE_STRICT
        );
        $sqlTranslator->setConcordanceArray([
            'name' => 'c.contact_name',
        ]);

        $bind = [];
        foreach ($aclGroupIds as $key => $aclGroupId) {
            $bind[':acl_group_' . $key] = $aclGroupId;
        }

        if ([] === $bind) {
            return [];
        }

        $bindTokenAsString = implode(', ', array_keys($bind));

        $query = <<<'SQL'
            SELECT GROUP_CONCAT(topology.topology_name) as topologies, c.contact_name, c.contact_id, c.contact_email
                FROM `:db`.contact c
                    LEFT JOIN `:db`.contactgroup_contact_relation cgcr
                        ON cgcr.contact_contact_id = c.contact_id
                    LEFT JOIN `:db`.acl_group_contactgroups_relations gcgr
                        ON gcgr.cg_cg_id = cgcr.contactgroup_cg_id
                    LEFT JOIN `:db`.acl_group_contacts_relations gcr
                        ON gcr.contact_contact_id = c.contact_id
                    LEFT JOIN `:db`.acl_group_topology_relations agtr
                        ON agtr.acl_group_id = gcr.acl_group_id
                            OR agtr.acl_group_id = gcgr.acl_group_id
                    LEFT JOIN `:db`.acl_topology_relations acltr
                        ON acltr.acl_topo_id = agtr.acl_topology_id
                    INNER JOIN `:db`.topology
                        ON topology.topology_id = acltr.topology_topology_id
                    INNER JOIN `:db`.topology parent
                        ON topology.topology_parent = parent.topology_page
            SQL;

        $searchRequest = $sqlTranslator->translateSearchParameterToSql();
        $query .= $searchRequest !== null
            ? $searchRequest . ' AND '
            : ' WHERE ';

        $query .= <<<SQL
            parent.topology_name = 'Dashboards'
                AND topology.topology_name IN ('Viewer','Administrator','Creator')
                AND gcr.acl_group_id IN ({$bindTokenAsString})
                AND acltr.access_right IS NOT NULL
                AND c.contact_oreon = '1'
            GROUP BY c.contact_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($bind as $token => $aclGroupId) {
            $statement->bindValue($token, $aclGroupId, \PDO::PARAM_INT);
        }
        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            /**
             * @var int
             */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }
        $statement->execute();

        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $dashboardContactRoles = [];
        foreach ($statement as $contactRole) {
            /** @var array{
             *     topologies: string,
             *     contact_name: string,
             *     contact_id: int,
             *     contact_email: string
             * } $contactRole
             */
            $dashboardContactRoles[] = $this->createDashboardContactRole($contactRole);
        }

        return $dashboardContactRoles;
    }

    /**
     * @inheritDoc
     */
    public function findContactGroupsWithAccessRightByUserAndRequestParameters(
        RequestParametersInterface $requestParameters,
        int $contactId
    ): array {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(
            RequestParameters::CONCORDANCE_MODE_STRICT
        );
        $sqlTranslator->setConcordanceArray([
            'name' => 'cg.cg_name',
        ]);

        $query = <<<'SQL'
                SELECT SQL_CALC_FOUND_ROWS GROUP_CONCAT(topology.topology_name) as topologies, cg.cg_name, cg.cg_id
                FROM `:db`.contactgroup cg
                    LEFT JOIN `:db`.contactgroup_contact_relation cgcr
                        ON cgcr.contactgroup_cg_id = cg.cg_id
                    LEFT JOIN `:db`.acl_group_contactgroups_relations gcgr
                        ON gcgr.cg_cg_id = cg.cg_id
                    LEFT JOIN `:db`.acl_group_topology_relations agtr
                        ON agtr.acl_group_id = gcgr.acl_group_id
                    LEFT JOIN `:db`.acl_topology_relations acltr
                        ON acltr.acl_topo_id = agtr.acl_topology_id
                    INNER JOIN `:db`.topology
                        ON topology.topology_id = acltr.topology_topology_id
                    INNER JOIN `:db`.topology parent
                        ON topology.topology_parent = parent.topology_page
            SQL;

        $searchRequest = $sqlTranslator->translateSearchParameterToSql();
        $query .= $searchRequest !== null
            ? $searchRequest . ' AND '
            : ' WHERE ';

        $query .= <<<'SQL'
            parent.topology_name = 'Dashboards'
                AND topology.topology_name IN ('Viewer','Administrator','Creator')
                AND cgcr.contact_contact_id = :contactId
                AND acltr.access_right IS NOT NULL
            GROUP BY cg.cg_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':contactId', $contactId, \PDO::PARAM_INT);
        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            /**
             * @var int
             */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }
        $statement->execute();

        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $dashboardContactGroupRoles = [];
        foreach ($statement as $contactGroupRole) {
            /** @var array{
             *     topologies: string,
             *     cg_name: string,
             *     cg_id: int,
             * } $contactGroupRole
             */
            $dashboardContactGroupRoles[] = $this->createDashboardContactGroupRole($contactGroupRole);
        }

        return $dashboardContactGroupRoles;
    }

    /**
     * @inheritDoc
     */
    public function existsAsEditor(int $dashboardId, ContactInterface $contact): bool
    {
        $query = <<<'SQL'
            SELECT 1 FROM `:db`.`dashboard` d
            LEFT JOIN  `:db`.`dashboard_contact_relation` dcr
            ON dcr.dashboard_id = d.id
            LEFT JOIN  `:db`.`dashboard_contactgroup_relation` dcgr
            ON dcgr.dashboard_id = d.id
            WHERE
                (dcgr.dashboard_id = :dashboardId
                AND dcgr.contactgroup_id IN (
                    SELECT contactgroup_cg_id FROM `:db`.contactgroup_contact_relation
                    WHERE contact_contact_id = :contactId
                )
                AND dcgr.role = 'editor')
            OR (
                dcr.dashboard_id = :dashboardId
                AND dcr.contact_id = :contactId
                AND dcr.role = 'editor')
            OR d.created_by = :contactId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':contactId', $contact->getId(), \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param array{
     *      contact_name: string,
     *      contact_id: int,
     *      contact_email: string,
     *      contact_admin?: string,
     *      topologies?: string
     *  } $contactRole
     *
     * @throws \UnexpectedValueException
     *
     * @return DashboardContactRole
     */
    private function createDashboardContactRole(array $contactRole): DashboardContactRole
    {
        $topologies = array_key_exists('topologies', $contactRole)
            ? explode(',', $contactRole['topologies'])
            : [];
        $roles = array_map(
            static fn (string $topology): DashboardGlobalRole => DashboardGlobalRoleConverter::fromString(
                $topology
            ),
            $topologies
        );

        return new DashboardContactRole(
            $contactRole['contact_id'],
            $contactRole['contact_name'],
            $contactRole['contact_email'],
            $roles
        );
    }

    /**
     * @param array{
     *      cg_name: string,
     *      cg_id: int,
     *      topologies: string
     *  } $contactRole
     *
     * @throws \UnexpectedValueException
     *
     * @return DashboardContactGroupRole
     */
    private function createDashboardContactGroupRole(array $contactRole): DashboardContactGroupRole
    {
        $topologies = explode(',', $contactRole['topologies']);
        $roles = array_map(
            static fn (string $topology): DashboardGlobalRole => DashboardGlobalRoleConverter::fromString(
                $topology
            ),
            $topologies
        );

        return new DashboardContactGroupRole(
            $contactRole['cg_id'],
            $contactRole['cg_name'],
            $roles
        );
    }

    /**
     * @param ContactInterface $contact
     * @param Dashboard ...$dashboards
     *
     * @throws RepositoryException
     * @throws \PDOException
     * @throws AssertionFailedException
     *
     * @return array<int, array<DashboardContactGroupShare>>
     */
    private function getContactGroupShares(ContactInterface $contact, Dashboard ...$dashboards): array
    {
        if ([] === $dashboards) {
            return [];
        }

        $dashboardsById = [];
        foreach ($dashboards as $dashboard) {
            $dashboardsById[$dashboard->getId()] = $dashboard;
        }

        $select = <<<'SQL'
            SELECT
                cg.`cg_id`,
                cg.`cg_name`,
                dcgr.`dashboard_id`,
                dcgr.`role`
            FROM `:db`.`dashboard_contactgroup_relation` dcgr
            INNER JOIN `:db`.`contactgroup` cg ON cg.`cg_id`=dcgr.`contactgroup_id`
            INNER JOIN `:db`.`contactgroup_contact_relation` cgcr ON cg.`cg_id`=cgcr.`contactgroup_cg_id`
            WHERE
                cgcr.`contact_contact_id` = :contact_id
                AND dcgr.`dashboard_id` IN (:dashboard_ids)
            SQL;

        $concatenator = (new SqlConcatenator())
            ->defineSelect($select)
            ->storeBindValueMultiple(':dashboard_ids', array_keys($dashboardsById), \PDO::PARAM_INT)
            ->storeBindValue(':contact_id', $contact->getId(), \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $shares = [];
        foreach ($statement as $result) {
            /** @var array{
             *     cg_id: int,
             *     cg_name: string,
             *     dashboard_id: int,
             *     role: string
             * } $result
             */
            $dashboardId = $result['dashboard_id'];
            if (! isset($dashboardsById[$dashboardId])) {
                continue;
            }

            $shares[$dashboardId][] = new DashboardContactGroupShare(
                $dashboardsById[$dashboardId],
                $result['cg_id'],
                $result['cg_name'],
                $this->stringToRole($result['role'])
            );
        }

        return $shares;
    }

    /**
     * @param ContactInterface $contact
     * @param Dashboard ...$dashboards
     *
     * @throws RepositoryException
     * @throws AssertionFailedException
     * @throws \PDOException
     *
     * @return array<int, DashboardContactShare>
     */
    private function getContactShares(ContactInterface $contact, Dashboard ...$dashboards): array
    {
        if ([] === $dashboards) {
            return [];
        }

        $dashboardsById = [];
        foreach ($dashboards as $dashboard) {
            $dashboardsById[$dashboard->getId()] = $dashboard;
        }

        $select = <<<'SQL'
            SELECT
                dcr.`dashboard_id`,
                dcr.`role`
            FROM `:db`.`dashboard_contact_relation` dcr
            WHERE
                dcr.`contact_id` = :contact_id
                AND dcr.`dashboard_id` IN (:dashboard_ids)
            SQL;

        $concatenator = (new SqlConcatenator())
            ->defineSelect($select)
            ->storeBindValueMultiple(':dashboard_ids', array_keys($dashboardsById), \PDO::PARAM_INT)
            ->storeBindValue(':contact_id', $contact->getId(), \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $shares = [];
        foreach ($statement as $result) {
            /** @var array{
             *     dashboard_id: int,
             *     role: string
             * } $result
             */
            $dashboardId = $result['dashboard_id'];

            $shares[$dashboardId] = new DashboardContactShare(
                $dashboardsById[$dashboardId],
                $contact->getId(),
                $contact->getName(),
                $contact->getEmail(),
                $this->stringToRole($result['role'])
            );
        }

        return $shares;
    }

    /**
     * We want to make the conversion between a role and its string table representation here.
     *
     * @param string $role
     *
     * @throws RepositoryException
     *
     * @return DashboardSharingRole
     */
    private function stringToRole(string $role): DashboardSharingRole
    {
        try {
            return DashboardSharingRoleConverter::fromString($role);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw new RepositoryException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }
}
