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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * Write-side counterpart of {@see DbalHostTransformer}: turns a {@see Notifications} value object
 * into the `host` table's own storage formats, which the domain deliberately knows nothing about.
 *
 * A null block transforms to exactly what legacy writes for a payload carrying no notification
 * field — the Default tri-state, false flags, and NULL everywhere else.
 *
 * @phpstan-type NotificationColumns = array{
 *   notificationsEnabled: string,
 *   notificationOptions: string|null,
 *   notificationInterval: int|null,
 *   notificationPeriodId: int|null,
 *   firstNotificationDelay: int|null,
 *   recoveryNotificationDelay: int|null,
 *   contactAdditiveInheritance: bool,
 *   contactGroupAdditiveInheritance: bool,
 * }
 *
 * @implements TransformerInterface<?Notifications, NotificationColumns>
 */
final readonly class DbalNotificationsTransformer implements TransformerInterface
{
    public function transform(mixed $from): array
    {
        return [
            'notificationsEnabled' => self::triState($from instanceof Notifications ? $from->enabled : TriStateEnum::UseDefault),
            'notificationOptions' => self::options($from instanceof Notifications ? $from->options : []),
            'notificationInterval' => $from?->interval,
            'notificationPeriodId' => $from?->periodId?->value,
            'firstNotificationDelay' => $from?->firstDelay,
            'recoveryNotificationDelay' => $from?->recoveryDelay,
            'contactAdditiveInheritance' => $from instanceof Notifications && $from->contactAdditiveInheritance,
            'contactGroupAdditiveInheritance' => $from instanceof Notifications && $from->contactGroupAdditiveInheritance,
        ];
    }

    /**
     * The storage format of every `enum('0','1','2')` column of the legacy schema.
     */
    public static function triState(TriStateEnum $value): string
    {
        return match ($value) {
            TriStateEnum::False => '0',
            TriStateEnum::True => '1',
            TriStateEnum::UseDefault => '2',
        };
    }

    /**
     * The engine's own comma-separated single-letter format. An empty list writes NULL, as legacy
     * does (DbWriteHostRepository::bindHostValues()) — distinct from the "none" option, which
     * stores a real 'n' meaning "notify on nothing".
     *
     * @param list<NotificationOptionEnum> $options
     */
    public static function options(array $options): ?string
    {
        if ($options === []) {
            return null;
        }

        return implode(',', array_map(
            static fn (NotificationOptionEnum $option): string => match ($option) {
                NotificationOptionEnum::Down => 'd',
                NotificationOptionEnum::Unreachable => 'u',
                NotificationOptionEnum::Recovery => 'r',
                NotificationOptionEnum::Flapping => 'f',
                NotificationOptionEnum::DowntimeScheduled => 's',
                NotificationOptionEnum::None => 'n',
            },
            $options,
        ));
    }
}
