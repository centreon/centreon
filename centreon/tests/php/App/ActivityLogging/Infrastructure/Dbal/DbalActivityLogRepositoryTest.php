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

    public function testCount(): void
    {
        $initialCount = $this->repository->count();

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

        $newCount = $this->repository->count();

        self::assertSame($initialCount + 1, $newCount);
    }

    public function testAddMultipleActivityLogs(): void
    {
        $initialCount = $this->repository->count();

        $activityLog1 = new ActivityLog(
            id: null,
            action: ActionEnum::Add,
            actor: new Actor(
                id: new ActorId(1),
            ),
            target: new Target(
                id: new TargetId(1),
                name: new TargetName('first'),
                type: TargetTypeEnum::ServiceCategory,
            ),
            performedAt: (new \DateTimeImmutable())->setTime(0, 0),
            details: [
                'key1' => 'value1',
            ],
        );

        $activityLog2 = new ActivityLog(
            id: null,
            action: ActionEnum::Update,
            actor: new Actor(
                id: new ActorId(2),
            ),
            target: new Target(
                id: new TargetId(2),
                name: new TargetName('second'),
                type: TargetTypeEnum::Command,
            ),
            performedAt: (new \DateTimeImmutable())->setTime(0, 0),
            details: [
                'key2' => 'value2',
            ],
        );

        $activityLog3 = new ActivityLog(
            id: null,
            action: ActionEnum::Delete,
            actor: new Actor(
                id: new ActorId(3),
            ),
            target: new Target(
                id: new TargetId(3),
                name: new TargetName('third'),
                type: TargetTypeEnum::ServiceCategory,
            ),
            performedAt: (new \DateTimeImmutable())->setTime(0, 0),
            details: [],
        );

        $this->repository->add($activityLog1, $activityLog2, $activityLog3);

        self::assertSame($initialCount + 3, $this->repository->count());

        self::assertNotNull($activityLog1->id());
        self::assertNotNull($activityLog2->id());
        self::assertNotNull($activityLog3->id());

        self::assertEquals($activityLog1, $this->repository->find($activityLog1->id()));
        self::assertEquals($activityLog2, $this->repository->find($activityLog2->id()));
        self::assertEquals($activityLog3, $this->repository->find($activityLog3->id()));

        // to verify the incremental IDs
        self::assertSame($activityLog1->id()->value + 1, $activityLog2->id()->value);
        self::assertSame($activityLog2->id()->value + 1, $activityLog3->id()->value);
    }

    public function testAddWithEmptyArray(): void
    {
        $initialCount = $this->repository->count();

        $this->repository->add();

        $newCount = $this->repository->count();

        self::assertSame($initialCount, $newCount);
    }

    public function testAddAndFindWithUpdateAction(): void
    {
        $activityLog = new ActivityLog(
            id: null,
            action: ActionEnum::Update,
            actor: new Actor(
                id: new ActorId(1),
            ),
            target: new Target(
                id: new TargetId(1),
                name: new TargetName('updated-item'),
                type: TargetTypeEnum::ServiceCategory,
            ),
            performedAt: (new \DateTimeImmutable())->setTime(0, 0),
            details: [
                'old_value' => 'old',
                'new_value' => 'new',
            ],
        );

        $this->repository->add($activityLog);

        $found = $this->repository->find($activityLog->id());

        self::assertNotNull($found);
        self::assertEquals(ActionEnum::Update, $found->action);
        self::assertSame('old', $found->details['old_value']);
        self::assertSame('new', $found->details['new_value']);
    }

    public function testAddAndFindWithDeleteAction(): void
    {
        $activityLog = new ActivityLog(
            id: null,
            action: ActionEnum::Delete,
            actor: new Actor(
                id: new ActorId(1),
            ),
            target: new Target(
                id: new TargetId(1),
                name: new TargetName('deleted-item'),
                type: TargetTypeEnum::Command,
            ),
            performedAt: (new \DateTimeImmutable())->setTime(0, 0),
            details: [],
        );

        $this->repository->add($activityLog);

        $found = $this->repository->find($activityLog->id());

        self::assertNotNull($found);
        self::assertEquals(ActionEnum::Delete, $found->action);
        self::assertEmpty($found->details);
    }

    public function testAddWithoutDetails(): void
    {
        $activityLog = new ActivityLog(
            id: null,
            action: ActionEnum::Add,
            actor: new Actor(
                id: new ActorId(1),
            ),
            target: new Target(
                id: new TargetId(1),
                name: new TargetName('no-details'),
                type: TargetTypeEnum::ServiceCategory,
            ),
            performedAt: (new \DateTimeImmutable())->setTime(0, 0),
            details: [],
        );

        $this->repository->add($activityLog);

        $found = $this->repository->find($activityLog->id());

        self::assertNotNull($found);
        self::assertEmpty($found->details);
    }

    public function testAddWithMultipleDetails(): void
    {
        $details = [
            'field1' => 'value1',
            'field2' => 'value2',
            'field3' => 'value3',
            'field4' => 'value4',
            'field5' => 'value5',
        ];

        $activityLog = new ActivityLog(
            id: null,
            action: ActionEnum::Add,
            actor: new Actor(
                id: new ActorId(1),
            ),
            target: new Target(
                id: new TargetId(1),
                name: new TargetName('multiple-details'),
                type: TargetTypeEnum::ServiceCategory,
            ),
            performedAt: (new \DateTimeImmutable())->setTime(0, 0),
            details: $details,
        );

        $this->repository->add($activityLog);

        $found = $this->repository->find($activityLog->id());

        self::assertNotNull($found);
        self::assertCount(5, $found->details);
        foreach ($details as $key => $value) {
            self::assertArrayHasKey($key, $found->details);
            self::assertSame($value, $found->details[$key]);
        }
    }
}
