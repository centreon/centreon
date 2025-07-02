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

namespace Centreon\Infrastructure\Event;

/**
 * This class is used to find and include a specific php file in a tree.
 *
 * @package Centreon\Domain\Entity
 */
class FileLoader implements DispatcherLoaderInterface
{
    /** @var string Path where we will try to find php files */
    private $pathModules;

    /** @var string Name of the php file to find in path */
    private $filename;

    /**
     * FileLoader constructor.
     *
     * @param string $pathModules Path where we will try to find php files
     * @param string $filename Name of the php file to find in path
     */
    public function __construct(string $pathModules, string $filename)
    {
        $this->pathModules = $pathModules;
        $this->filename = $filename;
    }

    /**
     * Include all php file found.
     *
     * @throws \Exception
     */
    public function load(): void
    {
        if (! is_dir($this->pathModules)) {
            throw new \Exception(_('The path does not exist'));
        }
        $modules = scandir($this->pathModules);

        foreach ($modules as $module) {
            $fileToInclude = $this->pathModules . '/' . $module . '/' . $this->filename;
            if (preg_match('/^(?!\.)/', $module)
                && is_dir($this->pathModules . '/' . $module)
                && file_exists($fileToInclude)
            ) {
                require_once $fileToInclude;
            }
        }
    }
}
