<?php
/*
 * Copyright 2015-2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class Centreon_OpenTickets_Request
{
    /**
     *
     * @var array
     */
    protected $_postVar;

    /**
     *
     * @var array
     */
    protected $_getVar;

    /**
     * constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->_postVar = array();
        $this->_getVar = array();

        if (isset($_POST)) {
            foreach ($_POST as $key => $value) {
                $this->_postVar[$key] = $value;
            }
        }

        if (isset($_GET)) {
            foreach ($_GET as $key => $value) {
                $this->_getVar[$key] = $value;
            }
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
        if (isset($this->_getVar[$index])) {
            return $this->_getVar[$index];
        }
        if (isset($this->_postVar[$index])) {
            return $this->_postVar[$index];
        }
        return null;
    }
}
