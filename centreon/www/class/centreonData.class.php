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

/**
 * Class
 *
 * @class CentreonData
 * @description Object used for storing and accessing specific data
 * @version 2.5.0
 * @since 2.5.0
 * @author Sylvestre Ho <sho@centreon.com>
 */
class CentreonData
{
    /**
     * Instance of Centreon Template
     *
     * @var CentreonData
     */
    private static $instance = null;

    /**
     * List of javascript data
     *
     * @var array
     */
    private $jsData = [];

    /**
     * Pass data to javascript
     *
     * @param string $key
     * @param string $value
     * @throws Exception
     * @return void
     */
    public function addJsData($key, $value): void
    {
        if (isset($this->jsData[$key])) {
            throw new Exception(
                sprintf('Key %s in Javascript Data already used', $key)
            );
        }
        $this->jsData[$key] = $value;
    }

    /**
     * Get javascript data
     *
     * @return array
     */
    public function getJsData()
    {
        return $this->jsData;
    }

    /**
     * Get a instance of Centreon_Template
     *
     * @return CentreonData
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new CentreonData();
        }

        return self::$instance;
    }
}

// vim: set ai softtabstop=4 shiftwidth=4 tabstop=4 expandtab:
