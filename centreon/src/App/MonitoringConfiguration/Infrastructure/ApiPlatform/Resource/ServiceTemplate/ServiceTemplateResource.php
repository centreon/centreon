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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ServiceTemplate;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use App\MonitoringConfiguration\Domain\Security\ServicePermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceTemplate\ListServiceTemplatesChoicesProvider;

#[ApiResource(
    shortName: 'ServiceTemplate',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/services/service_templates',
            openapi: false,
            security: 'is_granted("' . ServicePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to access service templates',
            itemUriTemplate: '/configuration/service_templates/{id}',
            output: ServiceTemplateChoicesOutput::class,
            provider: ListServiceTemplatesChoicesProvider::class,
        ),
        // temporary, to make itemUriTemplate work
        new NotExposed(uriTemplate: '/configuration/service_templates/{id}'),
    ],
)]
final class ServiceTemplateResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,

        public string $name,
    ) {
    }
}
