<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Common\Domain;

enum HostEvent: string
{
    use LegacyEventEnumTrait, BitmaskEnumTrait;

    case Down = 'd';
    case Unreachable = 'u';
    case Recovery = 'r';
    case Flapping = 'f';
    case DowntimeScheduled = 's';
    case None = 'n';

    public function toBit(): int
    {
        return match ($this) {
            self::None => 0b00000,
            self::Down => 0b00001,
            self::Unreachable => 0b00010,
            self::Recovery => 0b00100,
            self::Flapping => 0b01000,
            self::DowntimeScheduled => 0b10000,
        };
    }

    public static function getMaxBitmask(): int {
        return 0b11111;
    }
}