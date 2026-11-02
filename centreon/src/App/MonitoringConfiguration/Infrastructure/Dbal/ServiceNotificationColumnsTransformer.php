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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\Dbal\TriStateColumnTrait;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * Write-side counterpart of {@see ServiceTransformer} for the notification settings: turns a
 * {@see ServiceNotifications} value object into the `service` table's own storage formats, which the domain
 * knows nothing about.
 *
 * A null block transforms to exactly what legacy writes for a form carrying no notification field: the default
 * tri-state, false flags, and NULL everywhere else.
 *
 * @phpstan-type ServiceNotificationColumnsTypeAlias = array{
 *   notificationsEnabled: string,
 *   notificationOptions: string|null,
 *   notificationInterval: int|null,
 *   notificationPeriodId: int|null,
 *   firstNotificationDelay: int|null,
 *   recoveryNotificationDelay: int|null,
 *   contactAdditiveInheritance: bool,
 *   contactGroupAdditiveInheritance: bool,
 *   useOnlyContactsFromHost: string,
 * }
 *
 * @implements TransformerInterface<?ServiceNotifications, ServiceNotificationColumnsTypeAlias>
 */
final readonly class ServiceNotificationColumnsTransformer implements TransformerInterface
{
    use TriStateColumnTrait;

    public function transform(mixed $from): array
    {
        return [
            'notificationsEnabled' => $this->triStateToColumn($from instanceof ServiceNotifications ? $from->enabled : TriStateEnum::UseDefault),
            'notificationOptions' => self::options($from instanceof ServiceNotifications ? $from->options : []),
            'notificationInterval' => $from?->interval,
            'notificationPeriodId' => $from?->periodId?->value,
            'firstNotificationDelay' => $from?->firstDelay,
            'recoveryNotificationDelay' => $from?->recoveryDelay,
            'contactAdditiveInheritance' => $from instanceof ServiceNotifications && $from->contactAdditiveInheritance,
            'contactGroupAdditiveInheritance' => $from instanceof ServiceNotifications && $from->contactGroupAdditiveInheritance,
            'useOnlyContactsFromHost' => $from instanceof ServiceNotifications && $from->useOnlyContactsFromHost ? '1' : '0',
        ];
    }

    /**
     * The engine's own comma-separated single-letter format. An empty list writes NULL, as legacy does, distinct
     * from the "none" option, which stores a real 'n' meaning "notify on nothing".
     *
     * @param list<ServiceNotificationOptionEnum> $options
     */
    public static function options(array $options): ?string
    {
        if ($options === []) {
            return null;
        }

        return implode(',', array_map(
            static fn (ServiceNotificationOptionEnum $option): string => match ($option) {
                ServiceNotificationOptionEnum::Warning => 'w',
                ServiceNotificationOptionEnum::Unknown => 'u',
                ServiceNotificationOptionEnum::Critical => 'c',
                ServiceNotificationOptionEnum::Recovery => 'r',
                ServiceNotificationOptionEnum::Flapping => 'f',
                ServiceNotificationOptionEnum::DowntimeScheduled => 's',
                ServiceNotificationOptionEnum::None => 'n',
            },
            $options,
        ));
    }
}
