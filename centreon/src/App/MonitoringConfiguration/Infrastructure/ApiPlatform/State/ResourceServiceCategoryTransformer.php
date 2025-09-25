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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State;

use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategory;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ServiceCategoryResource;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @implements TransformerInterface<ServiceCategory, ServiceCategoryResource>
 */
final readonly class ResourceServiceCategoryTransformer implements TransformerInterface
{
    public function transform(mixed $from): ServiceCategoryResource
    {
        return new ServiceCategoryResource(
            id: $from->id()->value,
            name: $from->name->value,
            alias: $from->alias->value,
            isActivated: $from->activated,
        );
    }
}
