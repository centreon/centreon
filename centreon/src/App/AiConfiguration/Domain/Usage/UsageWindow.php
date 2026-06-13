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

namespace App\AiConfiguration\Domain\Usage;

/**
 * Start timestamps of the current daily and monthly spend windows, in UTC.
 */
final class UsageWindow
{
    public static function dayStart(\DateTimeImmutable $now): int
    {
        return $now->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0)->getTimestamp();
    }

    public static function monthStart(\DateTimeImmutable $now): int
    {
        return $now->setTimezone(new \DateTimeZone('UTC'))
            ->modify('first day of this month')
            ->setTime(0, 0)
            ->getTimestamp();
    }
}
