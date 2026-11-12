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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceNotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContactGroups;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContacts;
use App\MonitoringConfiguration\Infrastructure\Validator\ExclusiveNotificationOption;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimePeriod;
use App\Shared\Domain\Aggregate\TriStateEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ServiceNotificationsInput
{
    /**
     * @param list<int> $contacts
     * @param list<int> $contactGroups imported LDAP groups included
     * @param list<string> $options
     */
    public function __construct(
        #[ApiProperty(description: 'Whether notifications are enabled; "use_default" leaves the directive to the template chain.')]
        #[Assert\Choice(choices: [TriStateEnum::False->value, TriStateEnum::True->value, TriStateEnum::UseDefault->value])]
        public string $enabled = TriStateEnum::UseDefault->value,

        #[ApiProperty(description: 'When true, the service notifies the contacts of its host only: its own contacts and contact groups are cleared, and both additive-inheritance flags switched off.')]
        public bool $useOnlyContactsFromHost = false,

        #[ApiProperty(description: "Ignored unless the platform's inheritance mode enables additive inheritance.")]
        public bool $contactAdditiveInheritance = ServiceNotifications::DEFAULT_ADDITIVE_INHERITANCE,

        #[ApiProperty(description: "Ignored unless the platform's inheritance mode enables additive inheritance.")]
        public bool $contactGroupAdditiveInheritance = ServiceNotifications::DEFAULT_ADDITIVE_INHERITANCE,

        #[Assert\Sequentially([
            new Assert\All([new Assert\Type('integer'), new Assert\Positive()]),
            new AccessibleContacts(),
        ])]
        public array $contacts = [],

        #[Assert\Sequentially([
            new Assert\All([new Assert\Type('integer'), new Assert\Positive()]),
            new AccessibleContactGroups(),
        ])]
        public array $contactGroups = [],

        #[Assert\PositiveOrZero]
        public ?int $interval = null,

        #[Assert\Sequentially([new Assert\Positive(), new ExistingTimePeriod()])]
        public ?int $timeperiodId = null,

        #[ApiProperty(
            description: 'State transitions triggering a notification. "none" is exclusive.',
            openapiContext: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ServiceNotificationOptionEnumResolver::API_VALUES]],
        )]
        #[Assert\Sequentially([
            new Assert\All([new Assert\Choice(callback: [self::class, 'apiOptions'])]),
            new ExclusiveNotificationOption(),
        ])]
        public array $options = [],

        #[Assert\PositiveOrZero]
        public ?int $firstDelay = null,

        #[Assert\PositiveOrZero]
        public ?int $recoveryDelay = null,
    ) {
    }

    /**
     * The choices the API accepts, so the input DTO and the mapping cannot drift apart.
     *
     * @return list<string>
     */
    public static function apiOptions(): array
    {
        return array_map(ServiceNotificationOptionEnumResolver::toString(...), ServiceNotificationOptionEnum::cases());
    }
}
