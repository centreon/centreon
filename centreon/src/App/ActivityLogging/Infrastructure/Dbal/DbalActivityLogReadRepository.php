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

use App\ActivityLogging\Domain\ReadModel\ActivityLogDetails;
use App\ActivityLogging\Domain\ReadModel\ActivityLogEntry;
use App\ActivityLogging\Domain\ReadModel\ActivityLogPage;
use App\ActivityLogging\Domain\Repository\ActivityLogReadRepository;
use App\ActivityLogging\Domain\Repository\Criteria\ActivityLogCriteria;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Pagination;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type EntryRowTypeAlias = array{
 *     action_log_id: int|string,
 *     action_log_date: int|string,
 *     object_type: string,
 *     object_id: int|string,
 *     object_name: string|null,
 *     action_type: string,
 *     log_contact_id: int|string|null,
 *     has_changes: int|string
 * }
 */
final readonly class DbalActivityLogReadRepository implements ActivityLogReadRepository
{
    private const TABLE_NAME = 'log_action';
    private const DETAIL_TABLE_NAME = 'log_action_modification';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private Connection $connection,
    ) {
    }

    public function findAll(ActivityLogCriteria $criteria): Collection|ActivityLogPage
    {
        $qb = $this->createEntryQueryBuilder();
        $this->applyCriteria($qb, $criteria);
        // The id breaks ties between logs of the same second, so the pages stay stable
        $direction = $criteria->isOldestFirst() ? 'ASC' : 'DESC';
        $qb->orderBy('l.action_log_date', $direction)
            ->addOrderBy('l.action_log_id', $direction);

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof Pagination) {
            return new Collection($this->fetchEntries($qb), ActivityLogEntry::class);
        }

        // No COUNT: one more log than asked tells whether another page follows
        $qb->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->itemsPerPage + 1);
        $entries = $this->fetchEntries($qb);

        return new ActivityLogPage(
            entries: array_slice($entries, 0, $pagination->itemsPerPage),
            page: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
            hasNextPage: count($entries) > $pagination->itemsPerPage,
        );
    }

    public function findEntry(int $id): ?ActivityLogEntry
    {
        $qb = $this->createEntryQueryBuilder()
            ->andWhere('l.action_log_id = :id')
            ->setParameter('id', $id, ParameterType::INTEGER);

        return $this->fetchEntries($qb)[0] ?? null;
    }

    public function findTargetHistoryUntil(ActivityLogEntry $entry): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('l.action_log_id', 'm.field_name', 'm.field_value')
            ->from(self::TABLE_NAME, 'l')
            ->leftJoin('l', self::DETAIL_TABLE_NAME, 'm', 'm.action_log_id = l.action_log_id')
            ->where('l.object_id = :targetId')
            ->andWhere('l.object_type IN (:targetTypes)')
            ->andWhere($qb->expr()->or(
                'l.action_log_date < :performedAt',
                $qb->expr()->and('l.action_log_date = :performedAt', 'l.action_log_id <= :id'),
            ))
            ->orderBy('l.action_log_date', 'ASC')
            ->addOrderBy('l.action_log_id', 'ASC')
            ->setParameter('targetId', $entry->targetId, ParameterType::INTEGER)
            ->setParameter('targetTypes', LogActionTokenMapper::targetTypeToTokens($entry->targetType), ArrayParameterType::STRING)
            ->setParameter('performedAt', $entry->performedAt->getTimestamp(), ParameterType::INTEGER)
            ->setParameter('id', $entry->id, ParameterType::INTEGER);

        /** @var list<array{action_log_id: int|string, field_name: string|null, field_value: string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        /** @var array<int, list<array{name: string, value: string}>> $fieldsByLog */
        $fieldsByLog = [];
        foreach ($rows as $row) {
            $activityLogId = (int) $row['action_log_id'];
            $fieldsByLog[$activityLogId] ??= [];
            if ($row['field_name'] !== null) {
                $fieldsByLog[$activityLogId][] = ['name' => $row['field_name'], 'value' => $row['field_value'] ?? ''];
            }
        }

        $history = [];
        foreach ($fieldsByLog as $activityLogId => $fields) {
            $history[] = new ActivityLogDetails($activityLogId, $fields);
        }

        return $history;
    }

    public function findActorIds(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('DISTINCT l.log_contact_id')
            ->from(self::TABLE_NAME, 'l')
            ->where($qb->expr()->isNotNull('l.log_contact_id'))
            ->andWhere('l.log_contact_id > 0');

        return array_map(static fn (mixed $actorId): int => is_numeric($actorId) ? (int) $actorId : 0, $qb->executeQuery()->fetchFirstColumn());
    }

    /**
     * Same decoding as the legacy viewLogs.php: names were stored HTML-encoded, slash-escaped,
     * with "/" and "\" replaced by placeholders.
     */
    public static function decodeName(string $storedName): string
    {
        $name = stripslashes(html_entity_decode($storedName, \ENT_QUOTES, 'UTF-8'));

        return str_replace(['#S#', '#BS#'], ['/', '\\'], $name);
    }

    private function createEntryQueryBuilder(): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            'l.action_log_id',
            'l.action_log_date',
            'l.object_type',
            'l.object_id',
            'l.object_name',
            'l.action_type',
            'l.log_contact_id',
            sprintf('EXISTS (SELECT 1 FROM %s m WHERE m.action_log_id = l.action_log_id) AS has_changes', self::DETAIL_TABLE_NAME),
        )
            ->from(self::TABLE_NAME, 'l')
            // legacy behaviour: logs without target are not displayed
            ->where('l.object_id <> 0')
            // keep the total count consistent with the returned items: tokens without domain counterpart are left out
            ->andWhere('l.object_type IN (:knownTargetTypes)')
            ->andWhere('l.action_type IN (:knownActions)')
            ->setParameter('knownTargetTypes', LogActionTokenMapper::allTargetTypeTokens(), ArrayParameterType::STRING)
            ->setParameter('knownActions', LogActionTokenMapper::allActionTokens(), ArrayParameterType::STRING);

        return $qb;
    }

    private function applyCriteria(QueryBuilder $qb, ActivityLogCriteria $criteria): void
    {
        if (($targetName = $criteria->getTargetName()) !== null) {
            $qb->andWhere('l.object_name LIKE :targetName')
                ->setParameter('targetName', '%' . addcslashes($targetName, '\\%_') . '%');
        }

        if (($actorIds = $criteria->getActorIds()) !== []) {
            $qb->andWhere('l.log_contact_id IN (:actorIds)')
                ->setParameter('actorIds', $actorIds, ArrayParameterType::INTEGER);
        }

        if (($targetTypes = $criteria->getTargetTypes()) !== []) {
            $tokens = array_merge(...array_map(LogActionTokenMapper::targetTypeToTokens(...), $targetTypes));
            $qb->andWhere('l.object_type IN (:targetTypes)')
                ->setParameter('targetTypes', $tokens, ArrayParameterType::STRING);
        }

        if (($targetId = $criteria->getTargetId()) !== null) {
            $qb->andWhere('l.object_id = :targetId')
                ->setParameter('targetId', $targetId, ParameterType::INTEGER);
        }

        if (($actions = $criteria->getActions()) !== []) {
            $qb->andWhere('l.action_type IN (:actions)')
                ->setParameter('actions', array_map(LogActionTokenMapper::actionToToken(...), $actions), ArrayParameterType::STRING);
        }

        if (($performedFrom = $criteria->getPerformedFrom()) !== null) {
            $qb->andWhere('l.action_log_date >= :performedFrom')
                ->setParameter('performedFrom', $performedFrom->getTimestamp(), ParameterType::INTEGER);
        }

        if (($performedTo = $criteria->getPerformedTo()) !== null) {
            $qb->andWhere('l.action_log_date <= :performedTo')
                ->setParameter('performedTo', $performedTo->getTimestamp(), ParameterType::INTEGER);
        }
    }

    /**
     * @return list<ActivityLogEntry>
     */
    private function fetchEntries(QueryBuilder $qb): array
    {
        /** @var list<EntryRowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $entries = [];
        foreach ($rows as $row) {
            $targetType = LogActionTokenMapper::tokenToTargetType($row['object_type']);
            $action = LogActionTokenMapper::tokenToAction($row['action_type']);
            if ($targetType === null || $action === null) {
                continue;
            }

            $actorId = $row['log_contact_id'] !== null ? (int) $row['log_contact_id'] : null;

            $entries[] = new ActivityLogEntry(
                id: (int) $row['action_log_id'],
                performedAt: (new \DateTimeImmutable('@' . $row['action_log_date']))->setTimezone(new \DateTimeZone(date_default_timezone_get())),
                action: $action,
                targetId: (int) $row['object_id'],
                targetType: $targetType,
                targetName: self::decodeName($row['object_name'] ?? ''),
                actorId: $actorId !== null && $actorId > 0 ? $actorId : null,
                hasChanges: (bool) $row['has_changes'],
            );
        }

        return $entries;
    }
}
