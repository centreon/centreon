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
use App\ActivityLogging\Domain\Event\LogServiceMassChangeEventHandler;
use App\ActivityLogging\Domain\Factory\ServiceActivityLogFactory;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Event\ServiceMassChanged;
use PHPUnit\Framework\TestCase;
use Tests\App\ActivityLogging\Infrastructure\Double\FakeActivityLogRepository;

final class LogServiceMassChangeEventHandlerTest extends TestCase
{
    public function testItWritesOneMassChangeLineNamedAfterTheServiceAndItsActor(): void
    {
        $repository = new FakeActivityLogRepository();
        $handler = new LogServiceMassChangeEventHandler($repository, new ServiceActivityLogFactory());

        $handler(ServiceMassChanged::of(
            new Service(id: new ServiceId(42), name: new ServiceName('web-check'), hostId: new HostId(7), hostActivated: true, hostPollerId: new PollerId(3)),
            9,
        ));

        $lines = array_values($repository->activityLogs);
        self::assertCount(1, $lines);
        self::assertSame(
            [ActionEnum::MassChange, 42, 'web-check', 9, 'web-check', '7'],
            [
                $lines[0]->action,
                $lines[0]->target->id->value,
                $lines[0]->target->name->value,
                $lines[0]->actor->id->value,
                $lines[0]->details['service_description'] ?? null,
                $lines[0]->details['host_id'] ?? null,
            ],
        );
    }
}
