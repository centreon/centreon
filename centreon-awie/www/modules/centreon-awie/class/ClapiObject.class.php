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

    /** @var array<mixed> */
    protected $dbConfigCentreon = [];
    /** @var array<mixed> */
    protected $dbConfigCentreonStorage = [];
    /** @var mixed */
    protected $db;
    /** @var mixed */
    protected $dbMon;
    /** @var array<mixed> */
    protected $clapiParameters = [];
    /** @var \CentreonClapi\CentreonAPI */
    protected $clapiConnector;
    /** @var \Pimple\Container */
    protected $dependencyInjector;

    /**
     * @param \Pimple\Container $dependencyInjector
     * @param array<mixed> $clapiParameters
     */
    public function __construct($dependencyInjector, $clapiParameters)
    {
        $this->dependencyInjector = $dependencyInjector;
        $this->clapiParameters = $clapiParameters;
        $this->connectToClapi();
    }

    /**
     * @param string $key
     * @param string $value
     * @return void
     */
    public function addClapiParameter($key, $value): void
    {
        $this->clapiParameters[$key] = $value;
    }

    /**
     * @return void
     */
    private function connectToClapi(): void
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
     * @param bool $withoutClose boolean disable using of PHP exit function (default: false)
     * @return mixed
     */
    public function export($withoutClose = false)
    {

        $this->clapiConnector->setOption($this->clapiParameters);
        $export = $this->clapiConnector->export($withoutClose);
        return $export;
    }

    /**
     * @param string $fileName
     * @return mixed
     */
    public function import($fileName)
    {
        $import = $this->clapiConnector->import($fileName);
        return $import;
    }
}
