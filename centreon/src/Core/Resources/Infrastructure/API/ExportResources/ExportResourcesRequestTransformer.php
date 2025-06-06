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
use Core\Common\Domain\Collection\StringCollection;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\TransformerException;
use Core\Resources\Application\UseCase\ExportResources\ExportResourcesRequest;

/**
 * Class
 *
 * @class ExportResourcesRequestTransformer
 * @package Core\Resources\Infrastructure\API\ExportResources
 */
final readonly class ExportResourcesRequestTransformer
{
    /**
     * @param ExportResourcesInput $input
     * @param ResourceFilter $resourceFilter
     * @param ContactInterface $contact
     *
     * @throws TransformerException
     * @return ExportResourcesRequest
     */
    public static function transform(
        ExportResourcesInput $input,
        ResourceFilter $resourceFilter,
        ContactInterface $contact
    ): ExportResourcesRequest {
        $allPages = filter_var($input->allPages, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($allPages === null) {
            throw new TransformerException(
                'Error while transforming input to request for export resources with all_pages',
                ['input_all_pages' => $input->allPages, 'all_pages' => $allPages]
            );
        }

        try {
            $columns = is_null($input->columns) ? new StringCollection() : new StringCollection($input->columns);
        } catch (CollectionException $e) {
            throw new TransformerException(
                'Error while transforming input to request for export resources with columns',
                ['columns' => $input->columns],
                $e
            );
        }

        return new ExportResourcesRequest(
            exportedFormat: $input->format ?? '',
            resourceFilter: $resourceFilter,
            allPages: $allPages,
            maxResults: is_null($input->maxLines) ? 0 : (int) $input->maxLines,
            columns: $columns,
            contactId: $contact->getId(),
            isAdmin: $contact->isAdmin()
        );
    }
}
