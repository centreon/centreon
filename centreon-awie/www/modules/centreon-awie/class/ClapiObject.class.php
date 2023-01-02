<?php

/**
 * Copyright 2021 Centreon
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
 */

class ClapiObject
{

    protected $dbConfigCentreon = array();
    protected $dbConfigCentreonStorage = array();
    protected $db;
    protected $dbMon;
    protected $clapiParameters = array();
    protected $clapiConnector;
    protected $dependencyInjector;

    public function __construct($dependencyInjector, $clapiParameters)
    {
        $this->dependencyInjector = $dependencyInjector;
        $this->clapiParameters = $clapiParameters;
        $this->connectToClapi();
    }

    /**
     * @param $key
     * @param $value
     */
    public function addClapiParameter($key, $value)
    {
        $this->clapiParameters[$key] = $value;
    }

    /**
     *
     */
    private function connectToClapi()
    {


        \CentreonClapi\CentreonUtils::setUserName($this->clapiParameters['username']);
        $this->clapiConnector = \CentreonClapi\CentreonAPI::getInstance(
            '',
            '',
            '',
            _CENTREON_PATH_,
            $this->clapiParameters,
            $this->dependencyInjector
        );
    }

    /**
     * Export
     *
     * @param $withoutClose boolean disable using of PHP exit function (default: false)
     * @return mixed
     */
    public function export($withoutClose = false)
    {

        $this->clapiConnector->setOption($this->clapiParameters);
        $export = $this->clapiConnector->export($withoutClose);
        return $export;
    }

    /**
     * @return mixed
     */
    public function import($fileName)
    {
        $import = $this->clapiConnector->import($fileName);
        return $import;
    }
}
