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

use Core\Media\Infrastructure\API\Exception\MediaException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class AddMediaValidator
{
    /** @var array<string, mixed> */
    private array $allProperties = [];

    public function __construct(readonly private Request $request)
    {
        $this->allProperties = array_unique(
            array_merge($this->request->request->keys(), $this->request->files->keys())
        );
    }

    /**
     * @throws MediaException
     */
    public function assertFilesSent(): void
    {
        if (! in_array('data', $this->allProperties, true)) {
            throw MediaException::propertyNotPresent('data');
        }
        $files = $this->request->files->get('data');
        // The presence of an array means that it contains files.
        if (! is_array($files)) {
            $this->assertUploadedFile($files);
        }
    }

    /**
     * @throws MediaException
     */
    public function assertDirectory(): void
    {
        if (! in_array('directory', $this->allProperties, true)) {
            MediaException::propertyNotPresent('directory');
        }

        $value = $this->request->get('directory');
        if (empty($value)) {
            MediaException::stringPropertyCanNotBeEmpty('directory');
        }
    }

    /**
     * @param mixed $file
     *
     * @throws MediaException
     */
    private function assertUploadedFile(mixed $file): void
    {
        if (! $file instanceof UploadedFile) {
            throw MediaException::wrongFileType('data');
        }
    }
}
