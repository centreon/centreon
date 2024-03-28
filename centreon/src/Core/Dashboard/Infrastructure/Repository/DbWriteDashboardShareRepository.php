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

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;

class DbWriteDashboardShareRepository extends AbstractRepositoryDRB implements WriteDashboardShareRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function deleteContactShare(int $contactId, int $dashboardId): bool
    {
        $query = <<<'SQL'
            DELETE FROM
               `:db`.`dashboard_contact_relation`
            WHERE
                dashboard_id = :dashboard_id
                AND contact_id = :contact_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':contact_id', $contactId, \PDO::PARAM_INT);
        $statement->execute();

        return 0 !== $statement->rowCount();
    }

    public function deleteContactGroupShare(int $contactGroupId, int $dashboardId): bool
    {
        $query = <<<'SQL'
            DELETE FROM
               `:db`.`dashboard_contactgroup_relation`
            WHERE
                dashboard_id = :dashboard_id
                AND contactgroup_id = :contactgroup_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':contactgroup_id', $contactGroupId, \PDO::PARAM_INT);
        $statement->execute();

        return 0 !== $statement->rowCount();
    }

    public function updateContactShare(int $contactId, int $dashboardId, DashboardSharingRole $role): bool
    {
        $query = <<<'SQL'
            UPDATE `:db`.`dashboard_contact_relation`
            SET
                `role` = :contact_role
            WHERE
                dashboard_id = :dashboard_id
                AND contact_id = :contact_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':contact_id', $contactId, \PDO::PARAM_INT);
        $statement->bindValue(':contact_role', $this->roleToString($role), \PDO::PARAM_STR);
        $statement->execute();

        return 0 !== $statement->rowCount();
    }

    public function updateContactGroupShare(int $contactGroupId, int $dashboardId, DashboardSharingRole $role): bool
    {
        $query = <<<'SQL'
            UPDATE `:db`.`dashboard_contactgroup_relation`
            SET
                `role` = :contact_role
            WHERE
                dashboard_id = :dashboard_id
                AND contactgroup_id = :contactgroup_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':contactgroup_id', $contactGroupId, \PDO::PARAM_INT);
        $statement->bindValue(':contact_role', $this->roleToString($role), \PDO::PARAM_STR);
        $statement->execute();

        return 0 !== $statement->rowCount();
    }

    public function upsertShareWithContact(int $contactId, int $dashboardId, DashboardSharingRole $role): void
    {
        $query = <<<'SQL'
            INSERT INTO `:db`.`dashboard_contact_relation` (`dashboard_id`, `contact_id`, `role`)
            VALUES (:dashboard_id, :contact_id, :contact_role)
            ON DUPLICATE KEY UPDATE `role` = :contact_role
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':contact_id', $contactId, \PDO::PARAM_INT);
        $statement->bindValue(':contact_role', $this->roleToString($role), \PDO::PARAM_STR);
        $statement->execute();
    }

    public function upsertShareWithContactGroup(int $contactGroupId, int $dashboardId, DashboardSharingRole $role): void
    {
        $query = <<<'SQL'
            INSERT INTO `:db`.`dashboard_contactgroup_relation` (`dashboard_id`, `contactgroup_id`, `role`)
            VALUES (:dashboard_id, :contactgroup_id, :contact_role)
            ON DUPLICATE KEY UPDATE `role` = :contact_role
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->bindValue(':contactgroup_id', $contactGroupId, \PDO::PARAM_INT);
        $statement->bindValue(':contact_role', $this->roleToString($role), \PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteDashboardShares(int $dashboardId): void
    {
        $deleteContactSharesStatement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM dashboard_contact_relation WHERE dashboard_id = :dashboardId
                SQL
        ));
        $deleteContactSharesStatement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);
        $deleteContactSharesStatement->execute();

        $deleteContactGroupSharesStatement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM dashboard_contactgroup_relation WHERE dashboard_id = :dashboardId
                SQL
        ));
        $deleteContactGroupSharesStatement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);
        $deleteContactGroupSharesStatement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteDashboardSharesByContactGroupIds(int $dashboardId, array $contactGroupIds): void
    {
        $bind = [];
        foreach ($contactGroupIds as $key => $contactGroupId) {
            $bind[':contact_group' . $key] = $contactGroupId;
        }

        if ([] === $bind) {
            return;
        }

        $bindTokenAsString = implode(', ', array_keys($bind));

        $deleteContactGroupSharesStatement = $this->db->prepare($this->translateDbName(
            <<<SQL
                DELETE FROM dashboard_contactgroup_relation
                    WHERE dashboard_id = :dashboardId
                    AND contactgroup_id IN ({$bindTokenAsString})
                SQL
        ));
        $deleteContactGroupSharesStatement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);
        foreach ($bind as $token => $contactGroupId) {
            $deleteContactGroupSharesStatement->bindValue($token, $contactGroupId, \PDO::PARAM_INT);
        }
        $deleteContactGroupSharesStatement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteDashboardSharesByContactIds(int $dashboardId, array $contactIds): void
    {
        $bind = [];
        foreach ($contactIds as $key => $contactId) {
            $bind[':contact' . $key] = $contactId;
        }

        if ([] === $bind) {
            return;
        }

        $bindTokenAsString = implode(', ', array_keys($bind));

        $deleteContactGroupSharesStatement = $this->db->prepare($this->translateDbName(
            <<<SQL
                DELETE FROM dashboard_contact_relation
                    WHERE dashboard_id = :dashboardId
                    AND contact_id IN ({$bindTokenAsString})
                SQL
        ));
        $deleteContactGroupSharesStatement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);
        foreach ($bind as $token => $contactId) {
            $deleteContactGroupSharesStatement->bindValue($token, $contactId, \PDO::PARAM_INT);
        }
        $deleteContactGroupSharesStatement->execute();
    }

    /**
     * @inheritDoc
     */
    public function addDashboardContactShares(int $dashboardId, array $contactRoles): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    INSERT INTO dashboard_contact_relation (`dashboard_id`,`contact_id`,`role`)
                    VALUES (:dashboardId,:contactId,:role)
                SQL
        ));
        $statement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);
        foreach ($contactRoles as $contactRole) {
            $statement->bindValue(':contactId', $contactRole->getId());
            $statement->bindValue(
                ':role',
                DashboardSharingRoleConverter::toString($contactRole->getRole()),
                \PDO::PARAM_STR
            );
            $statement->execute();
        }
    }

    /**
     * @inheritDoc
     */
    public function addDashboardContactGroupShares(int $dashboardId, array $contactGroupRoles): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    INSERT INTO dashboard_contactgroup_relation (`dashboard_id`,`contactgroup_id`,`role`)
                    VALUES (:dashboardId,:contactGroupId,:role)
                SQL
        ));
        $statement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);
        foreach ($contactGroupRoles as $contactGroupRole) {
            $statement->bindValue(':contactGroupId', $contactGroupRole->getId());
            $statement->bindValue(
                ':role',
                DashboardSharingRoleConverter::toString($contactGroupRole->getRole()),
                \PDO::PARAM_STR
            );
            $statement->execute();
        }
    }

    /**
     * We want to make the conversion between a role and its string table representation here.
     *
     * @param DashboardSharingRole $role
     *
     * @return string
     */
    private function roleToString(DashboardSharingRole $role): string
    {
        return DashboardSharingRoleConverter::toString($role);
    }
}
