<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Centreon\Infrastructure\Service;

use Centreon\Infrastructure\FileManager\File;
use Psr\Container\ContainerInterface;

class UploadFileService
{
    /** @var array|null */
    protected $filesRequest;

    protected ContainerInterface $services;

    /**
     * Construct
     *
     * @param ContainerInterface $services
     * @param array $filesRequest Copy of $_FILES
     */
    public function __construct(ContainerInterface $services, ?array $filesRequest = null)
    {
        $this->services = $services;
        $this->filesRequest = $filesRequest;
    }

    /**
     * Get all files
     *
     * @return array
     */
    public function getFiles(string $fieldName, ?array $withExtension = null): array
    {
        $filesFromRequest = $this->prepare($fieldName);

        $result = [];
        foreach ($filesFromRequest as $data) {
            $file = new File($data);

            if ($withExtension !== null && in_array($file->getExtension(), $withExtension) === false) {
                continue;
            }

            $result[] = $file;
        }

        return $result;
    }

    public function prepare(string $fieldName): array
    {
        $result = [];

        if (array_key_exists($fieldName, $this->filesRequest) === false) {
            return $result;
        }

        foreach ($this->filesRequest[$fieldName] as $prop => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $result[$key][$prop] = $value;
                }
            } else {
                $result[0][$prop] = $values;
            }
        }

        return $result;
    }
}
