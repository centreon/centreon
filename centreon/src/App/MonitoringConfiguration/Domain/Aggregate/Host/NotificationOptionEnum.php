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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

/**
 * The host state transitions that trigger a notification.
 *
 * Backing values are the API-contract strings; the mapping to the `host_notification_options`
 * column's single-letter legacy format (`d`, `u`, `r`, `f`, `s`, `n`) lives in the DBAL layer.
 *
 * {@see self::None} is exclusive: it means "notify on nothing" and cannot be combined with any
 * other case (enforced by {@see Notifications}).
 */
enum NotificationOptionEnum: string
{
    case Down = 'down';
    case Unreachable = 'unreachable';
    case Recovery = 'recovery';
    case Flapping = 'flapping';
    case DowntimeScheduled = 'downtime_scheduled';
    case None = 'none';
}
