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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\HostTemplateNotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContactGroups;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContacts;
use App\MonitoringConfiguration\Infrastructure\Validator\ExclusiveNotificationOption;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimePeriod;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\Validator\Constraints\ExclusiveKeys;
use App\Shared\Infrastructure\Validator\Constraints\IdList;
use App\Shared\Infrastructure\Validator\Constraints\NotNullWhenProvided;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusiveKeys([
    ['contacts', 'contactsToAdd', 'contactsToRemove'],
    ['contactGroups', 'contactGroupsToAdd', 'contactGroupsToRemove'],
    ['options', 'optionsToAdd', 'optionsToRemove'],
])]
final readonly class PatchHostTemplateNotificationsInput
{
    /**
     * @param list<int> $contacts
     * @param list<int> $contactsToAdd
     * @param list<int> $contactsToRemove
     * @param list<int> $contactGroups
     * @param list<int> $contactGroupsToAdd
     * @param list<int> $contactGroupsToRemove
     * @param list<string> $options
     * @param list<string> $optionsToAdd
     * @param list<string> $optionsToRemove
     */
    public function __construct(
        #[ApiProperty(description: 'null means "use_default".')]
        #[Assert\Choice(choices: [TriStateEnum::False->value, TriStateEnum::True->value, TriStateEnum::UseDefault->value])]
        public ?string $enabled = null,

        #[ApiProperty(
            description: 'State transitions triggering a notification, an empty list clears them. "none" is exclusive. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.',
            openapiContext: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => HostTemplateNotificationOptionEnumResolver::API_VALUES]],
        )]
        #[Assert\Sequentially([
            new Assert\All([new Assert\Choice(callback: [CreateHostTemplateNotificationsInput::class, 'apiOptions'])]),
            new ExclusiveNotificationOption(),
        ])]
        public array $options = [],

        #[ApiProperty(
            description: 'Added to the current options. "none" is exclusive.',
            openapiContext: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => HostTemplateNotificationOptionEnumResolver::API_VALUES]],
        )]
        #[Assert\Sequentially([
            new Assert\All([new Assert\Choice(callback: [CreateHostTemplateNotificationsInput::class, 'apiOptions'])]),
            new ExclusiveNotificationOption(),
        ])]
        public array $optionsToAdd = [],

        #[ApiProperty(
            description: 'Removed from the current options.',
            openapiContext: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => HostTemplateNotificationOptionEnumResolver::API_VALUES]],
        )]
        #[Assert\All([new Assert\Choice(callback: [CreateHostTemplateNotificationsInput::class, 'apiOptions'])])]
        public array $optionsToRemove = [],

        #[ApiProperty(description: 'Replaces the contacts; an empty list removes them all, those the user cannot see stay. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new AccessibleContacts()])]
        public array $contacts = [],

        #[Assert\Sequentially([new IdList(), new AccessibleContacts()])]
        public array $contactsToAdd = [],

        #[IdList]
        public array $contactsToRemove = [],

        #[ApiProperty(description: 'Replaces the contact groups; an empty list removes them all, those the user cannot see stay. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new AccessibleContactGroups()])]
        public array $contactGroups = [],

        #[Assert\Sequentially([new IdList(), new AccessibleContactGroups()])]
        public array $contactGroupsToAdd = [],

        #[IdList]
        public array $contactGroupsToRemove = [],

        #[Assert\Range(min: 0, max: HostTemplate::MAX_INTEGER)]
        public ?int $interval = null,

        #[Assert\Sequentially([new Assert\Positive(), new ExistingTimePeriod()])]
        public ?int $timeperiodId = null,

        #[Assert\Range(min: 0, max: HostTemplate::MAX_INTEGER)]
        public ?int $firstDelay = null,

        #[Assert\Range(min: 0, max: HostTemplate::MAX_INTEGER)]
        public ?int $recoveryDelay = null,

        #[ApiProperty(description: "Ignored unless the platform's inheritance mode enables additive inheritance.")]
        #[NotNullWhenProvided]
        public ?bool $contactAdditiveInheritance = null,

        #[ApiProperty(description: "Ignored unless the platform's inheritance mode enables additive inheritance.")]
        #[NotNullWhenProvided]
        public ?bool $contactGroupAdditiveInheritance = null,
    ) {
    }
}
