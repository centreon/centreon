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

use Core\Media\Domain\Model\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DashboardUpdatedEvent
{
    /**
     * @param int $dashboardId
     * @param Media|UploadedFile $thumbnail
     * @param string $directory
     */
    public function __construct(
        private readonly int $dashboardId,
        private string $directory,
        private readonly Media|UploadedFile $thumbnail,
    ) {
    }

    /**
     * @return Media|UploadedFile
     */
    public function getThumbnail(): Media|UploadedFile
    {
        return $this->thumbnail;
    }

    public function getDashboardId(): int
    {
        return $this->dashboardId;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }
}
