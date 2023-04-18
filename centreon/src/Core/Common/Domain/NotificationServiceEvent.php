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

enum NotificationServiceEvent
{
    use BitmaskEnumTrait;

    case Ok;
    case Warning;
    case Critical;
    case Unknown;

    public function toBit(): int
    {
        return match ($this) {
            self::Ok => 0b0001,
            self::Warning => 0b0010,
            self::Critical => 0b0100,
            self::Unknown => 0b1000,
        };
    }

    public static function getMaxBitmask(): int {
        return 0b111;
    }
}