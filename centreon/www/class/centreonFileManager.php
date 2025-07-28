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

use Pimple\Container;

/**
 * Class
 *
 * @class CentreonFileManager
 */
class CentreonFileManager implements iFileManager
{
    /** @var mixed */
    protected $rawFile;

    /** @var Container */
    protected $dependencyInjector;

    /** @var string */
    protected $comment;

    /** @var mixed */
    protected $tmpFile;

    /** @var */
    protected $mediaPath;

    /** @var string */
    protected $destinationPath;

    /** @var mixed */
    protected $destinationDir;

    /** @var mixed */
    protected $originalFile;

    /** @var mixed */
    protected $fileName;

    /** @var mixed */
    protected $size;

    /** @var array|string */
    protected $extension;

    /** @var string */
    protected $newFile;

    /** @var string */
    protected $completePath;

    /** @var array */
    protected $legalExtensions = [];

    /** @var int */
    protected $legalSize = 500000;

    /**
     * CentreonFileManager constructor
     *
     * @param Container $dependencyInjector
     * @param $rawFile
     * @param $mediaPath
     * @param $destinationDir
     * @param string $comment
     */
    public function __construct(
        Container $dependencyInjector,
        $rawFile,
        $mediaPath,
        $destinationDir,
        $comment = ''
    ) {

        $this->dependencyInjector = $dependencyInjector;
        $this->mediaPath = $mediaPath;
        $this->comment = $comment;
        $this->rawFile = $rawFile['filename'];
        $this->destinationDir = $this->secureName($destinationDir);
        $this->destinationPath = $this->mediaPath . $this->destinationDir;
        $this->dirExist($this->destinationPath);
        $this->originalFile = $this->rawFile['name'];
        $this->tmpFile = $this->rawFile['tmp_name'];
        $this->size = $this->rawFile['size'];
        $this->extension = pathinfo($this->originalFile, PATHINFO_EXTENSION);
        $this->fileName = $this->secureName(basename($this->originalFile, '.' . $this->extension));
        $this->newFile = $this->fileName . '.' . $this->extension;
        $this->completePath = $this->destinationPath . '/' . $this->newFile;
    }

    /**
     * @return array|bool|void
     */
    public function upload()
    {
        if ($this->securityCheck()) {
            $this->moveFile();

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function securityCheck()
    {
        return ! (
            ! $this->validFile()
            || ! $this->validSize()
            || ! $this->secureExtension()
            || $this->fileExist()
        );

    }

    /**
     * @param $text
     *
     * @return array|string|string[]|null
     */
    protected function secureName($text)
    {
        $utf8 = ['/[áàâãªä]/u' => 'a', '/[ÁÀÂÃÄ]/u' => 'A', '/[ÍÌÎÏ]/u' => 'I', '/[íìîï]/u' => 'i', '/[éèêë]/u' => 'e', '/[ÉÈÊË]/u' => 'E', '/[óòôõºö]/u' => 'o', '/[ÓÒÔÕÖ]/u' => 'O', '/[úùûü]/u' => 'u', '/[ÚÙÛÜ]/u' => 'U', '/ç/' => 'c', '/Ç/' => 'C', '/ñ/' => 'n', '/Ñ/' => 'N', '/–/' => '-', '/[“”«»„"’‘‹›‚]/u' => '', '/ /' => '', '/\//' => '', '/\'/' => ''];

        return preg_replace(array_keys($utf8), array_values($utf8), $text);
    }

    /**
     * @return bool
     */
    protected function secureExtension()
    {

        return (bool) (in_array(strtolower($this->extension), $this->legalExtensions));
    }

    /**
     * @return bool
     */
    protected function validFile()
    {
        return ! (empty($this->tmpFile) || $this->size == 0);
    }

    /**
     * @return bool
     */
    protected function validSize()
    {
        return (bool) ($this->size < $this->legalSize);
    }

    /**
     * @return bool
     */
    protected function fileExist()
    {
        return (bool) (file_exists($this->completePath));
    }

    /**
     * @param mixed $dir
     * @return void
     */
    protected function dirExist($dir)
    {
        if (! is_dir($dir)) {
            @mkdir($dir);
        }
    }

    /**
     * @return void
     */
    protected function moveFile()
    {
        move_uploaded_file($this->tmpFile, $this->completePath);
    }
}
