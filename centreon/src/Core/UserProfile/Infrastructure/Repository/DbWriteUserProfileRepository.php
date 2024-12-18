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

namespace Core\UserProfile\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\UserProfile\Application\Repository\WriteUserProfileRepositoryInterface;

final class DbWriteUserProfileRepository extends AbstractRepositoryRDB implements WriteUserProfileRepositoryInterface
{
    use SqlMultipleBindTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function addDefaultProfileForUser(ContactInterface $contact): int
    {
        $alreadyInTransaction = $this->db->inTransaction();

        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $statement = $this->db->prepare(
                $this->translateDbName(
                    <<<'SQL'
                        INSERT INTO `:db`.user_profile (contact_id) VALUES (:contactId)
                        SQL
                )
            );

            $statement->bindValue(':contactId', $contact->getId(), \PDO::PARAM_INT);
            $statement->execute();

            $profileId = (int) $this->db->lastInsertId();

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

            return $profileId;
        } catch (\Throwable $exception) {
            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function addDashboardAsFavorites(int $profileId, int $dashboardId): void
    {
        $alreadyInTransaction = $this->db->inTransaction();

        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $request = <<<'SQL'
                INSERT INTO `:db`.user_profile_favorite_dashboards (profile_id, dashboard_id) VALUES (:profileId, :dashboardId)
                SQL;

            $statement = $this->db->prepare($this->translateDbName($request));
            $statement->bindValue(':profileId', $profileId, \PDO::PARAM_INT);
            $statement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);

            $statement->execute();

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function removeDashboardFromFavorites(int $profileId, int $dashboardId): void
    {
        $request = <<<'SQL'
            DELETE FROM `:db`.user_profile_favorite_dashboards
            WHERE profile_id = :profileId
                AND dashboard_id = :dashboardId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':profileId', $profileId, \PDO::PARAM_INT);
        $statement->bindValue(':dashboardId', $dashboardId, \PDO::PARAM_INT);

        $statement->execute();
    }
}
