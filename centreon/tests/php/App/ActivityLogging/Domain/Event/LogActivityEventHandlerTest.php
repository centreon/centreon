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

namespace Tests\App\ActivityLogging\Domain\Event;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Event\LogActivityEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategory;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryName;
use App\MonitoringConfiguration\Domain\Event\ServiceCategoryCreated;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Tests\App\ActivityLogging\Infrastructure\Double\FakeActivityLogFactory;
use Tests\App\ActivityLogging\Infrastructure\Double\FakeActivityLogRepository;

final class LogActivityEventHandlerTest extends TestCase
{
    public function testCreateActivityLogOnCreation(): void
    {
        $repository = new FakeActivityLogRepository();

        $handler = new LogActivityEventHandler($repository, $this->createContainer([
            ServiceCategory::class => new FakeActivityLogFactory(),
        ]));

        $aggregate = new ServiceCategory(
            id: new ServiceCategoryId(1),
            name: new ServiceCategoryName('NAME'),
            alias: new ServiceCategoryName('ALIAS'),
            activated: true,
        );

        $handler(new ServiceCategoryCreated(
            aggregate: $aggregate,
            creatorId: 2,
            firedAt: $firedAt = new \DateTimeImmutable(),
        ));

        $activityLog = reset($repository->activityLogs);

        self::assertInstanceof(ActivityLog::class, $activityLog);
        self::assertSame(ActionEnum::Add, $activityLog->action);
        self::assertSame(2, $activityLog->actor->id->value);
        self::assertSame(1, $activityLog->target->id->value);
        self::assertSame('NAME', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::ServiceCategory, $activityLog->target->type);
        self::assertEquals($firedAt, $activityLog->performedAt);
    }

    /**
     * @param array<string, object> $factories
     */
    private function createContainer(array $factories = []): ContainerInterface
    {
        return new class ($factories) implements ContainerInterface {
            /**
             * @param array<string, object> $factories
             */
            public function __construct(
                private array $factories,
            ) {
            }

            public function has(string $id): bool
            {
                return isset($this->factories[$id]);
            }

            public function get(string $id): object
            {
                return $this->factories[$id];
            }
        };
    }
}
