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

namespace Core\Resources\Application\UseCase\ExportResources;

use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Common\Domain\Collection\StringCollection;
use Core\Resources\Application\UseCase\ExportResources\Enum\AllowedFormatEnum;

/**
 * Class
 *
 * @class ExportResourcesRequest
 * @package Core\Resources\Application\UseCase\ExportResources
 */
final readonly class ExportResourcesRequest {
    /**
     * ExportResourcesRequest constructor
     *
     * @param string $exportedFormat
     * @param ResourceFilter $resourceFilter
     * @param bool $allPages
     * @param int $maxResults
     * @param StringCollection $columns
     * @param int $contactId
     * @param bool $isAdmin
     */
    public function __construct(
        public string $exportedFormat,
        public ResourceFilter $resourceFilter,
        public bool $allPages,
        public int $maxResults,
        public StringCollection $columns,
        public int $contactId,
        public bool $isAdmin
    )
    {
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

        if (is_null(AllowedFormatEnum::tryFrom($this->exportedFormat))) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Format must be one of the following: %s, %s given',
                    implode(', ', AllowedFormatEnum::values()),
                    $this->exportedFormat
                )
            );
        }

        if ($this->allPages && $this->maxResults === 0) {
            throw new \InvalidArgumentException(
                "Max number of resources is required when exporting all pages"
            );
        }

        if ($this->allPages && $this->maxResults > 10000) {
            throw new \InvalidArgumentException(
                "Max number of resources to export must be equal or less than 10000, {$this->maxResults} given"
            );
        }
    }
}
