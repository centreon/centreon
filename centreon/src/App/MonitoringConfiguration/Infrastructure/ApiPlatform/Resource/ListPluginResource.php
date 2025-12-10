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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ListPluginsProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'ListPlugins',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/plugins',
            provider: ListPluginsProvider::class,
        ),
    ],
)]
final class ListPluginResource
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[ApiProperty(
            description: 'Name of plugin',
            openapiContext: ['example' => 'negate']
        )]
        public ?string $name = null,
    ) {
    }
}
