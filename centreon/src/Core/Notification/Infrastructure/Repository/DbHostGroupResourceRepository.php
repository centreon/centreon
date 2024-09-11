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
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Converter\NotificationServiceEventConverter;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Domain\Model\ConfigurationResource;
use Core\Notification\Domain\Model\HostEvent;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Utility\SqlConcatenator;

class DbHostGroupResourceRepository extends AbstractRepositoryRDB implements NotificationResourceRepositoryInterface
{
    use LoggerTrait;
    use SqlMultipleBindTrait;
    private const RESOURCE_TYPE = NotificationResource::TYPE_HOST_GROUP;
    private const EVENT_ENUM = HostEvent::class;
    private const EVENT_ENUM_CONVERTER = NotificationHostEventConverter::class;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function supportResourceType(string $type): bool
    {
        return mb_strtolower($type) === self::RESOURCE_TYPE;
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
    public function eventEnumConverter(): string
    {
        return self::EVENT_ENUM_CONVERTER;
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
            'Check if resource IDs exist',
            ['resource_type' => self::RESOURCE_TYPE, 'resource_ids' => $resourceIds]
        );

        if ($resourceIds === []) {
            return [];
        }

        $concatenator = $this->getConcatenatorForExistRequest()
            ->appendWhere(
                <<<'SQL'
                    hg.hg_id IN (:resourceIds)
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
            'Check if resource IDs exist with accessGroups',
            [
                'resource_type' => self::RESOURCE_TYPE,
                'resource_ids' => $resourceIds,
                'access_groups' => $accessGroups,
            ]
        );

        if ([] === $accessGroups || [] === $resourceIds) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $concatenator = $this->getConcatenatorForExistRequest($accessGroupIds)
            ->appendWhere(
                <<<'SQL'
                    hg.hg_id IN (:resourceIds)
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

        [$hostgroupEvents, $includedServiceEvents] = $this->retrieveEvents($notificationId);

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
            (fn($data) => new ConfigurationResource($data['hg_id'], $data['hg_name'])),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );

        return new NotificationResource(
            self::RESOURCE_TYPE,
            self::EVENT_ENUM,
            $resources,
            NotificationHostEventConverter::fromBitFlags($hostgroupEvents),
            NotificationServiceEventConverter::fromBitFlags($includedServiceEvents),
        );
    }

    /**
     * @inheritDoc
     */
    public function findByNotificationIdAndAccessGroups(
        int $notificationId,
        array $accessGroups
    ): ?NotificationResource {
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

        [$hostgroupEvents, $includedServiceEvents] = $this->retrieveEvents($notificationId);

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
            (fn($data) => new ConfigurationResource($data['hg_id'], $data['hg_name'])),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );

