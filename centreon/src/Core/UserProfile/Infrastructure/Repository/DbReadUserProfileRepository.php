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
use Core\UserProfile\Application\Repository\ReadUserProfileRepositoryInterface;
use Core\UserProfile\Domain\Model\UserProfile;

/**
 * @phpstan-type _UserProfileRecord array{
 *    id:int,
 *    contact_id:int,
 *    favorite_dashboards:string|null
 * }
 */
final class DbReadUserProfileRepository extends AbstractRepositoryRDB implements ReadUserProfileRepositoryInterface
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
    public function findByContact(ContactInterface $contact): ?UserProfile
    {
        $request = <<<'SQL'
                SELECT
                    `id`,
                    `contact_id`,
                    GROUP_CONCAT(DISTINCT user_profile_favorite_dashboards.dashboard_id) AS `favorite_dashboards`
                FROM `:db`.user_profile
                LEFT JOIN `:db`.user_profile_favorite_dashboards
                    ON user_profile_favorite_dashboards.profile_id = user_profile.id
                WHERE contact_id = :contactId
                GROUP BY contact_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':contactId', $contact->getId(), \PDO::PARAM_INT);
        $statement->execute();

        if (false !== ($record = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _UserProfileRecord $record */
            return $this->createUserProfileFromRecord($record);
        }

        return null;
    }

    /**
     * @param _UserProfileRecord $record
     * @return UserProfile
     */
    private function createUserProfileFromRecord(array $record): UserProfile
    {
        $profile = new UserProfile(
            id: (int) $record['id'],
            userId: (int) $record['contact_id'],
        );

        if ($record['favorite_dashboards'] !== null) {
            $favoriteDashboardIds = array_map(
                static fn (string $dashboardId) => (int) $dashboardId,
                explode(',', $record['favorite_dashboards'])
            );
            $profile->setFavoriteDashboards($favoriteDashboardIds);
        }

        return $profile;
    }
}
