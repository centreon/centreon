<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Application\UseCase\CountResources;

use Centreon\Domain\Monitoring\ResourceFilter;

/**
 * Class
 *
 * @class CountResourcesRequest
 * @package Core\Resources\Application\UseCase\CountResources
 */
final readonly class CountResourcesRequest {
    /**
     * CountResourcesRequest constructor
     *
     * @param ResourceFilter $resourceFilter
     * @param int $contactId
     * @param bool $isAdmin
     */
    public function __construct(
        public ResourceFilter $resourceFilter,
        public int $contactId,
        public bool $isAdmin
    ) {
        $this->validateRequest();
    }

    /**
     * @return void
     */
    private function validateRequest(): void
    {
        if (! $this->contactId > 0) {
            throw new \InvalidArgumentException("Contact ID must be greater than 0, {$this->contactId} given");
        }
    }
}
