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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostGroups;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessiblePoller;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidHostAddress;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateHostInput
{
    /**
     * @param list<int> $hostGroupIds
     */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: HostName::MIN_LENGTH, max: HostName::MAX_LENGTH)]
        #[Assert\Regex(pattern: '/^_Module(?:_| )/', match: false, message: 'This value must not start with "_Module_".', normalizer: 'trim')]
        public string $name,

        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: HostAddress::MIN_LENGTH, max: HostAddress::MAX_LENGTH)]
        #[ValidHostAddress]
        public string $address,

        #[Assert\NotNull]
        #[Assert\Positive]
        #[AccessiblePoller]
        public int $pollerId,

        #[ApiProperty(description: 'Mandatory when creating a host on a Cloud platform, optional otherwise.')]
        #[Assert\All([new Assert\Positive()])]
        #[AccessibleHostGroups]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Count(min: 1, minMessage: 'Host groups are mandatory when creating a host on a Cloud platform.'),
        ])]
        public array $hostGroupIds = [],
    ) {
    }
}
