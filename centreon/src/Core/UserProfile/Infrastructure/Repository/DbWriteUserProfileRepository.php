<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\UserProfile\Application\Repository\WriteUserProfileRepositoryInterface;
use PDO;
use PDOException;

final class DbWriteUserProfileRepository extends AbstractRepositoryRDB implements WriteUserProfileRepositoryInterface
{
    use SqlMultipleBindTrait;
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @param ContactInterface $contact
     *
     * @return int
     *
     * @throws RepositoryException
     */
    public function addDefaultProfileForUser(ContactInterface $contact): int
    {
        try {
            $alreadyInTransaction = $this->db->inTransaction();

            if (! $alreadyInTransaction) {
                $this->db->beginTransaction();
            }

            $query = <<<SQL
                INSERT INTO `:db`.user_profile (contact_id) VALUES (:contactId)
                SQL;

            $statement = $this->db->prepare($this->translateDbName($query));

            $statement->bindValue(':contactId', $contact->getId(), PDO::PARAM_INT);
            $statement->execute();

            $profileId = (int) $this->db->lastInsertId();

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

            return $profileId;
        } catch (PDOException $e) {
            if (! $alreadyInTransaction) {
                try {
                    $this->db->rollBack();
                } catch (PDOException $rollbackException) {
                    $errorMessage = "Error while rolling back transaction: {$rollbackException->getMessage()}";
                    $this->error(
                        $errorMessage,
                        [
                            'contact_id' => $contact->getId(),
                            'query' => $query,
                            'exception' => [
                                'message' => $rollbackException->getMessage(),
                                'pdo_code' => $rollbackException->getCode(),
                                'pdo_info' => $rollbackException->errorInfo,
                                'trace' => $rollbackException->getTraceAsString()
                            ]
                        ]
                    );
                    throw new RepositoryException($errorMessage, exception: $e);
                }
            }
            $errorMessage = "Error while creating default profile for user: {$e->getMessage()}";
            $this->error(
                $errorMessage,
                [
                    'contact_id' => $contact->getId(),
                    'query' => $query,
                    'exception' => [
                        'message' => $e->getMessage(),
                        'pdo_code' => $e->getCode(),
                        'pdo_info' => $e->errorInfo,
                        'trace' => $e->getTraceAsString()
                    ]
                ]
            );
            throw new RepositoryException($errorMessage, exception: $e);
        }
    }

    /**
     * @param int $profileId
     * @param int $dashboardId
     *
     * @return void
     * @throws RepositoryException
     */
    public function addDashboardAsFavorites(int $profileId, int $dashboardId): void
    {
        try {
            $alreadyInTransaction = $this->db->inTransaction();

            if (! $alreadyInTransaction) {
                $this->db->beginTransaction();
            }

            $query = <<<SQL
                INSERT INTO `:db`.user_profile_favorite_dashboards (profile_id, dashboard_id) VALUES (:profileId, :dashboardId)
                SQL;

            $statement = $this->db->prepare($this->translateDbName($query));
            $statement->bindValue(':profileId', $profileId, PDO::PARAM_INT);
            $statement->bindValue(':dashboardId', $dashboardId, PDO::PARAM_INT);

            $statement->execute();

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }
        } catch (PDOException $e) {
            if (! $alreadyInTransaction) {
                try {
                    $this->db->rollBack();
                } catch (PDOException $rollbackException) {
                    $errorMessage = "Error while rolling back transaction: {$rollbackException->getMessage()}";
                    $this->error(
                        $errorMessage,
                        [
                            'profile_id' => $profileId,
                            'dashboard_id' => $dashboardId,
                            'query' => $query,
                            'exception' => [
                                'message' => $rollbackException->getMessage(),
                                'pdo_code' => $rollbackException->getCode(),
                                'pdo_info' => $rollbackException->errorInfo,
                                'trace' => $rollbackException->getTraceAsString()
                            ]
                        ]
                    );
                    throw new RepositoryException($errorMessage, exception: $e);
                }
            }
            $errorMessage = "Error while creating default profile for user: {$e->getMessage()}";
            $this->error(
                $errorMessage,
                [
                    'profile_id' => $profileId,
                    'dashboard_id' => $dashboardId,
                    'query' => $query,
                    'exception' => [
                        'message' => $e->getMessage(),
                        'pdo_code' => $e->getCode(),
                        'pdo_info' => $e->errorInfo,
                        'trace' => $e->getTraceAsString()
                    ]
                ]
            );
            throw new RepositoryException($errorMessage, exception: $e);
        }
    }

    /**
     * @param int $profileId
     * @param int $dashboardId
     *
     * @return void
     * @throws RepositoryException
     */
    public function removeDashboardFromFavorites(int $profileId, int $dashboardId): void
    {
        try {
            $query = <<<SQL
                DELETE FROM `:db`.user_profile_favorite_dashboards
                WHERE profile_id = :profileId
                    AND dashboard_id = :dashboardId
                SQL;

            $statement = $this->db->prepare($this->translateDbName($query));
            $statement->bindValue(':profileId', $profileId, PDO::PARAM_INT);
            $statement->bindValue(':dashboardId', $dashboardId, PDO::PARAM_INT);

            $statement->execute();
        } catch (PDOException $e) {
            $message = "Error while removing dashboard from user favorite dashboards : {$e->getMessage()}";
            $this->error($message, [
                'profile_id' => $profileId,
                'dashboard_id' => $dashboardId,
                'query' => $query,
                'exception' => [
                    'message' => $e->getMessage(),
                    'pdo_code' => $e->getCode(),
                    'pdo_info' => $e->errorInfo,
                    'trace' => $e->getTraceAsString()
                ],
            ]);
            throw new RepositoryException($message, previous: $e);
        }
    }
}
