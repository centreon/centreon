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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * Groups the host's "Notification" tab behind a single VO instead of a growing list of scalar
 * properties directly on {@see Host}: the six `host_notification*` columns of the `host` table,
 * the two additive-inheritance flags, and the `contact_host_relation` /
 * `contactgroup_host_relation` links.
 *
 * On-prem only — the Cloud platform handles notifications through a different model
 * (see MON-208992/MON-204689), so the API rejects this whole block there.
 */
final readonly class Notifications
{
    public const MIN_INTERVAL = 0;
    public const MIN_FIRST_DELAY = 0;
    public const MIN_RECOVERY_DELAY = 0;

    /** @var list<NotificationOptionEnum> */
    public array $options;

    /**
     * @param Collection<NotificationContactId> $contactIds
     * @param Collection<ContactGroupId> $contactGroupIds
     * @param list<NotificationOptionEnum> $options duplicates are collapsed; {@see NotificationOptionEnum::None}
     *                                              is exclusive and cannot be combined
     * @param bool $contactAdditiveInheritance forced to false by the caller when the platform's
     *                                         `inheritance_mode` option is not enabled, mirroring legacy
     * @param bool $contactGroupAdditiveInheritance same as $contactAdditiveInheritance, for contact groups
     */
    public function __construct(
        public TriStateEnum $enabled,
        public Collection $contactIds,
        public Collection $contactGroupIds,
        array $options = [],
        public ?int $interval = null,
        public ?TimePeriodId $periodId = null,
        public ?int $firstDelay = null,
        public ?int $recoveryDelay = null,
        public bool $contactAdditiveInheritance = false,
        public bool $contactGroupAdditiveInheritance = false,
    ) {
        $uniqueOptions = [];
        foreach ($options as $option) {
            $uniqueOptions[$option->value] = $option;
        }
        $this->options = array_values($uniqueOptions);

        Assert::false(
            in_array(NotificationOptionEnum::None, $this->options, true) && count($this->options) > 1,
            sprintf(
                'Notifications::options cannot combine "%s" with any other option.',
                NotificationOptionEnum::None->value,
            ),
        );

        Assert::nullOrGreaterThanEq($interval, self::MIN_INTERVAL);
        Assert::nullOrGreaterThanEq($firstDelay, self::MIN_FIRST_DELAY);
        Assert::nullOrGreaterThanEq($recoveryDelay, self::MIN_RECOVERY_DELAY);
    }
}
