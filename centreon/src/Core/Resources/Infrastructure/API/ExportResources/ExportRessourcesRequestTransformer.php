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

namespace Core\Resources\Infrastructure\API\ExportResources;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Resources\Application\UseCase\ExportResources\ExportResourcesRequest;

/**
 * Class
 *
 * @class ExportRessourcesRequestTransformer
 * @package Core\Resources\Infrastructure\API\ExportResources
 */
class ExportRessourcesRequestTransformer {

    /**
     * @param ExportRessourcesInput $input
     * @param ResourceFilter $resourceFilter
     * @param ContactInterface $contact
     *
     * @return ExportResourcesRequest
     */
    public static function transform(
        ExportRessourcesInput $input,
        ResourceFilter $resourceFilter,
        ContactInterface $contact
    ): ExportResourcesRequest {
        return new ExportResourcesRequest(
            contact: $contact,
            exportedFormat: $input->format,
            resourceFilter: $resourceFilter,
            allPages: $input->all_pages,
            maxResults: $input->max_lines ?? 10000,
            columns: $input->columns
        );
    }
}
