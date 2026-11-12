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
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHost;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceCategories;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceGroups;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceSeverity;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingGraphTemplate;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingServiceTemplate;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTraps;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use App\Shared\Infrastructure\Validator\Constraints\ExclusiveKeys;
use App\Shared\Infrastructure\Validator\Constraints\IdList;
use App\Shared\Infrastructure\Validator\Constraints\NotNullWhenProvided;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Every key is optional: a key left out leaves the value untouched, a key sent as null clears it where a value can
 * be cleared. The properties that cannot be null carry {@see NotNullWhenProvided}, since this DTO reads both cases
 * as null (see {@see RequestPayload}). The rules that need the stored service (the name on the target host, a check
 * command or an event handler own or inherited) are checked by the command handler only.
 *
 * Do not add an `id` property here: it would flip a missing-service 404 into a 422 via
 * InvalidReferenceExceptionListener.
 */
#[ExclusiveKeys([
    ['serviceGroupIds', 'serviceGroupIdsToAdd', 'serviceGroupIdsToRemove'],
    ['categoryIds', 'categoryIdsToAdd', 'categoryIdsToRemove'],
    ['trapIds', 'trapIdsToAdd', 'trapIdsToRemove'],
])]
final readonly class PatchServiceInput
{
    private const CONTROL_CHARACTERS = '/[\x00-\x1F\x7F]/';
    private const FORBIDDEN_CHARACTERS = '/[~!$%^&*"|\'<>?,()=]/';

    /**
     * @param list<int> $serviceGroupIds replaces the service groups, an empty list removes them all
     * @param list<int> $serviceGroupIdsToAdd
     * @param list<int> $serviceGroupIdsToRemove
     * @param list<int> $categoryIds
     * @param list<int> $categoryIdsToAdd
     * @param list<int> $categoryIdsToRemove
     * @param list<int> $trapIds
     * @param list<int> $trapIdsToAdd
     * @param list<int> $trapIdsToRemove
     */
    public function __construct(
        #[ApiProperty(description: 'Whether the service is enabled.')]
        #[NotNullWhenProvided]
        public ?bool $activated = null,

        #[ApiProperty(description: 'Trimmed, and its inner runs of spaces collapsed into one, before it is stored. Unique on the host the service ends up on.')]
        #[NotNullWhenProvided]
        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Regex(
                pattern: self::FORBIDDEN_CHARACTERS,
                message: 'This value must not contain any of the following characters: ~ ! $ % ^ & * " | \' < > ? , ( ) =',
                match: false,
            ),
            // On the raw value, before the collapse would turn a tab or a line break into a space.
            new Assert\Regex(pattern: self::CONTROL_CHARACTERS, message: 'This value must not contain control characters.', match: false),
            // Measured on what is stored.
            new Assert\Length(max: ServiceName::MAX_LENGTH, normalizer: [CreateServiceInput::class, 'normalizeName']),
        ])]
        public ?string $name = null,

        #[ApiProperty(description: 'Moves the service to this host: its other host links, if any, are dropped.')]
        #[NotNullWhenProvided]
        #[Assert\Sequentially([
            new Assert\Positive(),
            new AccessibleHost(),
        ])]
        public ?int $hostId = null,

        #[ApiProperty(description: 'Null removes the template, except on a Cloud platform.')]
        #[Assert\Sequentially([
            new Assert\Positive(),
            new ExistingServiceTemplate(),
        ])]
        #[WhenPlatform(forCloud: true, constraints: [new NotNullWhenProvided()])]
        public ?int $serviceTemplateId = null,

        #[Assert\Sequentially([
            new Assert\Positive(),
            new AccessibleServiceSeverity(),
        ])]
        public ?int $severityId = null,

        #[ApiProperty(description: 'Replaces the service groups; an empty list removes them all. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new AccessibleServiceGroups()])]
        public array $serviceGroupIds = [],

        #[Assert\Sequentially([new IdList(), new AccessibleServiceGroups()])]
        public array $serviceGroupIdsToAdd = [],

        #[IdList]
        public array $serviceGroupIdsToRemove = [],

        #[ApiProperty(description: 'Replaces the categories; an empty list removes them all. Only one of this, the "_to_add" and the "_to_remove" keys can be sent.')]
        #[Assert\Sequentially([new IdList(), new AccessibleServiceCategories()])]
        public array $categoryIds = [],

        #[Assert\Sequentially([new IdList(), new AccessibleServiceCategories()])]
        public array $categoryIdsToAdd = [],

        #[IdList]
        public array $categoryIdsToRemove = [],

        #[ApiProperty(description: 'Replaces the traps; an empty list removes them all. Only one of this, the "_to_add" and the "_to_remove" keys can be sent. Not available on a Cloud platform.')]
        #[Assert\Sequentially([new IdList(), new ExistingTraps()])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Count(exactly: 0, exactMessage: 'This field is not available on a Cloud platform.'),
        ])]
        public array $trapIds = [],

        #[Assert\Sequentially([new IdList(), new ExistingTraps()])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Count(exactly: 0, exactMessage: 'This field is not available on a Cloud platform.'),
        ])]
        public array $trapIdsToAdd = [],

        #[IdList]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Count(exactly: 0, exactMessage: 'This field is not available on a Cloud platform.'),
        ])]
        public array $trapIdsToRemove = [],

        #[ApiProperty(description: 'Not available on a Cloud platform.')]
        #[Assert\Sequentially([
            new Assert\Positive(),
            new ExistingGraphTemplate(),
        ])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'This field is not available on a Cloud platform.'),
        ])]
        public ?int $graphTemplateId = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?PatchServiceCheckOptionsInput $checkOptions = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?ServiceMonitoringEngineInput $monitoringEngine = null,

        #[ApiProperty(description: 'Not available on a Cloud platform, where notifications follow a different model.')]
        #[NotNullWhenProvided]
        #[Assert\Valid]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Blank(message: 'Notifications are not available on a Cloud platform.'),
        ])]
        public ?PatchServiceNotificationsInput $notifications = null,

        #[NotNullWhenProvided]
        #[Assert\Valid]
        public ?PatchServiceAdvancedInput $advanced = null,
    ) {
    }
}
