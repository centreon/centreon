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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\ServiceCategoryPermissionEnum;
use App\MonitoringConfiguration\Domain\Security\ServicePermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ServiceCategory\ServiceCategoryChoicesOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\CreateServiceCategoryProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceCategory\ListServiceCategoriesChoicesProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'ServiceCategory',
    operations: [
        new Post(
            uriTemplate: '/configuration/services/categories',
            processor: CreateServiceCategoryProcessor::class,
            openapi: new Model\Operation(
                tags: ['Service Category'],
                responses: [
                    409 => new Model\Response('ServiceCategory resource already exists'),
                ],
            ),
            security: "is_granted('" . ServiceCategoryPermissionEnum::CanWrite->value . "')",
            securityMessage: 'You are not allowed to create service categories',
        ),
        new GetCollection(
            uriTemplate: '/configuration/services/service_categories',
            openapi: false,
            security: 'is_granted("' . ServicePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to access service categories',
            itemUriTemplate: '/configuration/service_categories/{id}',
            output: ServiceCategoryChoicesOutput::class,
            provider: ListServiceCategoriesChoicesProvider::class,
        ),
        // Placeholder item operation: it gives the selector items and the category created by the POST
        // above their IRI (@id and Location). Replace it with a real Get when one exists, never just
        // remove it.
        new NotExposed(uriTemplate: '/configuration/service_categories/{id}'),
    ],
)]
final class ServiceCategoryResource
{
    public function __construct(
        #[Assert\Length(min: 1, max: 255)]
        public string $name,

        #[Assert\Length(min: 1, max: 255)]
        public string $alias,

        #[ApiProperty(identifier: true, writable: false)]
        public ?int $id = null,

        public bool $isActivated = true,
    ) {
    }
}
