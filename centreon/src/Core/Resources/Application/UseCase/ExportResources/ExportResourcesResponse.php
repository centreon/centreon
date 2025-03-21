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

use Centreon\Domain\Monitoring\Resource as ResourceEntity;

/**
 * Class
 *
 * @class ExportResourcesResponse
 * @package Core\Resources\Application\UseCase\ExportResources
 */
final class ExportResourcesResponse {
    /** @var \Traversable<ResourceEntity> */
    private \Traversable $resources;

    /** @var array<string> */
    private array $filteredColumns = [];

    /** @var string */
    private string $exportedFormat;

    /**
     * @return \Traversable<ResourceEntity>
     */
    public function getResources(): \Traversable
    {
        return $this->resources;
    }

    /**
     * @param \Traversable<ResourceEntity> $resources
     *
     * @return ExportResourcesResponse
     */
    public function setResources(\Traversable $resources): self
    {
        $this->resources = $resources;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getFilteredColumns(): array
    {
        return $this->filteredColumns;
    }

    /**
     * @param array<string> $filteredColumns
     *
     * @return ExportResourcesResponse
     */
    public function setFilteredColumns(array $filteredColumns): self
    {
        $this->filteredColumns = $filteredColumns;

        return $this;
    }

    /**
     * @return string
     */
    public function getExportedFormat(): string
    {
        return $this->exportedFormat;
    }

    /**
     * @param string $exportedFormat
     *
     * @return ExportResourcesResponse
     */
    public function setExportedFormat(string $exportedFormat): self
    {
        $this->exportedFormat = $exportedFormat;

        return $this;
    }
}
