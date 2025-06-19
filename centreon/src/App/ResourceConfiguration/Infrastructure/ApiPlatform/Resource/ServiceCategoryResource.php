<?php

declare(strict_types=1);

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
 */

namespace App\ResourceConfiguration\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\ResourceConfiguration\Domain\Exception\ServiceCategoryAlreadyExistException;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\State\CreateServiceCategoryProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'ServiceCategory',
    operations: [
        new Post(
            uriTemplate: '/configuration/services/categories',
            processor: CreateServiceCategoryProcessor::class,
            exceptionToStatus: [ServiceCategoryAlreadyExistException::class => 409],
            openapi: new Model\Operation(
                responses: [
                    409 => new Model\Response('ServiceCategory resource already exist'),
                ],
            ),
        ),
    ],
)]
final class ServiceCategoryResource
{
    public function __construct(
        #[ApiProperty(identifier: true, readable: false, writable: false)]
        public ?int $id = null,

        #[Assert\NotNull]
        #[Assert\Length(min: 1, max: 255)]
        public ?string $name = null,

        #[Assert\NotNull]
        #[Assert\Length(min: 1, max: 255)]
        public ?string $alias = null,

        public bool $isActivated = true,
    ) {
    }
}
