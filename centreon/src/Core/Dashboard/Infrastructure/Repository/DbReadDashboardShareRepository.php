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
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardContactGroupShare;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;
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
