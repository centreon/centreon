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

namespace CentreonClapi;

/**
 * Class
 *
 * @class CentreonExported
 * @package CentreonClapi
 */
class CentreonExported
{
    /** @var array */
    private $exported = [];

    /** @var array */
    private $ariane = [];

    /** @var int */
    private $filter = 0;

    /** @var array|null */
    private $filter_type = null;

    /** @var array|null */
    private $filter_ariane = null;

    /** @var CentreonExported|null */
    private static $instance = null;

    /**
     * @param $object
     * @param $id
     * @param $name
     *
     * @return void
     */
    public function arianePush($object, $id, $name): void
    {
        array_push($this->ariane, $object . ':' . $name . ':' . $id);
    }

    /**
     * @return void
     */
    public function arianePop(): void
    {
        array_pop($this->ariane);
    }

    /**
     * @param $value
     *
     * @return void
     */
    public function setFilter($value = 1): void
    {
        $this->filter = $value;
    }

    /**
     * @param $options
     *
     * @return void
     */
    public function setOptions($options): void
    {
        if (isset($options['filter-type'])) {
            $this->filter_type = $options['filter-type'];
            if (! is_array($options['filter-type'])) {
                $this->filter_type = [$options['filter-type']];
            }
        }

        if (isset($options['filter-ariane'])) {
            $this->filter_ariane = $options['filter-ariane'];
            if (! is_array($options['filter-ariane'])) {
                $this->filter_ariane = [$options['filter-ariane']];
            }
        }
    }

    /**
     * @param string $object
     * @param int $id
     *
     * @return void
     */
    public function setExported(string $object, int $id): void
    {
        $this->exported[$object][$id] = 1;
    }

    /**
     * @param $object
     * @param $id
     * @param $name
     *
     * @return int
     */
    private function checkAriane($object, $id, $name)
    {
        if (! is_null($this->filter_ariane)) {
            $ariane = join('#', $this->ariane);
            foreach ($this->filter_ariane as $filter) {
                if (preg_match('/' . $filter . '/', $ariane)) {
                    return 0;
                }
            }

            return 1;
        }

        return 0;
    }

    /**
     * @param $object
     * @param $id
     * @param $name
     *
     * @return int
     */
    private function checkFilter($object, $id, $name)
    {
        if (! is_null($this->filter_type)) {
            foreach ($this->filter_type as $filter) {
                if (preg_match('/' . $filter . '/', $object)) {
                    return 0;
                }
            }

            return 1;
        }

        return 0;
    }

    /**
     * @param $object
     * @param $id
     * @param $name
     *
     * @return int
     */
    public function isExported($object, $id, $name)
    {
        if ($this->filter == 0) {
            return 1;
        }

        if (isset($this->exported[$object][$id])) {
            return 1;
        }

        // check if there is some filters
        if ($this->checkFilter($object, $id, $name)) {
            return 1;
        }
        if ($this->checkAriane($object, $id, $name)) {
            return 1;
        }

        if (! isset($this->exported[$object]) || ! is_array($this->exported[$object])) {
            $this->exported[$object] = [];
        }
        $this->exported[$object][$id] = 1;

        return 0;
    }

    /**
     * @return CentreonExported
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new CentreonExported();
        }

        return self::$instance;
    }
}
