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

namespace Core\Common\Infrastructure\Upload;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileCollection
{
    private \AppendIterator $appendIterator;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->appendIterator = new \AppendIterator();
        $this->appendIterator->append(new CommonFileIterator());
    }

    /**
     * @param UploadedFile $file
     *
     * @throws \Exception
     */
    public function addFile(UploadedFile $file): void
    {
        if ($file->getMimeType() === ZipFileIterator::MIME_TYPE) {
            $this->appendIterator->append(new ZipFileIterator($file));
        } else {
            foreach ($this->appendIterator->getArrayIterator() as $iterator) {
                if ($iterator instanceof CommonFileIterator) {
                    $iterator->addFile($file);
                    break;
                }
            }
        }
    }

    /**
     * @return \AppendIterator<string, string, FileIteratorInterface>
     */
    public function getFiles(): \AppendIterator
    {
        return $this->appendIterator;
    }
}
