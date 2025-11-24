<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\ActivityLogging\Infrastructure\Dbal;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\ActivityLogId;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\Target;
use App\ActivityLogging\Domain\Aggregate\TargetId;
use App\ActivityLogging\Domain\Aggregate\TargetName;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Repository\ActivityLogRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{action_log_id: int, action_log_date: int, object_id: int, object_name: string, object_type: string, action_type: string, log_contact_id: int, field_name: string|null, field_value: string|null}
 */
final readonly class DbalActivityLogRepository extends DbalRepository implements ActivityLogRepository
{
    private const TABLE_NAME = 'log_action';
    private const DETAIL_TABLE_NAME = 'log_action_modification';
    private const TARGET_TYPE_VALUE_MAP = [
        TargetTypeEnum::ServiceCategory->value => 'servicecategories',
        TargetTypeEnum::Command->value => 'commands',
    ];
    private const ACTION_VALUE_MAP = [
        ActionEnum::Add->value => 'a',
        ActionEnum::Update->value => 'c',
        ActionEnum::Delete->value => 'd',
    ];

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private Connection $connection,
    ) {
    }

    public function add(ActivityLog $activityLog): void
    {
        $qb = $this->connection->createQueryBuilder();

        $targetType = self::TARGET_TYPE_VALUE_MAP[$activityLog->target->type->value];
        $action = self::ACTION_VALUE_MAP[$activityLog->action->value];

        $qb->insert(self::TABLE_NAME)
            ->values([
                'action_log_date' => ':performedAt',
                'object_id' => ':targetId',
                'object_name' => ':targetName',
                'object_type' => ':targetType',
                'action_type' => ':action',
                'log_contact_id' => ':actorId',
            ])
            ->setParameter('performedAt', $activityLog->performedAt->getTimestamp())
            ->setParameter('targetId', $activityLog->target->id->value)
            ->setParameter('targetName', $activityLog->target->name->value)
            ->setParameter('targetType', $targetType)
            ->setParameter('action', $action)
            ->setParameter('actorId', $activityLog->actor->id->value)
            ->executeStatement();

        $id = (int) $this->connection->lastInsertId();

        if ($id === 0) {
            throw new \RuntimeException(sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
        }

        $this->setId($activityLog, new ActivityLogId($id));

        foreach ($activityLog->details as $name => $value) {
            $qb->insert(self::DETAIL_TABLE_NAME)
                ->values([
                    'action_log_id' => ':logId',
                    'field_name' => ':name',
                    'field_value' => ':value',
                ])
                ->setParameter('logId', $id)
                ->setParameter('name', $name)
                ->setParameter('value', $value)
                ->executeStatement();
        }
    }

    /**
     * @param array<ActivityLog> $activityLogs
     */
    public function addMultiple(array $activityLogs): void
    {
        if ($activityLogs === []) {
            return;
        }

        $columns = [
            'action_log_date',
            'object_id',
            'object_name',
            'object_type',
            'action_type',
            'log_contact_id',
        ];

        $targetType = self::TARGET_TYPE_VALUE_MAP[$activityLogs[0]->target->type->value];
        $action = self::ACTION_VALUE_MAP[$activityLogs[0]->action->value];
        $values = [];
        $parameters = [];

        foreach ($activityLogs as $index => $activityLog) {
            $values[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $parameters[] = $activityLog->performedAt->getTimestamp();
            $parameters[] = $activityLog->target->id->value;
            $parameters[] = $activityLog->target->name->value;
            $parameters[] = $targetType;
            $parameters[] = $action;
            $parameters[] = $activityLog->actor->id->value;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            self::TABLE_NAME,
            implode(', ', $columns),
            implode(', ', $values)
        );

        $this->connection->executeStatement($sql, $parameters);

        $insertedIds = $this->findLastInsertIdsByObjectsNames(array_map(
            fn (ActivityLog $log): string => $log->target->name->value,
            $activityLogs
        ));

        foreach ($insertedIds as $key => $row) {
            $this->setId($activityLogs[$key], new ActivityLogId((int) $row['action_log_id']));
        }

        $detailsToInsert = [];
        foreach ($activityLogs as $activityLog) {
            foreach ($activityLog->details as $name => $value) {
                $detailsToInsert[] = [
                    'action_log_id' => $activityLog->id()->value,
                    'field_name' => $name,
                    'field_value' => $value,
                ];
            }
        }

        $this->addMultipleDetails($detailsToInsert);
    }

    private function addMultipleDetails(array $detailsToInsert): void
    {
        if ($detailsToInsert !== []) {
            $detailColumns = ['action_log_id', 'field_name', 'field_value'];
            $detailValues = [];
            $detailParameters = [];

            foreach ($detailsToInsert as $detail) {
                $detailValues[] = '(' . implode(',', array_fill(0, count($detailColumns), '?')) . ')';
                $detailParameters[] = $detail['action_log_id'];
                $detailParameters[] = $detail['field_name'];
                $detailParameters[] = $detail['field_value'];
            }

            $detailSql = sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                self::DETAIL_TABLE_NAME,
                implode(', ', $detailColumns),
                implode(', ', $detailValues)
            );

            $this->connection->executeStatement($detailSql, $detailParameters);
        }
    }

    private function findLastInsertIdsByObjectsNames(array $objectNames): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('action_log_id')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->in('object_name', ':objectNames'))
            ->setParameter('objectNames', $objectNames, ArrayParameterType::STRING);

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return $rows;
    }

    public function find(ActivityLogId $id): ?ActivityLog
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('l.action_log_id', 'l.action_log_date', 'l.object_id', 'l.object_name', 'l.object_type', 'l.action_type', 'l.log_contact_id', 'd.field_name', 'd.field_value')
            ->from(self::TABLE_NAME, 'l')
            ->leftJoin('l', self::DETAIL_TABLE_NAME, 'd', 'l.action_log_id = d.action_log_id')
            ->where('l.action_log_id = :id')
            ->setParameter('id', $id->value);

        /** @var list<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        if ($rows === []) {
            return null;
        }

        return $this->createActivityLog($rows);
    }

    public function count(): int
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('count(1)')
            ->from(self::TABLE_NAME)
            ->setMaxResults(1);

        /** @var int|false $count */
        $count = $qb->executeQuery()->fetchOne();

        return $count ?: 0;
    }

    /**
     * @param non-empty-array<RowTypeAlias> $rows
     */
    private function createActivityLog(array $rows): ActivityLog
    {
        $details = [];
        foreach ($rows as $row) {
            if ($row['field_name'] !== null && $row['field_value'] !== null) {
                $details[$row['field_name']] = $row['field_value'];
            }
        }

        $row = $rows[0];

        $targetType = array_flip(self::TARGET_TYPE_VALUE_MAP)[$row['object_type']] ?? null;
        if ($targetType === null) {
            throw new \RuntimeException(sprintf('There is no "%s" associated to "%s", try to updated %s::TARGET_TYPE_VALUE_MAP.', TargetTypeEnum::class, $row['object_type'], self::class));
        }

        $action = array_flip(self::ACTION_VALUE_MAP)[$row['action_type']] ?? null;
        if ($action === null) {
            throw new \RuntimeException(sprintf('There is no "%s" associated to "%s", try to updated %s::ACTION_VALUE_MAP.', ActionEnum::class, $row['action_type'], self::class));
        }

        return new ActivityLog(
            id: new ActivityLogId($row['action_log_id']),
            action: ActionEnum::from($action),
            actor: new Actor(
                id: new ActorId($row['log_contact_id']),
            ),
            target: new Target(
                id: new TargetId($row['object_id']),
                name: new TargetName($row['object_name']),
                type: TargetTypeEnum::from($targetType),
            ),
            performedAt: (new \DateTimeImmutable('@' . $row['action_log_date']))->setTimezone(new \DateTimeZone(date_default_timezone_get())),
            details: $details,
        );
    }
}
