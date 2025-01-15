<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Infrastructure\Repository\ExtraDataProviders;

use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Monitoring\ResourceFilter;

interface ExtraDataProviderInterface
{
    /**
     * @param ResourceFilter $filter
     *
     * @return string
     */
    public function getSubFilter(ResourceFilter $filter): string;

    /**
     * @param ResourceFilter $filter
     * @param ResourceEntity[] $resources
     *
     * @return mixed[]
     */
    public function getExtraDataForResources(ResourceFilter $filter, array $resources): array;

    /**
     * @return string
     */
    public function getExtraDataSourceName(): string;

    /**
     * Indicates regarding the data set in the ResourceFilter if the provider should add or not extra data to resources.
     *
     * @param ResourceFilter $filter
     *
     * @return bool
     */
    public function supportsExtraData(ResourceFilter $filter): bool;
}
