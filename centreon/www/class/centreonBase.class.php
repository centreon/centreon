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
 * @class CentreonBase
 * @description Class for request
 */
class CentreonBase
{
    /** @var string */
    public $index;

    // Objects

    /** @var CentreonDB */
    protected $DB;

    /** @var CentreonDB */
    protected $DBC;

    /** @var CentreonGMT */
    protected $GMT;

    /** @var CentreonHost */
    protected $hostObj;

    /** @var CentreonService */
    protected $serviceObj;

    /** @var string */
    protected $sessionId;

    // Variables

    /** @var int */
    protected $debug;

    /** @var int|mixed */
    protected $compress;

    /** @var */
    protected $userId;

    /** @var */
    protected $general_opt;

    /**
     * CentreonBase constructor
     *
     * <code>
     *  $obj = new CentreonBGRequest($_GET["session_id"], 1, 1, 0, 1);
     * </code>
     *
     * @param string $sessionId
     * @param bool $index
     * @param bool $debug
     * @param bool $compress
     *
     * @throws PDOException
     */
    public function __construct($sessionId, $index, $debug, $compress = null)
    {
        if (! isset($debug)) {
            $this->debug = 0;
        }

        (! isset($compress)) ? $this->compress = 1 : $this->compress = $compress;

        if (! isset($sessionId)) {
            echo 'Your must check your session id';

            exit(1);
        }
        $this->sessionId = htmlentities($sessionId, ENT_QUOTES, 'UTF-8');

        $this->index = htmlentities($index, ENT_QUOTES, 'UTF-8');

        // Enable Database Connexions
        $this->DB = new CentreonDB();
        $this->DBC = new CentreonDB('centstorage');

        // Init Objects
        $this->hostObj = new CentreonHost($this->DB);
        $this->serviceObj = new CentreonService($this->DB);

        // Timezone management
        $this->GMT = new CentreonGMT($this->DB);
        $this->GMT->getMyGMTFromSession($this->sessionId);
    }

    /**
     * @param $options
     *
     * @return void
     */
    public function setGeneralOption($options): void
    {
        $this->general_opt = $options;
    }
}
