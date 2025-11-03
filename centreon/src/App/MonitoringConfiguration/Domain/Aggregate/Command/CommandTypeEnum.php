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

namespace App\MonitoringConfiguration\Domain\Aggregate\Command;

enum CommandTypeEnum: int
{
    case Notification = 1;
    case Check = 2;
    case Miscellaneous = 3;
    case Discovery = 4;

    public static function fromName(string $name): ?self
    {
        return match($name) {
            self::Notification->name => self::Notification,
            self::Check->name => self::Check,
            self::Miscellaneous->name => self::Miscellaneous,
            self::Discovery->name => self::Discovery,
            default => throw new \InvalidArgumentException("Invalid command type name: $name"),
        };
    }
}
