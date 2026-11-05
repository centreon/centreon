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
use App\ActivityLogging\Domain\Factory\HostTemplateActivityLogFactory;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostTemplateActivityLogFactoryTest extends TestCase
{
    #[DataProvider('actions')]
    public function testItLogsTheTemplateWithoutDetails(ActionEnum $action): void
    {
        $log = new HostTemplateActivityLogFactory()->create(
            $action,
            new HostTemplate(new HostTemplateId(12), new HostTemplateName('generic')),
            new Actor(new ActorId(3)),
            new \DateTimeImmutable(),
        );

        self::assertSame($action, $log->action);
        self::assertSame(TargetTypeEnum::HostTemplate, $log->target->type);
        self::assertSame(12, $log->target->id->value);
        self::assertSame('generic', $log->target->name->value);
        self::assertSame([], $log->details);
    }

    /**
     * @return iterable<string, array{ActionEnum}>
     */
    public static function actions(): iterable
    {
        yield 'duplicate' => [ActionEnum::Add];

        yield 'delete' => [ActionEnum::Delete];
    }
}
