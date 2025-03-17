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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\ResourceFilter;

/**
 * Class
 *
 * @class CountResourcesRequest
 * @package Core\Resources\Application\UseCase\CountResources
 */
class CountResourcesRequest {
    /**
     * CountResourcesRequest constructor
     *
     * @param ContactInterface $contact
     * @param ResourceFilter $resourceFilter
     */
    public function __construct(
        public ContactInterface $contact,
        public ResourceFilter $resourceFilter,
    ) {}
}
