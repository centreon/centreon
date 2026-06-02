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
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\PollerPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\GetInstallationCommandProvider;

#[ApiResource(
    operations: [new Get(
        uriTemplate: '/configuration/pollers/installation-command/{id}',
        provider: GetInstallationCommandProvider::class,
        openapi: new Model\Operation(
            tags: ['Poller'],
            parameters: [
                new Model\Parameter(
                    name: 'token-name',
                    in: 'query',
                    description: 'Name of the poller token to embed in the installation command. When omitted, the first valid poller token is used.',
                    required: false,
                    schema: ['type' => 'string'],
                ),
            ],
            responses: [
                404 => new Model\Response('Poller not found'),
                403 => new Model\Response('You are not allowed to access this resource'),
            ]
        ),
    )],
    security: "is_granted('" . PollerPermissionEnum::CanCreateEdit->value . "')", )
]
final class InstallationCommandResource
{
    public function __construct(
        #[ApiProperty(
            description: 'Poller Installation Command',
            openapiContext: ['example' => 'curl -fsSL https://<url>/poller/install.sh | bash -s -- --poller_token <token> --uid <uid> --name <name> --type <vm|docker> --central_url <central_url> --appsecret <app_secret> --salt <salt>']
        )]
        public string $installationCommand,
    ) {
    }
}
