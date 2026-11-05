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
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpVersionEnum;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostCategories;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostSeverity;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingHostTemplates;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingServiceTemplates;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimezone;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use App\Shared\Infrastructure\Validator\Constraints\ExclusiveKeys;
use App\Shared\Infrastructure\Validator\Constraints\IdList;
use App\Shared\Infrastructure\Validator\Constraints\NotNullWhenProvided;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use App\Shared\Infrastructure\Validator\Constraints\WhenVault;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Every key is optional: a key left out leaves the value untouched, a key sent as null clears it where a
 * value can be cleared. The properties that cannot be null carry {@see NotNullWhenProvided}, since this
 * DTO reads both cases as null (see {@see RequestPayload}).
 *
 * Do not add an `id` property here: it would turn a missing template's 404 into a 422 through
 * InvalidReferenceExceptionListener.
 */
#[ExclusiveKeys([
    ['templateIds', 'templateIdsToAdd', 'templateIdsToRemove'],
    ['categoryIds', 'categoryIdsToAdd', 'categoryIdsToRemove'],
    ['linkedServiceTemplateIds', 'linkedServiceTemplateIdsToAdd', 'linkedServiceTemplateIdsToRemove'],
])]
final readonly class PatchHostTemplateInput
{
    private const CONTROL_CHARACTERS = '/[\x00-\x1F\x7F]/';

    /**
     * @param list<int> $templateIds
     * @param list<int> $templateIdsToAdd
     * @param list<int> $templateIdsToRemove
     * @param list<int> $categoryIds
     * @param list<int> $categoryIdsToAdd
     * @param list<int> $categoryIdsToRemove
     * @param list<int> $linkedServiceTemplateIds
     * @param list<int> $linkedServiceTemplateIdsToAdd
     * @param list<int> $linkedServiceTemplateIdsToRemove
     */
    public function __construct(
        // Unique among hosts and templates: the check needs the template being changed, so it lives
        // in the command handler.
        #[ApiProperty(description: 'Trimmed; inner spaces become underscores.')]
        #[NotNullWhenProvided]
        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Length(max: HostTemplateName::MAX_LENGTH, normalizer: 'trim'),
        ])]
        public ?string $name = null,

        #[NotNullWhenProvided]
        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Length(max: HostTemplateAlias::MAX_LENGTH, normalizer: 'trim'),
            new Assert\Regex(pattern: self::CONTROL_CHARACTERS, message: 'This value must not contain control characters.', match: false),
        ])]
        public ?string $alias = null,

        public ?HostTemplateSnmpVersionEnum $snmpVersion = null,

        #[ApiProperty(description: 'Write-only, never returned. Left out keeps the community, null removes it.')]
        #[WhenVault(forVault: false, constraints: [
            new Assert\Length(max: HostTemplateSnmpCommunity::MAX_LENGTH, normalizer: 'trim'),
        ])]
        #[Assert\Regex(pattern: self::CONTROL_CHARACTERS, message: 'This value must not contain control characters.', match: false)]
        #[Sensitive]
        public ?string $snmpCommunity = null,

        #[Assert\Sequentially([new Assert\Positive(), new ExistingTimezone()])]
        public ?int $timezoneId = null,

        #[ApiProperty(description: 'null removes the severity, unless it is one the user cannot see.')]
        #[Assert\Sequentially([new Assert\Positive(), new AccessibleHostSeverity()])]
        public ?int $severityId = null,

        #[ApiProperty(description: 'Replaces the parents, in the order given: it sets the inheritance order. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new ExistingHostTemplates()])]
        public array $templateIds = [],

        #[ApiProperty(description: 'Added after the current parents.')]
        #[Assert\Sequentially([new IdList(), new ExistingHostTemplates()])]
        public array $templateIdsToAdd = [],

        #[IdList]
        public array $templateIdsToRemove = [],

        #[ApiProperty(description: 'Replaces the categories; an empty list removes them all. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new AccessibleHostCategories()])]
        public array $categoryIds = [],

        #[Assert\Sequentially([new IdList(), new AccessibleHostCategories()])]
        public array $categoryIdsToAdd = [],

        #[IdList]
        public array $categoryIdsToRemove = [],

        #[ApiProperty(description: 'Replaces the linked service templates, in the order given. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new ExistingServiceTemplates()])]
        public array $linkedServiceTemplateIds = [],

        #[ApiProperty(description: 'Added after the current linked service templates.')]
        #[Assert\Sequentially([new IdList(), new ExistingServiceTemplates()])]
        public array $linkedServiceTemplateIdsToAdd = [],

        #[IdList]
        public array $linkedServiceTemplateIdsToRemove = [],

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?PatchHostTemplateCheckOptionsInput $checkOptions = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?PatchHostTemplateExtendedInformationsInput $extendedInformations = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?CreateHostTemplateSchedulingOptionsInput $schedulingOptions = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?CreateHostTemplateDataProcessingInput $dataProcessing = null,

        #[ApiProperty(description: 'Not available on a Cloud platform, where notifications follow a different model.')]
        #[NotNullWhenProvided]
        #[Assert\Valid]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Blank(message: 'Notifications are not available on a Cloud platform.'),
        ])]
        public ?PatchHostTemplateNotificationsInput $notifications = null,
    ) {
    }
}
