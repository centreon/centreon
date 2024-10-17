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

namespace Core\ServiceTemplate\Application\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\ServiceTemplate\Domain\Model\NotificationType;

class NotificationTypeConverter
{
    public const NONE_AS_BIT = 0b000000;
    public const WARNING_AS_BIT = 0b000001;
    public const UNKNOWN_AS_BIT = 0b000010;
    public const CRITICAL_AS_BIT = 0b000100;
    public const RECOVERY_AS_BIT = 0b001000;
    public const FLAPPING_AS_BIT = 0b010000;
    public const DOWNTIME_SCHEDULED_AS_BIT = 0b100000;
    public const ALL_TYPE = 0b111111;

    /**
     * @param int $bitFlag
     *
     * @throws AssertionFailedException
     *
     * @return list<NotificationType>
     */
    public static function fromBits(int $bitFlag): array
    {
        Assertion::range($bitFlag, 0, self::ALL_TYPE);
        if ($bitFlag === self::NONE_AS_BIT) {
            return [NotificationType::None];
        }

        $notificationTypes = [];
        foreach (NotificationType::cases() as $notificationType) {
            if ($bitFlag & self::toBit($notificationType)) {
                $notificationTypes[] = $notificationType;
            }
        }

        return $notificationTypes;
    }

    /**
     * @param NotificationType $notificationType
     *
     * @return int
     */
    private static function toBit(NotificationType $notificationType): int
    {
        return match ($notificationType) {
            NotificationType::Warning => self::WARNING_AS_BIT,
            NotificationType::Unknown => self::UNKNOWN_AS_BIT,
            NotificationType::Critical => self::CRITICAL_AS_BIT,
            NotificationType::Recovery => self::RECOVERY_AS_BIT,
            NotificationType::Flapping => self::FLAPPING_AS_BIT,
            NotificationType::DowntimeScheduled => self::DOWNTIME_SCHEDULED_AS_BIT,
            NotificationType::None => self::NONE_AS_BIT,
        };
    }
}
