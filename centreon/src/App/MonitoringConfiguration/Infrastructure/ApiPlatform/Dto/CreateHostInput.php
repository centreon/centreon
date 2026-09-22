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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostGroups;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessiblePoller;
use App\MonitoringConfiguration\Infrastructure\Validator\UniqueHostName;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidHostAddress;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use App\Shared\Infrastructure\Validator\Constraints\WhenVault;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateHostInput
{
    private const CONTROL_CHARACTERS = '/[\x00-\x1F\x7F]/';

    /**
     * @param list<int> $hostGroupIds
     * @param list<int> $templateIds
     * @param list<int> $categoryIds
     * @param list<int> $parentHostIds
     * @param list<int> $childHostIds
     */
    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotBlank(normalizer: 'trim'),
            new Assert\Length(min: HostName::MIN_LENGTH, max: HostName::MAX_LENGTH),
            new Assert\Regex(
                pattern: '/(^_Module(?:_| ))|([~!$%^&*"|\'<>?,()=])/',
                match: false,
                message: 'This value must not start with "_Module_" and must not contain any of the following characters: ~ ! $ % ^ & * " | \' < > ? , ( ) =',
                normalizer: 'trim',
            ),
            new UniqueHostName(),
        ])]
        public string $name,

        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: HostAddress::MIN_LENGTH, max: HostAddress::MAX_LENGTH)]
        #[ValidHostAddress]
        public string $address,

        #[Assert\NotNull]
        #[Assert\Sequentially([
            new Assert\Positive(),
            new AccessiblePoller(),
        ])]
        public int $pollerId,

        #[ApiProperty(description: 'Mandatory when creating a host on a Cloud platform, optional otherwise.')]
        #[Assert\Sequentially([
            new Assert\All([new Assert\Type('integer'), new Assert\Positive()]),
            new AccessibleHostGroups(),
        ])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Count(min: 1, minMessage: 'Host groups are mandatory when creating a host on a Cloud platform.'),
        ])]
        public array $hostGroupIds = [],

        // Legacy asserts maxLength on the trimmed value only, so a blank alias is valid there.
        #[Assert\Length(max: HostAlias::MAX_LENGTH, normalizer: 'trim')]
        // Stricter than legacy, deliberately: config generation writes this straight into a
        // `.cfg` line (object.class.php), so an embedded newline would inject a directive.
        #[Assert\Regex(pattern: self::CONTROL_CHARACTERS, match: false, message: 'This value must not contain control characters.')]
        public ?string $alias = null,

        public ?SnmpVersionEnum $snmpVersion = null,

        // Bounded only without a vault: legacy measures the substituted path, not the plaintext,
        // and only what lands in the column is bounded.
        #[ApiProperty(description: 'Write-only. Stored in the vault when one is configured, and never returned.')]
        #[WhenVault(forVault: false, constraints: [
            new Assert\Length(max: SnmpCommunity::MAX_LENGTH, normalizer: 'trim'),
        ])]
        #[Assert\Regex(pattern: self::CONTROL_CHARACTERS, match: false, message: 'This value must not contain control characters.')]
        #[Sensitive]
        public ?string $snmpCommunity = null,

        #[Assert\Positive]
        public ?int $timezoneId = null,

        #[Assert\Positive]
        public ?int $severityId = null,

        #[ApiProperty(description: 'Ordered: the position of a template drives the inheritance order.')]
        #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
        public array $templateIds = [],

        #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
        public array $categoryIds = [],

        #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
        public array $parentHostIds = [],

        #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
        public array $childHostIds = [],

        #[Assert\Valid]
        public ?DataProcessingInput $dataProcessing = null,

        #[ApiProperty(description: 'Not available on a Cloud platform, where linked services are always created. Defaults to true.')]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'This field is not available on a Cloud platform.'),
        ])]
        public ?bool $createServicesLinkedToTemplates = null,

        #[Assert\Valid]
        public ?CreateHostExtendedInformationsInput $extendedInformations = null,

        #[Assert\Valid]
        public ?CreateHostSchedulingOptionsInput $schedulingOptions = null,

        #[Assert\Valid]
        public ?CheckOptionsInput $checkOptions = null,
    ) {
    }
}
