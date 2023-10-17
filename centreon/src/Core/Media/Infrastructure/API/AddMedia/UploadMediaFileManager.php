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

namespace Core\Media\Infrastructure\API\AddMedia;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

class UploadMediaFileManager
{
    /** @var list<string> */
    private array $mimeTypes = [];

    private static ?\ZipArchive $zipArchive = null;

    /** @var list<string> */
    private array $fileExtensionsAllowed = [];

    public function __construct(readonly private UploadedFile $uploadedFile)
    {
    }

    public function addMimeTypeFilter(string ...$mimeType): void
    {
        foreach ($mimeType as $oneMimeType) {
            $this->mimeTypes[] = $oneMimeType;
            $this->addFileExtensions($oneMimeType);
        }
    }

    /**
     * @throws \Exception
     *
     * @return \Generator<array<int, string>>
     */
    public function getFiles(): \Generator
    {
        if ($this->uploadedFile->getError()) {
            throw new \Exception($this->uploadedFile->getErrorMessage());
        }
        if ($this->uploadedFile->getMimeType() === 'application/zip') {
            $zip = $this->openZipArchive($this->uploadedFile->getPathname());
             for ($index = 0; $index < $zip->count(); $index++) {
               $rawData = $zip->getFromIndex($index);
               if (empty($rawData)) {
                   continue;
               }
               $fileName = $zip->getNameIndex($index);
               if ($fileName !== false) {
                   $fileInfo = pathinfo($fileName);
                   $directories = explode(DIRECTORY_SEPARATOR, $fileInfo['dirname'] ?? '');
                   if ($directories[0] === '__MACOSX') {
                       continue; // The __MACOSX files are generated automatically by macOS during ZIP generation
                   }
                   if (in_array($fileInfo['extension'] ?? '', $this->fileExtensionsAllowed, true)) {
                       if (! array_key_exists('basename', $fileInfo)) {
                           continue;
                       }

                       yield [$fileInfo['basename'], $rawData];
                   }
               }
            }
        } elseif (in_array($this->uploadedFile->getMimeType(), $this->mimeTypes, true)) {
            yield [
                $this->uploadedFile->getClientOriginalName(),
                file_get_contents($this->uploadedFile->getPathname())
                    ?: throw new \Exception(sprintf('Error when reading file \'%s\'', $this->uploadedFile->getPathname())),
            ];
        }
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
            default => 'Unknown error'
        };
    }

    /**
     * @param string $mimeType
     */
    private function addFileExtensions(string $mimeType): void
    {
        foreach (MimeTypes::getDefault()->getExtensions($mimeType) as $oneMimeType) {
            $this->fileExtensionsAllowed[] = $oneMimeType;
        }
    }

    /**
     * @param string $filepath
     *
     * @throws \Exception
     *
     * @return \ZipArchive
     */
    private function openZipArchive(string $filepath): \ZipArchive {
        if (self::$zipArchive === null) {
            $zip = new \ZipArchive();
            $openStatus = $zip->open($filepath);
            if ($openStatus !== true) {
                throw new \Exception($this->getErrorMessage($openStatus));
            }
            self::$zipArchive = $zip;
        }

        return self::$zipArchive;
    }
}
