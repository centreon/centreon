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

namespace Tests\App\ActivityLogging\Infrastructure\Double;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\Target;
use App\ActivityLogging\Domain\Aggregate\TargetId;
use App\ActivityLogging\Domain\Aggregate\TargetName;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Factory\ActivityLogFactoryInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;

/**
 * @implements ActivityLogFactoryInterface<AggregateRoot<AggregateRootId>>
 */
final class FakeActivityLogFactory implements ActivityLogFactoryInterface
{
    public function create(ActionEnum $action, AggregateRoot $aggregate, Actor $firedBy, \DateTimeImmutable $firedAt): ActivityLog
    {

        $type = match ($action) {
            ActionEnum::Add => TargetTypeEnum::ServiceCategory,
            ActionEnum::Update, ActionEnum::Delete => TargetTypeEnum::Command,
        };

        $target = new Target(
            id: new TargetId(1),
            name: new TargetName('NAME'),
            type: $type,
        );

        return new ActivityLog(
            id: null,
            action: $action,
            actor: $firedBy,
            target: $target,
            performedAt: $firedAt,
            details: [],
        );
    }
}
