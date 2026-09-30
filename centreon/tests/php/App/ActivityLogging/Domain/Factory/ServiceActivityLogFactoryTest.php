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

namespace Tests\App\ActivityLogging\Domain\Factory;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Factory\ServiceActivityLogFactory;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use PHPUnit\Framework\TestCase;

final class ServiceActivityLogFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $factory = new ServiceActivityLogFactory();

        $service = new Service(
            id: new ServiceId(42),
            name: new ServiceName('web-check'),
            hostId: new HostId(7),
        );

        $firedAt = new \DateTimeImmutable();

        $activityLog = $factory->create(
            action: ActionEnum::Delete,
            aggregate: $service,
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: $firedAt,
        );

        self::assertSame(ActionEnum::Delete, $activityLog->action);
        self::assertSame(42, $activityLog->target->id->value);
        self::assertSame('web-check', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::Service, $activityLog->target->type);
        self::assertSame(1, $activityLog->actor->id->value);
        self::assertSame($firedAt, $activityLog->performedAt);
        self::assertSame([
            'service_name' => 'web-check',
            'host_id' => '7',
        ], $activityLog->details);
    }
}
