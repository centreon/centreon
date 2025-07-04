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
 * @class CentreonObjects
 * @description Class that contains various methods for managing hosts
 */
class CentreonObjects
{
    /** @var CentreonDB */
    private $DB;

    /** @var array */
    public $hosts;

    /** @var array */
    public $services;

    /** @var array */
    public $hostgoups;

    /** @var array */
    public $servicegroups;

    /** @var array */
    public $commandes;

    /**
     * CentreonObjects constructor
     *
     * @param CentreonDB $pearDB
     */
    public function __construct($pearDB)
    {
        $this->DB = $pearDB;
        // $this->hostgroups = new CentreonHostGroups($pearDB);
    }
}
