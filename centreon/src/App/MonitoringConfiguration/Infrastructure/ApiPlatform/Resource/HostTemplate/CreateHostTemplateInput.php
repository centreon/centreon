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
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpVersionEnum;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostCategories;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostSeverity;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingHostTemplates;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingServiceTemplates;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimezone;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Infrastructure\Validator\Constraints\IdList;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use App\Shared\Infrastructure\Validator\Constraints\WhenVault;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateHostTemplateInput
{
    private const CONTROL_CHARACTERS = '/[\x00-\x1F\x7F]/';

    /**
     * @param list<int> $templateIds
     * @param list<int> $categoryIds
     * @param list<int> $linkedServiceTemplateIds
     */
    public function __construct(
        #[ApiProperty(description: 'Trimmed; inner spaces become underscores. Unique among hosts and host templates.')]
        #[Assert\Sequentially([
            new Assert\NotBlank(normalizer: 'trim'),
            new Assert\Length(max: HostTemplateName::MAX_LENGTH, normalizer: 'trim'),
        ])]
        public ?string $name = null,

        #[Assert\Sequentially([
            new Assert\NotBlank(normalizer: 'trim'),
            new Assert\Length(max: HostAlias::MAX_LENGTH, normalizer: 'trim'),
            new Assert\Regex(pattern: self::CONTROL_CHARACTERS, message: 'This value must not contain control characters.', match: false),
        ])]
        public ?string $alias = null,

        public ?SnmpVersionEnum $snmpVersion = null,

        // Bounded only without a vault: with one, the column holds the reference.
        #[ApiProperty(description: 'Write-only. Stored in the vault when one is configured, and never returned.')]
        #[WhenVault(forVault: false, constraints: [
            new Assert\Length(max: SnmpCommunity::MAX_LENGTH, normalizer: 'trim'),
        ])]
        #[Assert\Regex(pattern: self::CONTROL_CHARACTERS, message: 'This value must not contain control characters.', match: false)]
        #[Sensitive]
        public ?string $snmpCommunity = null,

        #[Assert\Sequentially([new Assert\Positive(), new ExistingTimezone()])]
        public ?int $timezoneId = null,

        #[Assert\Sequentially([new Assert\Positive(), new AccessibleHostSeverity()])]
        public ?int $severityId = null,

        #[ApiProperty(description: 'Ordered: the position of a parent template sets the inheritance order.')]
        #[Assert\Sequentially([new IdList(), new ExistingHostTemplates()])]
        public array $templateIds = [],

        #[Assert\Sequentially([new IdList(), new AccessibleHostCategories()])]
        public array $categoryIds = [],

        #[ApiProperty(description: 'Ordered: the position of a service template sets the priority of its check command macros.')]
        #[Assert\Sequentially([new IdList(), new ExistingServiceTemplates()])]
        public array $linkedServiceTemplateIds = [],

        #[Assert\Valid]
        public ?CreateHostTemplateCheckOptionsInput $checkOptions = null,

        #[Assert\Valid]
        public ?CreateHostTemplateExtendedInformationsInput $extendedInformations = null,

        #[Assert\Valid]
        public ?CreateHostTemplateSchedulingOptionsInput $schedulingOptions = null,

        #[Assert\Valid]
        public ?CreateHostTemplateDataProcessingInput $dataProcessing = null,

        #[ApiProperty(description: 'Not available on a Cloud platform, where notifications follow a different model.')]
        #[Assert\Valid]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Blank(message: 'Notifications are not available on a Cloud platform.'),
        ])]
        public ?CreateHostTemplateNotificationsInput $notifications = null,
    ) {
    }
}
