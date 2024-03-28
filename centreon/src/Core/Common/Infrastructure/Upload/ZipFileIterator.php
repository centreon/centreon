<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Common\Infrastructure\Upload;

use Symfony\Component\HttpFoundation\File\File;

class ZipFileIterator implements FileIteratorInterface
{
    public const MIME_TYPE = 'application/zip';

    private \ZipArchive $zipArchive;

    private int $filePosition = 0;

    /**
     * @param File $file
     *
     * @throws \Exception
     */
    public function __construct(readonly private File $file)
    {
        if ($this->file->getMimeType() !== self::MIME_TYPE) {
            throw new \Exception(
                sprintf('Incompatible file type: %s != %s', $this->file->getMimeType(), self::MIME_TYPE)
            );
        }
        $this->zipArchive = new \ZipArchive();
        $openStatus = $this->zipArchive->open($file->getRealPath());
        if ($openStatus !== true) {
            throw new \Exception($this->getErrorMessage($openStatus));
        }
    }

    public function count(): int
    {
        return $this->zipArchive->count();
    }

    /**
     * @inheritDoc
     */
    public function current(): string
    {
        $fileContent = $this->zipArchive->getFromIndex($this->filePosition);
        if ($fileContent === false) {
            throw new \Exception();
        }

        return $fileContent;
    }

    /**
     * @throws \Exception
     */
    public function next(): void
    {
        $this->filePosition++;
    }

    /**
     * @throws \Exception
     */
    public function key(): string
    {
        $currentFilename = $this->zipArchive->getNameIndex($this->filePosition);
        if ($currentFilename === false) {
            throw new \Exception();
        }

        return $currentFilename;
    }

    public function valid(): bool
    {
        return $this->filePosition < $this->zipArchive->count();
    }

    /**
     * @throws \Exception
     */
    public function rewind(): void
    {
        $this->filePosition = 0;
    }

    /**
     * Retrieve the error message according to the open status error code.
     *
     * @param int $openStatus
     *
     * @return string
     */
    private function getErrorMessage(int $openStatus): string
    {
        return match ($openStatus) {
            \ZipArchive::ER_EXISTS => 'The file already exists',
            \ZipArchive::ER_INCONS => 'The ZIP archive is inconsistent',
            \ZipArchive::ER_INVAL => 'Invalid argument',
            \ZipArchive::ER_MEMORY => 'Memory allocation failure',
            \ZipArchive::ER_NOENT => 'The file does not exist',
            \ZipArchive::ER_NOZIP => 'This is not a ZIP archive',
            \ZipArchive::ER_OPEN => 'Unable to open file',
            \ZipArchive::ER_READ => 'Reading error',
            \ZipArchive::ER_SEEK => 'Position error',
            default => 'Unknown error',
        };
    }
}
