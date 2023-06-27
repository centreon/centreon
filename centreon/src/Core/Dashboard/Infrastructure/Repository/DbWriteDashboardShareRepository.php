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

    public function createShareWithContact(int $contactId, int $dashboardId, DashboardSharingRole $role): void
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

    public function createShareWithContactGroup(int $contactGroupId, int $dashboardId, DashboardSharingRole $role): void
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
     * We want to make the conversion between a role and its string table representation here.
     *
     * @param \Core\Dashboard\Domain\Model\Role\DashboardSharingRole $role
     *
     * @return string
     */
    private function roleToString(DashboardSharingRole $role): string
    {
        return DashboardSharingRoleConverter::toString($role);
    }
}
