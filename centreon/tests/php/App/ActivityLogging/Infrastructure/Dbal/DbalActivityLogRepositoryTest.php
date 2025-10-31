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

namespace Tests\App\ActivityLogging\Infrastructure\Doctrine;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\ActivityLogId;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\Target;
use App\ActivityLogging\Domain\Aggregate\TargetId;
use App\ActivityLogging\Domain\Aggregate\TargetName;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Infrastructure\Dbal\DbalActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalActivityLogRepositoryTest extends KernelTestCase
{
    private DbalActivityLogRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalActivityLogRepository $repository */
        $repository = self::getContainer()->get(DbalActivityLogRepository::class);

        $this->repository = $repository;
    }

    public function testAdd(): void
    {
        self::assertSame(0, $this->repository->count());

        $activityLog = new ActivityLog(
            id: null,
            action: ActionEnum::Add,
            actor: new Actor(
                id: new ActorId(1),
            ),
            target: new Target(
                id: new TargetId(1),
                name: new TargetName('foo'),
                type: TargetTypeEnum::ServiceCategory,
            ),
            performedAt: (new \DateTimeImmutable())->setTime(0, 0),
            details: [
                'foo' => 'foo_value',
                'bar' => 'bar_value',
            ],
        );

        $this->repository->add($activityLog);

        self::assertEquals($activityLog, $this->repository->find($activityLog->id()));
    }

    public function testFind(): void
    {
        $activityLog = new ActivityLog(
            id: null,
            action: ActionEnum::Add,
            actor: new Actor(
                id: new ActorId(1),
            ),
            target: new Target(
                id: new TargetId(1),
                name: new TargetName('foo'),
                type: TargetTypeEnum::ServiceCategory,
            ),
            performedAt: (new \DateTimeImmutable())->setTime(0, 0),
            details: [],
        );

        $this->repository->add($activityLog);

        self::assertNull($this->repository->find(new ActivityLogId(mt_rand())));
        self::assertNotNull($this->repository->find($activityLog->id()));
    }
}
