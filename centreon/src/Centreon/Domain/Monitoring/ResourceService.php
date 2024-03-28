<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Domain\Monitoring;

use Centreon\Domain\Service\AbstractCentreonService;
use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Monitoring\Interfaces\ResourceServiceInterface;

/**
 * Service manage the resources in real-time monitoring : hosts and services.
 *
 * @package Centreon\Domain\Monitoring
 */
class ResourceService extends AbstractCentreonService implements ResourceServiceInterface
{
    /**
     * Find host id by resource
     * @param ResourceEntity $resource
     * @return int|null
     */
    public static function generateHostIdByResource(ResourceEntity $resource): ?int
    {
        $hostId = null;
        if ($resource->getType() === ResourceEntity::TYPE_HOST) {
            $hostId = (int) $resource->getId();
        } elseif (
            $resource->getParent() !== null
            && $resource->getType() === ResourceEntity::TYPE_SERVICE
        ) {
            $hostId = (int) $resource->getParent()->getId();
        }

        return $hostId;
    }
}
