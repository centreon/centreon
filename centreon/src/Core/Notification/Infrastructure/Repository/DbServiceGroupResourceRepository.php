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

namespace Core\Notification\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Domain\NotificationServiceEvent;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Domain\Model\NotificationGenericObject;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Utility\SqlConcatenator;

class DbServiceGroupResourceRepository extends AbstractRepositoryRDB implements NotificationResourceRepositoryInterface
{
    use LoggerTrait;
    private const RESOURCE_TYPE = 'servicegroup';
    private const EVENT_ENUM = NotificationServiceEvent::class;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function supportResourceType(string $resourceType): bool
    {
        return mb_strtolower($resourceType) === self::RESOURCE_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function eventEnum(): string
    {
        return self::EVENT_ENUM;
    }

    /**
     * @inheritDoc
     */
    public function resourceType(): string
    {
        return self::RESOURCE_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function exist(array $resourceIds): array
    {
        $this->info(
            'Check if resource ids exist with accessGroups',
            ['resource_type' => self::RESOURCE_TYPE, 'resource_ids' => $resourceIds]
        );

        if ($resourceIds === []) {
            return [];
        }

        $concatenator = $this->getConcatenatorForExistRequest()
            ->appendWhere(
                <<<'SQL'
                    sg.sg_id IN (:resourceIds)
                    SQL
            )->storeBindValueMultiple(':resourceIds', $resourceIds, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * @inheritDoc
     */
    public function existByAccessGroups(array $resourceIds, array $accessGroups): array
    {
        $this->info(
            'Check if resource ids exist with accessGroups',
            [
                'resource_type' => self::RESOURCE_TYPE,
                'resource_ids' => $resourceIds,
                'access_groups' => $accessGroups,
            ]
        );

        if ($resourceIds === []) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $concatenator = $this->getConcatenatorForExistRequest($accessGroupIds)
            ->appendWhere(
                <<<'SQL'
                    sg.sg_id IN (:resourceIds)
                    SQL
            )->storeBindValueMultiple(':resourceIds', $resourceIds, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * @inheritDoc
     */
    public function findByNotificationId(int $notificationId): ?NotificationResource
    {
        $this->info(
            'Find resource with accessGroups',
            [
                'resource_type' => self::RESOURCE_TYPE,
                'notification_id' => $notificationId,
            ]
        );

        $eventResults = $this->retrieveEvents($notificationId);
        if ($eventResults === null) {
            return null;
        }

        $concatenator = $this->getConcatenatorForFindRequest()
            ->appendWhere(
                <<<'SQL'
                    WHERE notification_id = :notificationId
                    SQL
            )->storeBindValue(':notificationId', $notificationId, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();
        $resources = array_map(
            (fn($data) => new NotificationGenericObject($data['sg_id'], $data['sg_name'])),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );

        return new NotificationResource(
            self::RESOURCE_TYPE,
            self::EVENT_ENUM,
            $resources,
            (self::EVENT_ENUM)::fromBitmask($eventResults),
        );
    }

    /**
     * @inheritDoc
     */
    public function findByNotificationIdAndAccessGroups(
        int $notificationId,
        array $accessGroups
    ): ?NotificationResource
    {
        if ([] === $accessGroups) {
            return null;
        }

        $this->info(
            'Find resource with accessGroups',
            [
                'resource_type' => self::RESOURCE_TYPE,
                'notification_id' => $notificationId,
                'access_group' => $accessGroups,
            ]
        );

        $eventResults = $this->retrieveEvents($notificationId);
        if ($eventResults === null) {
            return null;
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );
        $concatenator = $this->getConcatenatorForFindRequest($accessGroupIds)
            ->appendWhere(
                <<<'SQL'
                    WHERE notification_id = :notificationId
                    SQL
            )->storeBindValue(':notificationId', $notificationId, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $resources = array_map(
            (fn($data) => new NotificationGenericObject($data['sg_id'], $data['sg_name'])),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );

        return new NotificationResource(
            self::RESOURCE_TYPE,
            self::EVENT_ENUM,
            $resources,
            (self::EVENT_ENUM)::fromBitmask($eventResults),
        );
    }

    /**
     * @inheritDoc
     */
    public function add(int $notificationId, NotificationResource $resource): void
    {
        $this->info(
            'Add resource',
            [
                'resource_type' => self::RESOURCE_TYPE,
                'notification_id' => $notificationId,
                'resource' => $resource,
            ]
        );

        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.notification
                    SET servicegroup_events = :events
                WHERE id = :notificationId
                SQL
        ));
        $statement->bindValue(':events', (self::EVENT_ENUM)::toBitmask($resource->getEvents()), \PDO::PARAM_INT);
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();

        $subQuery = [];
        $bindElem = [];
        foreach ($resource->getResources() as $key => $resourceElem) {
            $subQuery[] = "(:notificationId, :resource_{$key})";
            $bindElem[":resource_{$key}"] = $resourceElem->getId();
        }
        $statement = $this->db->prepare($this->translateDbName(
            'INSERT INTO `:db`.notification_sg_relation (notification_id, sg_id) VALUES ' . implode(', ', $subQuery)
        ));
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        foreach ($bindElem as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();

        if (! $alreadyInTransaction) {
            $this->db->commit();
        }
    }

    private function retrieveEvents(int $notificationId): ?int
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT servicegroup_events
                FROM `:db`.notification
                WHERE id = :notificationId
                SQL
        ));
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetchColumn();

        return $result === false ? null : (int) $result;
    }

    /**
     * @param int[] $accessGroupIds
     *
     * @return SqlConcatenator
     */
    private function getConcatenatorForExistRequest(array $accessGroupIds = []): SqlConcatenator
    {
        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                    SELECT
                        sg.sg_id
                    SQL
            )
            ->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.`servicegroup` sg
                    SQL
            );

        if ([] !== $accessGroupIds) {
            $concatenator->appendJoins(
                <<<'SQL'
                    INNER JOIN `:db`.acl_resources_sg_relations arsr
                        ON sg.sg_id = arsr.sg_id
                    INNER JOIN `:db`.acl_resources res
                        ON arsr.acl_res_id = res.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON res.acl_res_id = argr.acl_res_id
                    INNER JOIN `:db`.acl_groups ag
                        ON argr.acl_group_id = ag.acl_group_id
                    SQL
            )->appendWhere(
                <<<'SQL'
                    WHERE ag.acl_group_id IN (:accessGroupIds)
                    SQL
            )
                ->storeBindValueMultiple(':accessGroupIds', $accessGroupIds, \PDO::PARAM_INT);
        }

        return $concatenator;
    }

    /**
     * @param int[] $accessGroupIds
     *
     * @return SqlConcatenator
     */
    private function getConcatenatorForFindRequest(array $accessGroupIds = []): SqlConcatenator
    {
        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                    SELECT
                        rel.sg_id, sg.sg_name
                    SQL
            )->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.notification_sg_relation rel
                    SQL
            )->appendJoins(
                <<<'SQL'
                    INNER JOIN `:db`.servicegroup sg
                        ON sg.sg_id = rel.sg_id
                    SQL
            );

        if ([] !== $accessGroupIds) {
            $concatenator->appendJoins(
                <<<'SQL'
                    INNER JOIN `:db`.acl_resources_sg_relations arsr
                        ON rel.sg_id = arsr.sg_id
                    INNER JOIN `:db`.acl_resources res
                        ON arsr.acl_res_id = res.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON res.acl_res_id = argr.acl_res_id
                    INNER JOIN `:db`.acl_groups ag
                        ON argr.acl_group_id = ag.acl_group_id
                    SQL
            )->appendWhere(
                <<<'SQL'
                    WHERE ag.acl_group_id IN (:accessGroupIds)
                    SQL
            )->storeBindValueMultiple(':accessGroupIds', $accessGroupIds, \PDO::PARAM_INT);
        }

        return $concatenator;
    }
}
