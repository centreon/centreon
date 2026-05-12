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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Security\PollerPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\CreatePollerProcessor;
use App\MonitoringConfiguration\Infrastructure\Validator\UniquePollerName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Poller',
    operations: [
        new Post(
            uriTemplate: '/configuration/pollers',
            processor: CreatePollerProcessor::class,
            normalizationContext: ['groups' => ['poller:read']],
            denormalizationContext: ['groups' => ['poller:write']],
            openapi: new Model\Operation(
                responses: [
                    409 => new Model\Response('Poller resource already exists'),
                ],
            ),
            security: "is_granted('" . PollerPermissionEnum::CanCreateEdit->value . "')",
            securityMessage: 'You are not allowed to create pollers',
        ),
    ],
)]
final class CreatePollerResource
{
    public function __construct(
        #[Assert\Length(min: PollerName::MIN_LENGTH, max: PollerName::MAX_LENGTH)]
        #[UniquePollerName]
        #[Groups(['poller:read', 'poller:write'])]
        public string $name,

        #[Assert\Choice(choices: [PollerTypeEnum::VM->value, PollerTypeEnum::Docker->value])]
        #[Groups(['poller:read', 'poller:write'])]
        public string $pollerType,

        #[Assert\Length(min: PollerAddress::MIN_LENGTH, max: PollerAddress::MAX_LENGTH)]
        #[Groups(['poller:read', 'poller:write'])]
        public string $address,

        #[ApiProperty(identifier: true, writable: false)]
        #[Groups(['poller:read'])]
        public ?int $id = null,

        #[ApiProperty(writable: false)]
        #[Groups(['poller:read'])]
        public ?int $uid = null,
    ) {
    }
}
