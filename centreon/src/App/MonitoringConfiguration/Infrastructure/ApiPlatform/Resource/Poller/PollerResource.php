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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreatePollerInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\CreatePollerProcessor;
use App\Shared\Domain\Logging\Attribute\Sensitive;

#[ApiResource(
    shortName: 'Poller',
    operations: [
        new Post(
            uriTemplate: '/configuration/pollers',
            processor: CreatePollerProcessor::class,
            input: CreatePollerInput::class,
            openapi: new Model\Operation(
                responses: [
                    400 => new Model\Response('Invalid input (e.g. unknown poller token name)'),
                    409 => new Model\Response('Poller resource already exists'),
                    503 => new Model\Response('Engine secrets are not available'),
                ],
            ),
            security: "is_granted('" . PollerPermissionEnum::CanCreateEdit->value . "')",
            securityMessage: 'You are not allowed to create pollers',
        ),
    ],
)]
final class PollerResource
{
    public function __construct(
        public string $name,

        public string $pollerType,

        public string $address,

        #[ApiProperty(identifier: true)]
        public int $id,

        public string $uid,

        public string $gorgoneCommunicationType,

        #[ApiProperty(
            openapiContext: ['example' => 'curl -fsSL https://<url>/poller/install.sh | bash -s -- --poller_token <token_name>:<token_value> --uid <uid> --name <name> --type <vm|docker> --central_url <central_url> --appsecret <app_secret> --salt <salt>']
        )]
        #[Sensitive]
        public string $installationCommand = '',
    ) {
    }
}
