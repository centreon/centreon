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

declare(strict_types = 1);

namespace Core\Media\Infrastructure\Repository;

use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;
use Core\Media\Domain\Model\NewMedia;

class FileProxyWriteMediaRepository implements WriteMediaRepositoryInterface
{
    public function __construct(
        readonly private DbWriteMediaRepository $dbWriteMediaRepository,
        readonly private FileWriteMediaRepository $fileWriteMediaRepository,
    ) {
    }

    /**
     * Creates the file in the directory after it has been saved in the databases.
     *
     * {@inheritDoc}
     */
    public function add(NewMedia $media): int
    {
        $mediaId = $this->dbWriteMediaRepository->add($media);
        $this->fileWriteMediaRepository->add($media);

        return $mediaId;
    }

    /**
     * Deletes the file from the database and also from the file system.
     *
     * {@inheritDoc}
     */
    public function delete(Media $media): void
    {
        $this->dbWriteMediaRepository->delete($media);
        $this->fileWriteMediaRepository->delete($media);
    }
}
