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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\AgentConfiguration;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\AgentConfigurationPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\AgentConfiguration\GetInstallationCommandProvider;

#[ApiResource(
    operations: [new Get(
        uriTemplate: '/configuration/agent-configurations/installation-command/{pollerId}',
        provider: GetInstallationCommandProvider::class,
        openapi: new Model\Operation(
            tags: ['Agent Configuration'],
            responses: [
                404 => new Model\Response('Poller not found'),
                403 => new Model\Response('You are not allowed to access this resource'),
            ]
        ),
    )],
    security: "is_granted('" . AgentConfigurationPermissionEnum::CanReadAndWrite->value . "', request.attributes.get('pollerId'))", )
]
final class InstallationCommandResource
{
    public function __construct(
        #[ApiProperty(
            description: 'Windows Installation Command',
            openapiContext: ['example' => 'curl -fsSL https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/[PF_VERSION]-latest/agent/installer/install_cma.ps1 -o install_cma.ps1 ; .\install_cma.ps1 -endpoint "[DNS]:[PORT]" -fingerprint "[certificate_sha]" -commonname "[certificate_cn]"']
        )]
        public string $windowsInstallationCommand,

        #[ApiProperty(
            description: 'Linux Installation Command',
            openapiContext: ['example' => 'curl -fsSL https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/[PF_VERSION]-latest/agent/installer/scripts_linux/install_cma.sh -o install_cma.sh && sudo chmod +x install_cma.sh && sudo ./install_cma.sh -e "[poller IP/DNS]:[PORT]" -f "[centreon_storage.instances.cma_certificate_sha]" -N "[centreon_storage.cma_certificate_cn]"']
        )]
        public string $linuxInstallationCommand,
    ) {
    }
}
