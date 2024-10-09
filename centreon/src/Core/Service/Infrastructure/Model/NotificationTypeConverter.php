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

namespace Core\Service\Infrastructure\Model;

use Core\Service\Domain\Model\NotificationType;

final class NotificationTypeConverter
{
    public const NONE_AS_BIT = 0b000000;
    public const NONE_AS_CHAR = 'n';
    public const WARNING_AS_BIT = 0b000001;
    public const WARNING_AS_CHAR = 'w';
    public const UNKNOWN_AS_BIT = 0b000010;
    public const UNKNOWN_AS_CHAR = 'u';
    public const CRITICAL_AS_BIT = 0b000100;
    public const CRITICAL_AS_CHAR = 'c';
    public const RECOVERY_AS_BIT = 0b001000;
    public const RECOVERY_AS_CHAR = 'r';
    public const FLAPPING_AS_BIT = 0b010000;
    public const FLAPPING_AS_CHAR = 'f';
    public const DOWNTIME_SCHEDULED_AS_BIT = 0b100000;
    public const DOWNTIME_SCHEDULED__AS_CHAR = 's';

    /**
     * @param NotificationType[] $notificationTypes
     *
     * @return int|null
     */
    public static function toBits(array $notificationTypes): ?int
    {
        if ($notificationTypes === []) {
            return null;
        }
        $bits = 0;
        foreach ($notificationTypes as $type) {
            // The 0 is considered a "None" type and therefore we do not expect any other values.
            if (self::toBit($type) === 0) {
                return 0;
            }
            $bits |= self::toBit($type);
        }

        return $bits;
    }

    /**
     * @param NotificationType[] $notificationTypes
     *
     * @return string|null
     */
    public static function toString(array $notificationTypes): ?string
    {
        $notificationChars = [];
        if ($notificationTypes === []) {
            return null;
        }
        foreach ($notificationTypes as $notification) {
            $notificationChars[] = match ($notification) {
                NotificationType::None => self::NONE_AS_CHAR,
                NotificationType::Warning => self::WARNING_AS_CHAR,
                NotificationType::Unknown => self::UNKNOWN_AS_CHAR,
                NotificationType::Critical => self::CRITICAL_AS_CHAR,
                NotificationType::Recovery => self::RECOVERY_AS_CHAR,
                NotificationType::Flapping => self::FLAPPING_AS_CHAR,
                NotificationType::DowntimeScheduled => self::DOWNTIME_SCHEDULED__AS_CHAR,
            };
        }

        return implode(',', $notificationChars);
    }

    /**
     * @param NotificationType $notificationType
     *
     * @return int
     */
    private static function toBit(NotificationType $notificationType): int
    {
        return match ($notificationType) {
            NotificationType::None => self::NONE_AS_BIT,
            NotificationType::Warning => self::WARNING_AS_BIT,
            NotificationType::Unknown => self::UNKNOWN_AS_BIT,
            NotificationType::Critical => self::CRITICAL_AS_BIT,
            NotificationType::Recovery => self::RECOVERY_AS_BIT,
            NotificationType::Flapping => self::FLAPPING_AS_BIT,
            NotificationType::DowntimeScheduled => self::DOWNTIME_SCHEDULED_AS_BIT,
        };
    }
}
