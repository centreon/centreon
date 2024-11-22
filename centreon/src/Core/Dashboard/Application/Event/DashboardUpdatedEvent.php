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

namespace Core\Dashboard\Application\Event;

readonly final class DashboardUpdatedEvent
{
    /**
     * @param int $dashboardId
     * @param string $directory
     * @param string $content
     * @param string $filename
     * @param int|null $thumbnailId
     */
    public function __construct(
        private int $dashboardId,
        private string $directory,
        private string $content,
        private string $filename,
        private int|null $thumbnailId = null
    ) {
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    public function getDashboardId(): int
    {
        return $this->dashboardId;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getThumbnailId(): int|null
    {
        return $this->thumbnailId;
    }
}
