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

use Core\Common\Infrastructure\Repository\FileDataStoreEngine;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;
use Core\Media\Domain\Model\NewMedia;

class FileWriteMediaRepository implements WriteMediaRepositoryInterface
{
    public function __construct(readonly private FileDataStoreEngine $engine)
    {
        $this->engine->throwsException(true);
    }

    /**
     * {@inheritDoc}
     *
     * @return int Returns the bytes written
     */
    public function add(NewMedia $media): int
    {
        $status = $this->engine->addFile(
            $media->getDirectory() . DIRECTORY_SEPARATOR . $media->getFilename(),
            $media->getData()
        );
        if ($status === false) {
            throw new \Exception($this->engine->getLastError());
        }

        return $status;
    }

    /**
     * {@inheritDoc}
     */
    public function update(Media $media): void
    {
        if ($media->getData() === null) {
            throw new \Exception('File content cannot be empty on update');
        }

        if (! $this->engine->addFile(
                $media->getDirectory() . DIRECTORY_SEPARATOR . $media->getFilename(),
                $media->getData()
            )
        ) {
            throw new \Exception($this->engine->getLastError());
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(Media $media): void
    {
        $this->engine->deleteFromFileSystem($media->getDirectory() . DIRECTORY_SEPARATOR . $media->getFilename());
    }
}
