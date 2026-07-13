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

namespace Tests\App\Monitoring\Infrastructure\Dbal;

use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Infrastructure\Dbal\Notification\DbalNotificationRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalNotificationRepositoryTest extends KernelTestCase
{
    private int $newNotificationId = 9999;
    private DbalNotificationRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalNotificationRepository $repository */
        $repository = self::getContainer()->get(DbalNotificationRepository::class);
        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert(
            DbalNotificationRepository::TABLE_NAME,
            [
                'id' => $this->newNotificationId,
                'name' => 'notif1',
                'is_activated' => true,
                'timeperiod_id' => 1,
            ],
        );
    }

    #[Group('wip')]
    public function testItGet(): void
    {
        $notification = $this->repository->get(new NotificationId($this->newNotificationId));
        self::assertEquals($this->newNotificationId, $notification->id()->value);
        self::assertEquals('notif1', $notification->name->value);
        self::assertTrue($notification->isActivated);
        self::assertEquals(1, $notification->timePeriod->id()->value);
    }
}