        return new NotificationResource(
            self::RESOURCE_TYPE,
            self::EVENT_ENUM,
            $resources,
            NotificationHostEventConverter::fromBitFlags($hostgroupEvents),
            NotificationServiceEventConverter::fromBitFlags($includedServiceEvents),
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
                UPDATE `:db`.notification SET
                    hostgroup_events = :events,
                    included_service_events = :serviceEvents
                WHERE id = :notificationId
                SQL
        ));
        $statement->bindValue(
            ':events',
            (self::EVENT_ENUM_CONVERTER)::toBitFlags($resource->getEvents()),
            \PDO::PARAM_INT
        );
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->bindValue(
            ':serviceEvents',
            method_exists($resource, 'getServiceEvents')
                ? NotificationServiceEventConverter::toBitFlags($resource->getServiceEvents())
                : 0,
            \PDO::PARAM_INT
        );
        $statement->execute();

        $subQuery = [];
        $bindElem = [];
        foreach ($resource->getResources() as $key => $resourceElem) {
            $subQuery[] = "(:notificationId, :resource_{$key})";
            $bindElem[":resource_{$key}"] = $resourceElem->getId();
        }
        $statement = $this->db->prepare($this->translateDbName(
            'INSERT INTO `:db`.notification_hg_relation (notification_id, hg_id) VALUES ' . implode(', ', $subQuery)
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

    /**
     * @inheritDoc
     */
    public function countResourcesByNotificationIdsAndAccessGroups(
        array $notificationIds,
        array $accessGroups
    ): array {
        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        [$bindNotificationValues, $subNotificationQuery] = $this->createMultipleBindQuery($notificationIds, ':nid_');
        [$bindAccessGroupValues, $subAccessGroupQuery] = $this->createMultipleBindQuery($accessGroupIds, ':aid_');

        $statement = $this->db->prepare(
            $this->translateDbName(<<<SQL
                SELECT notification_id, COUNT(DISTINCT rel.hg_id)
                FROM `:db`.notification_hg_relation rel
                INNER JOIN `:db`.acl_resources_hg_relations arhr
                    ON rel.hg_id = arhr.hg_hg_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                WHERE ag.acl_group_id IN ({$subAccessGroupQuery})
                    AND notification_id IN ({$subNotificationQuery})
                GROUP BY notification_id
                SQL
            )
        );
        foreach ([...$bindNotificationValues, ...$bindAccessGroupValues] as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();

        $result = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $result ?: [];
    }

    /**
     * @inheritDoc
     */
    public function countResourcesByNotificationIds(array $notificationIds): array
    {
        [$bindNotificationValues, $subNotificationQuery] = $this->createMultipleBindQuery($notificationIds, ':id_');
        $statement = $this->db->prepare(
            $this->translateDbName(<<<SQL
                SELECT notification_id, COUNT(DISTINCT rel.hg_id)
                FROM `:db`.notification_hg_relation rel
                WHERE notification_id IN ({$subNotificationQuery})
                GROUP BY notification_id
                SQL
            )
        );
        foreach ($bindNotificationValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();

        $result = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $result ?: [];
    }

    /**
     * @inheritDoc
     */
    public function deleteByNotificationIdAndResourcesId(int $notificationId, array $resourcesIds): void
    {
        $resetEventStatement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.notification SET
                    hostgroup_events = 0,
                    included_service_events = 0
                WHERE id = :notificationId
                SQL
        ));
        $resetEventStatement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $resetEventStatement->execute();

        $bindValues = [];
        foreach ($resourcesIds as $resourceId) {
            $bindValues[':resource_id' . $resourceId] = $resourceId;
        }
        $hostGroupsIds = implode(', ', array_keys($bindValues));

        $deleteStatement = $this->db->prepare($this->translateDbName(
            <<<SQL
                    DELETE FROM `:db`.notification_hg_relation
                    WHERE hg_id IN ({$hostGroupsIds})
                    AND notification_id = :notificationId
                SQL
        ));
        foreach ($bindValues as $token => $resourceId) {
            $deleteStatement->bindValue($token, $resourceId, \PDO::PARAM_INT);
        }
        $deleteStatement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $deleteStatement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteAllByNotification(int $notificationId): void
    {
        $resetEventStatement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.notification SET
                    hostgroup_events = 0,
                    included_service_events = 0
                WHERE id = :notificationId
                SQL
        ));
        $resetEventStatement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $resetEventStatement->execute();

        $deleteStatement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    DELETE FROM `:db`.notification_hg_relation
                    WHERE notification_id = :notificationId
                SQL
        ));
        $deleteStatement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $deleteStatement->execute();
    }

    /**
     * @param int $notificationId
     *
     * @return int[]
     */
    private function retrieveEvents(int $notificationId): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    hostgroup_events,
                    included_service_events
                FROM `:db`.notification
                WHERE id = :notificationId
                SQL
        ));
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();
        /** @var array<string,int> */
        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        return $result ? [$result['hostgroup_events'], $result['included_service_events']] : [0, 0];
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
                        hg.hg_id
                    SQL
            )->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.`hostgroup` hg
                    SQL
            );

        if ([] !== $accessGroupIds) {
            $concatenator->appendJoins(
                <<<'SQL'
                    INNER JOIN `:db`.acl_resources_hg_relations arhr
                        ON hg.hg_id = arhr.hg_hg_id
                    INNER JOIN `:db`.acl_resources res
                        ON arhr.acl_res_id = res.acl_res_id
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
                    SELECT DISTINCT
                        rel.hg_id, hg.hg_name
                    SQL
            )->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.notification_hg_relation rel
                    SQL
            )->appendJoins(
                <<<'SQL'
                    INNER JOIN `:db`.hostgroup hg
                        ON rel.hg_id = hg.hg_id
                    SQL
            );

        if ([] !== $accessGroupIds) {
            $concatenator->appendJoins(
                <<<'SQL'
                    INNER JOIN `:db`.acl_resources_hg_relations arhr
                        ON rel.hg_id = arhr.hg_hg_id
                    INNER JOIN `:db`.acl_resources res
                        ON arhr.acl_res_id = res.acl_res_id
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
