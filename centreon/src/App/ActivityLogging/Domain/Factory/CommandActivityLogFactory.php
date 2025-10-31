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

namespace App\ActivityLogging\Domain\Factory;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\Target;
use App\ActivityLogging\Domain\Aggregate\TargetId;
use App\ActivityLogging\Domain\Aggregate\TargetName;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\Shared\Domain\Aggregate\AggregateRoot;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * @implements ActivityLogFactoryInterface<Command>
 */
#[AsTaggedItem(index: Command::class)]
final readonly class CommandActivityLogFactory implements ActivityLogFactoryInterface
{
    public function create(ActionEnum $action, AggregateRoot $aggregate, Actor $firedBy, \DateTimeImmutable $firedAt): ActivityLog
    {
        $target = new Target(
            id: new TargetId($aggregate->id()->value),
            name: new TargetName($aggregate->name->value),
            type: TargetTypeEnum::Command,
        );

        $details = [
            'command_activate' => $aggregate->isActivated ? '1' : '0',
            'command_name' => $aggregate->name->value,
            'command_type' => (string) $aggregate->type->value,
            'command_line' => $aggregate->commandLine->value,
            'enable_shell' => $aggregate->isShellEnabled ? '1' : '0',
            'connector' => $aggregate->connector?->name->value ?: '',
        ];

        return new ActivityLog(
            id: null,
            action: $action,
            actor: $firedBy,
            target: $target,
            performedAt: $firedAt,
            details: $details,
        );
    }
}
