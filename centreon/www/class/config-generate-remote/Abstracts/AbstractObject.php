<?php
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace ConfigGenerateRemote\Abstracts;

use Exception;
use ConfigGenerateRemote\Backend;
use ConfigGenerateRemote\Manifest;
use Pimple\Container;

/**
 * Class
 *
 * @class AbstractObject
 * @package ConfigGenerateRemote\Abstracts
 */
abstract class AbstractObject
{
    /** @var array */
    public $attributes_array;
    /** @var array */
    public $attributes_hash;
    /** @var array */
    public $attributes_default;
    /** @var Backend|null */
    protected $backendInstance = null;
    /** @var string|null */
    protected $generateFilename = null;
    /** @var string|null */
    protected $table = null;
    /** @var array */
    protected $exported = [];
    /** @var resource|null */
    protected $fp = null;
    /** @var string */
    protected $type = 'infile';
    /** @var string */
    protected $subdir = 'configuration';

    /** @var array */
    protected $attributesWrite = [];
    /** @var array */
    protected $attributesArray = [];

    /** @var bool */
    protected $engine = true;
    /** @var bool */
    protected $broker = false;
    /** @var Container */
    protected $dependencyInjector;

    /** @var string|null */
    protected $fieldSeparatorInfile = null;
    /** @var string|null */
    protected $lineSeparatorInfile = null;

    /**
     * Get instance singleton
     *
     * @param Container $dependencyInjector
     * @return object
     */
    public static function getInstance(Container $dependencyInjector)
    {
        static $instances = [];
        $calledClass = static::class;

        if (!isset($instances[$calledClass])) {
            $instances[$calledClass] = new $calledClass($dependencyInjector);
        }

        return $instances[$calledClass];
    }

    /**
     * Constructor
     *
     * @param Container $dependencyInjector
     */
    protected function __construct(Container $dependencyInjector)
    {
        $this->dependencyInjector = $dependencyInjector;
        $this->backendInstance = Backend::getInstance($this->dependencyInjector);
        $this->fieldSeparatorInfile = $this->backendInstance->getFieldSeparatorInfile();
        $this->lineSeparatorInfile = $this->backendInstance->getLineSeparatorInfile();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->closeFile();
    }

    /**
     * Close file if open
     *
     * @return void
     */
    public function closeFile(): void
    {
        if (!is_null($this->fp)) {
            fclose($this->fp);
        }
        $this->fp = null;
    }

    /**
     * Reset object
     *
     * @param bool $createfile
     *
     * @return void
     * @throws Exception
     */
    public function reset($createfile = false): void
    {
        $this->closeFile();
        $this->exported = [];
        if ($createfile == true) {
            $this->createFile($this->backendInstance->getPath());
        }
    }

    /**
     * Create generateFilename in given directory
     *
     * @param string $dir
     *
     * @return void
     * @throws Exception
     */
    protected function createFile(string $dir): void
    {
        $fullFile = $dir . '/' . $this->subdir . '/' . $this->generateFilename;
        if (!($this->fp = @fopen($fullFile, 'a+'))) {
            throw new Exception("Cannot open file (writing permission) '" . $fullFile . "'");
        }

        if (posix_getuid() === fileowner($fullFile)) {
            chmod($fullFile, 0660);
        }

        if ($this->type == 'infile') {
            Manifest::getInstance($this->dependencyInjector)->addFile(
                $this->generateFilename,
                $this->type,
                $this->table,
                $this->attributesWrite
            );
        }
    }

    /**
     * Convert string in UTF-8
     *
     * @param string $str
     * @return string
     */
    private function toUTF8(string $str): string
    {
        $finalString = $str;
        if (mb_detect_encoding($finalString, 'UTF-8', true) !== 'UTF-8') {
            $finalString = mb_convert_encoding($finalString, 'UTF-8');
        }

        return $finalString;
    }

    /**
     * Write object in file
     *
     * @param array $object
     * @return void
     */
    protected function writeObject(array $object): void
    {
        $line = '';
        $append = '';
        $counter = count($this->attributesWrite);
        for ($i = 0; $i < $counter; $i++) {
            if (isset($object[$this->attributesWrite[$i]]) && strlen($object[$this->attributesWrite[$i]])) {
                $line .= $append . '"' . str_replace('"', '""', $object[$this->attributesWrite[$i]]) . '"';
            } else {
                $line .= $append . '\N';
            }
            $append = $this->fieldSeparatorInfile;
        }

        fwrite($this->fp, $line . $this->lineSeparatorInfile);
    }

    /**
     * Generate object in file
     *
     * @param array $object
     * @param int|string|null $id
     *
     * @return void
     * @throws Exception
     */
    protected function generateObjectInFile(array $object, $id = null): void
    {
        if (is_null($this->fp)) {
            $this->createFile($this->backendInstance->getPath());
        }
        $this->writeObject($object);
        if (!is_null($id)) {
            $this->exported[$id] = 1;
        }
    }

    /**
     * Write string in file
     *
     * @param array $object
     * @return void
     */
    private function writeNoObject(array $object): void
    {
        foreach ($this->attributes_array as &$attr) {
            if (isset($object[$attr]) && !is_null($object[$attr]) && is_array($object[$attr])) {
                foreach ($object[$attr] as $v) {
                    fwrite($this->fp, $this->toUTF8($attr . "=" . $v . "\n"));
                }
            }
        }

        foreach ($this->attributes_hash as &$attr) {
            if (!isset($object[$attr])) {
                continue;
            }
            foreach ($object[$attr] as $key => &$value) {
                fwrite($this->fp, $this->toUTF8($key . "=" . $value . "\n"));
            }
        }

        foreach ($this->attributesWrite as &$attr) {
            if (isset($object[$attr]) && !is_null($object[$attr]) && $object[$attr] != '') {
                fwrite($this->fp, $this->toUTF8($attr . "=" . $object[$attr] . "\n"));
            }
        }

        foreach ($this->attributes_default as &$attr) {
            if (isset($object[$attr]) && !is_null($object[$attr]) && $object[$attr] != 2) {
                fwrite($this->fp, $this->toUTF8($attr . "=" . $object[$attr] . "\n"));
            }
        }
    }

    /**
     * Generate file
     *
     * @param array $object
     *
     * @return void
     * @throws Exception
     */
    protected function generateFile(array $object): void
    {
        if (is_null($this->fp)) {
            $this->createFile($this->backendInstance->getPath());
        }

        $this->writeNoObject($object);
    }

    /**
     * Check if an id has already been generated
     *
     * @param int $id
     * @return bool
     */
    public function checkGenerate($id): bool
    {
        if (isset($this->exported[$id])) {
            return true;
        }

        return false;
    }

    /**
     * Get exported ids
     *
     * @return array
     */
    public function getExported(): array
    {
        return $this->exported ?? [];
    }

    /**
     * Check if current object is engine
     *
     * @return bool
     */
    public function isEngineObject(): bool
    {
        return $this->engine;
    }

    /**
     * Check if current object is broker
     *
     * @return bool
     */
    public function isBrokerObject(): bool
    {
        return $this->broker;
    }
}
