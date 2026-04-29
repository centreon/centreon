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
use App\MonitoringConfiguration\Domain\Security\PollerPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\CreatePollerProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Poller',
    operations: [
        new Post(
            uriTemplate: '/configuration/pollers',
            processor: CreatePollerProcessor::class,
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
        #[Assert\Length(min: 1, max: 40)]
        public string $name,

        #[Assert\Choice(choices: ['vm', 'docker'])]
        public string $pollerType,

        #[ApiProperty(identifier: true, writable: false)]
        public ?int $id = null,

        /**
         * Could be nullable has it is not mandatory. The Poller is connecting to the Central so the Central don't need
         * to know the Poller Address
         */
        #[Assert\Length(max: 255)]
        public ?string $address = null,

        #[ApiProperty(writable: false)]
        public ?string $uuid = null,
    ) {}
}
