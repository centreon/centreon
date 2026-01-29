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

class CentreonOpenTicketsRequest
{
    /** @var array */
    protected $postVar = [];

    /** @var array */
    protected $getVar = [];

    /**
     * constructor
     *
     * @return void
     */
    public function __construct()
    {
        foreach ($_POST as $key => $value) {
            $this->postVar[$key] = $value;
        }

        foreach ($_GET as $key => $value) {
            $this->getVar[$key] = $value;
        }
    }

    /**
     * Return value of requested object
     *
     * @param string $index
     * @return mixed
     */
    public function getParam($index)
    {
        return $this->getVar[$index] ?? $this->postVar[$index] ?? null;
    }
}
