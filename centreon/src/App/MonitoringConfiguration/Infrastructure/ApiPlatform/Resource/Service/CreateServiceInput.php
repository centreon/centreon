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
use App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration\MiscOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHost;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceCategories;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceGroups;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceSeverity;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingGraphTemplate;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingServiceTemplate;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTraps;
use App\MonitoringConfiguration\Infrastructure\Validator\NoControlCharacters;
use App\MonitoringConfiguration\Infrastructure\Validator\ServiceCommandInheritance;
use App\MonitoringConfiguration\Infrastructure\Validator\UniqueServiceNameOnHost;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The create payload of a service: its name and host, its template and relations at the top level, its other
 * fields grouped as the form's sections (check options, monitoring engine, notifications, advanced).
 */
#[UniqueServiceNameOnHost]
#[ServiceCommandInheritance]
final readonly class CreateServiceInput
{
    private const FORBIDDEN_CHARACTERS = '/[' . MiscOptions::DEFAULT_ILLEGAL_OBJECT_NAME_CHARS . ']/';

    /**
     * @param list<int> $serviceGroupIds
     * @param list<int> $categoryIds
     * @param list<int> $trapIds
     */
    public function __construct(
        #[ApiProperty(description: 'Trimmed, and its inner runs of spaces collapsed into one, before it is stored.')]
        #[Assert\Sequentially([
            new Assert\NotBlank(normalizer: 'trim'),
            new Assert\Regex(
                pattern: self::FORBIDDEN_CHARACTERS,
                message: 'This value must not contain any of the following characters: ~ ! $ % ^ & * " | \' < > ? , ( ) =',
                match: false,
            ),
            // On the raw value, before the collapse would turn a tab or a line break into a space.
            new NoControlCharacters(),
            // Measured on what is stored.
            new Assert\Length(max: ServiceName::MAX_LENGTH, normalizer: [self::class, 'normalizeName']),
        ])]
        public string $name,

        #[Assert\NotNull]
        #[Assert\Sequentially([
            new Assert\Positive(),
            new AccessibleHost(),
        ])]
        public int $hostId,

        #[ApiProperty(description: 'Mandatory on a Cloud platform, optional otherwise.')]
        #[Assert\Sequentially([
            new Assert\Positive(),
            new ExistingServiceTemplate(),
        ])]
        #[WhenPlatform(forCloud: true, constraints: [new Assert\NotNull()])]
        public ?int $serviceTemplateId = null,

        #[Assert\Sequentially([
            new Assert\All([new Assert\Type('integer'), new Assert\Positive()]),
            new AccessibleServiceGroups(),
        ])]
        public array $serviceGroupIds = [],

        #[Assert\Sequentially([
            new Assert\All([new Assert\Type('integer'), new Assert\Positive()]),
            new AccessibleServiceCategories(),
        ])]
        public array $categoryIds = [],

        #[Assert\Sequentially([
            new Assert\Positive(),
            new AccessibleServiceSeverity(),
        ])]
        public ?int $severityId = null,

        #[ApiProperty(description: 'Not available on a Cloud platform.')]
        #[Assert\Sequentially([
            new Assert\All([new Assert\Type('integer'), new Assert\Positive()]),
            new ExistingTraps(),
        ])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Count(exactly: 0, exactMessage: 'This field is not available on a Cloud platform.'),
        ])]
        public array $trapIds = [],

        #[ApiProperty(description: 'Not available on a Cloud platform.')]
        #[Assert\Sequentially([
            new Assert\Positive(),
            new ExistingGraphTemplate(),
        ])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'This field is not available on a Cloud platform.'),
        ])]
        public ?int $graphTemplateId = null,

        #[Assert\Valid]
        public ?ServiceCheckOptionsInput $checkOptions = null,

        #[Assert\Valid]
        public ?ServiceMonitoringEngineInput $monitoringEngine = null,

        #[ApiProperty(description: 'Not available on a Cloud platform, where notifications follow a different model.')]
        #[Assert\Valid]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Blank(message: 'Notifications are not available on a Cloud platform.'),
        ])]
        public ?ServiceNotificationsInput $notifications = null,

        #[Assert\Valid]
        public ?ServiceAdvancedInput $advanced = null,
    ) {
    }

    /**
     * The name as it is stored: trimmed, its inner runs of whitespace collapsed into one space.
     */
    public static function normalizeName(string $name): string
    {
        return (string) preg_replace('/\s{2,}/', ' ', trim($name));
    }
}
