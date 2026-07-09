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

namespace App\Monitoring\Infrastructure\Dbal;

use App\Monitoring\Domain\Aggregate\Notification\Notification;
use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Exception\NotificationNotFoundException;
use App\Monitoring\Domain\Repository\NotificationRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-import-type RowTypeAlias from DbalTimePeriodRepository as TimePeriodRowTypeAlias
 * @phpstan-type RowTypeAlias = array{
 *    notification_id: int,
 *    notification_name: string,
 *    notification_is_activated: int,
 * }
 */
final readonly class DbalNotificationRepository extends DbalRepository implements NotificationRepository
{
    public const string TABLE_NAME = 'notification';

    /**
     * @param Connection $connection
     * @param TransformerInterface<RowTypeAlias, Notification> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalNotificationTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function add(Notification $notification): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->insert(self::TABLE_NAME)->values([
            'name' => ':name',
            'is_activated' => ':isActivated',
            'timeperiod_id' => ':timeperiodId',
        ])->setParameters([
            'name' => $notification->name->value,
            'isActivated' => $notification->isActivated,
            'timeperiodId' => $notification->timePeriod->id()->value,
        ])->executeStatement();
        $notificationId = (int) $this->connection->lastInsertId();

        if ($notificationId === 0) {
            throw new \RuntimeException(sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
        }

        $this->setId($notification, new NotificationId($notificationId));
    }

    public function get(NotificationId $id): Notification
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns('n'), ...DbalTimePeriodRepository::getSelectColumns('t'))
            ->from(self::TABLE_NAME, 'n')
            ->innerJoin(
                'n',
                DbalTimePeriodRepository::TABLE_NAME,
                't',
                'n.timeperiod_id = t.tp_id',
            )->where('n.id = :id')
            ->setParameter('id', $id->value);

        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {
            throw new NotificationNotFoundException(
                ['id' => $id->value],
                sprintf('Notification #%d not found', $id->value)
            );
        }

        return $this->transformer->transform($row);
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'n'): array
    {
        return [
            "{$alias}.id AS notification_id",
            "{$alias}.name AS notification_name",
            "{$alias}.is_activated AS notification_is_activated",
        ];
    }
}
