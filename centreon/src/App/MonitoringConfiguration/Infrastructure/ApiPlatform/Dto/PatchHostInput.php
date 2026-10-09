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
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostCategories;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostGroups;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHosts;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostSeverity;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessiblePoller;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingHostTemplates;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimezone;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidHostAddress;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use App\Shared\Infrastructure\Validator\Constraints\ExclusiveKeys;
use App\Shared\Infrastructure\Validator\Constraints\IdList;
use App\Shared\Infrastructure\Validator\Constraints\NotNullWhenProvided;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use App\Shared\Infrastructure\Validator\Constraints\WhenVault;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Every key is optional: a key left out leaves the value untouched, a key sent as null clears it where
 * a value can be cleared. The properties that cannot be null carry {@see NotNullWhenProvided}, since
 * this DTO reads both cases as null (see {@see RequestPayload}).
 *
 * Do not add an `id` property here: it would flip a missing-host 404 into a 422 via
 * InvalidReferenceExceptionListener.
 */
#[ExclusiveKeys([
    ['hostGroupIds', 'hostGroupIdsToAdd', 'hostGroupIdsToRemove'],
    ['categoryIds', 'categoryIdsToAdd', 'categoryIdsToRemove'],
    ['templateIds', 'templateIdsToAdd', 'templateIdsToRemove'],
    ['parentHostIds', 'parentHostIdsToAdd', 'parentHostIdsToRemove'],
    ['childHostIds', 'childHostIdsToAdd', 'childHostIdsToRemove'],
])]
final readonly class PatchHostInput
{
    private const CONTROL_CHARACTERS = '/[\x00-\x1F\x7F]/';

    /**
     * @param list<int> $hostGroupIds replaces the host groups, an empty list removes them all
     * @param list<int> $hostGroupIdsToAdd
     * @param list<int> $hostGroupIdsToRemove
     * @param list<int> $categoryIds
     * @param list<int> $categoryIdsToAdd
     * @param list<int> $categoryIdsToRemove
     * @param list<int> $templateIds replaces the templates, in the order given
     * @param list<int> $templateIdsToAdd added after the current ones
     * @param list<int> $templateIdsToRemove
     * @param list<int> $parentHostIds
     * @param list<int> $parentHostIdsToAdd
     * @param list<int> $parentHostIdsToRemove
     * @param list<int> $childHostIds
     * @param list<int> $childHostIdsToAdd
     * @param list<int> $childHostIdsToRemove
     */
    public function __construct(
        #[ApiProperty(description: 'Whether the host is enabled.')]
        #[NotNullWhenProvided]
        public ?bool $activated = null,

        // The name is unique among hosts and templates: the check needs the host being changed, so it
        // lives in the command handler rather than in a constraint here.
        #[NotNullWhenProvided]
        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Length(min: HostName::MIN_LENGTH, max: HostName::MAX_LENGTH),
            new Assert\Regex(
                pattern: '/(^_Module(?:_| ))|([~!$%^&*"|\'<>?,()=])/',
                message: 'This value must not start with "_Module_" and must not contain any of the following characters: ~ ! $ % ^ & * " | \' < > ? , ( ) =',
                match: false,
                normalizer: 'trim',
            ),
        ])]
        public ?string $name = null,

        #[NotNullWhenProvided]
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(min: HostAddress::MIN_LENGTH, max: HostAddress::MAX_LENGTH)]
        #[ValidHostAddress]
        public ?string $address = null,

        #[NotNullWhenProvided]
        #[Assert\Sequentially([
            new Assert\Positive(),
            new AccessiblePoller(),
        ])]
        public ?int $pollerId = null,

        #[Assert\Length(max: HostAlias::MAX_LENGTH, normalizer: 'trim')]
        #[Assert\Regex(pattern: self::CONTROL_CHARACTERS, message: 'This value must not contain control characters.', match: false)]
        public ?string $alias = null,

        public ?SnmpVersionEnum $snmpVersion = null,

        #[ApiProperty(description: 'Write-only. Stored in the vault when one is configured, and never returned.')]
        #[WhenVault(forVault: false, constraints: [
            new Assert\Length(max: SnmpCommunity::MAX_LENGTH, normalizer: 'trim'),
        ])]
        #[Assert\Regex(pattern: self::CONTROL_CHARACTERS, message: 'This value must not contain control characters.', match: false)]
        #[Sensitive]
        public ?string $snmpCommunity = null,

        #[Assert\Sequentially([
            new Assert\Positive(),
            new ExistingTimezone(),
        ])]
        public ?int $timezoneId = null,

        #[Assert\Sequentially([
            new Assert\Positive(),
            new AccessibleHostSeverity(),
        ])]
        public ?int $severityId = null,

        #[ApiProperty(description: 'Replaces the host groups; an empty list removes them all. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new AccessibleHostGroups()])]
        public array $hostGroupIds = [],

        #[Assert\Sequentially([new IdList(), new AccessibleHostGroups()])]
        public array $hostGroupIdsToAdd = [],

        #[IdList]
        public array $hostGroupIdsToRemove = [],

        #[ApiProperty(description: 'Replaces the categories; an empty list removes them all. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new AccessibleHostCategories()])]
        public array $categoryIds = [],

        #[Assert\Sequentially([new IdList(), new AccessibleHostCategories()])]
        public array $categoryIdsToAdd = [],

        #[IdList]
        public array $categoryIdsToRemove = [],

        #[ApiProperty(description: 'Replaces the templates, in the order given: it sets the inheritance order. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new ExistingHostTemplates()])]
        public array $templateIds = [],

        #[ApiProperty(description: 'Added after the current templates.')]
        #[Assert\Sequentially([new IdList(), new ExistingHostTemplates()])]
        public array $templateIdsToAdd = [],

        #[IdList]
        public array $templateIdsToRemove = [],

        #[ApiProperty(description: 'Replaces the hosts this one depends on. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new AccessibleHosts()])]
        public array $parentHostIds = [],

        #[Assert\Sequentially([new IdList(), new AccessibleHosts()])]
        public array $parentHostIdsToAdd = [],

        #[IdList]
        public array $parentHostIdsToRemove = [],

        #[ApiProperty(description: 'Replaces the hosts that depend on this one. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new AccessibleHosts()])]
        public array $childHostIds = [],

        #[Assert\Sequentially([new IdList(), new AccessibleHosts()])]
        public array $childHostIdsToAdd = [],

        #[IdList]
        public array $childHostIdsToRemove = [],

        #[ApiProperty(description: 'Creates, once the host is saved, the services of the templates it has. Not stored.')]
        #[NotNullWhenProvided]
        public ?bool $createServicesLinkedToTemplates = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?DataProcessingInput $dataProcessing = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?CreateHostExtendedInformationsInput $extendedInformations = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?CreateHostSchedulingOptionsInput $schedulingOptions = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?PatchHostCheckOptionsInput $checkOptions = null,

        #[ApiProperty(description: 'Not available on a Cloud platform, where notifications follow a different model.')]
        #[NotNullWhenProvided]
        #[Assert\Valid]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Blank(message: 'Notifications are not available on a Cloud platform.'),
        ])]
        public ?PatchHostNotificationsInput $notifications = null,
    ) {
    }
}
