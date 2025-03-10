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

/**
 * Class
 *
 * @class ExportResourcesViewModel
 * @package Core\Resources\Infrastructure\API\ExportResources
 */
final class ExportResourcesViewModel {
    /** @var \Traversable */
    private \Traversable $resources;
    /** @var string */
    private string $exportedFormat;

    /**
     * @return \Traversable
     */
    public function getResources(): \Traversable
    {
        return $this->resources;
    }

    /**
     * @param \Traversable $resources
     *
     * @return void
     */
    public function setResources(\Traversable $resources): void
    {
        $this->resources = $resources;
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
     * @return void
     */
    public function setExportedFormat(string $exportedFormat): void
    {
        $this->exportedFormat = $exportedFormat;
    }
}
